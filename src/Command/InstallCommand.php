<?php
declare(strict_types=1);

namespace Crustum\Ignis\Command;

use Cake\Collection\Collection;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Crustum\Ignis\Contracts\SupportsGuidelines;
use Crustum\Ignis\Contracts\SupportsSkills;
use Crustum\Ignis\Install\Agents\Agent;
use Crustum\Ignis\Install\AgentsDetector;
use Crustum\Ignis\Install\GuidelineComposer;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Ignis\Install\GuidelineWriter;
use Crustum\Ignis\Install\InstallPath;
use Crustum\Ignis\Install\McpWriter;
use Crustum\Ignis\Install\RuleComposer;
use Crustum\Ignis\Install\Skill;
use Crustum\Ignis\Install\SkillComposer;
use Crustum\Ignis\Install\SkillWriter;
use Crustum\Ignis\Install\ThirdPartyPackage;
use Crustum\Ignis\Rules\RuleRepository;
use Crustum\Ignis\Support\Config;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Ignis\Support\RenderFailures;
use Crustum\Ignis\Trait\ConsolePromptTrait;
use Crustum\Ignis\Trait\ReportsSkillParseFailuresTrait;
use Crustum\Inspector\ProjectManager;
use Exception;
use Override;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Installs Ignis guidelines, skills, and MCP configuration for AI agents.
 */
class InstallCommand extends Command
{
    use ConsolePromptTrait;
    use ReportsSkillParseFailuresTrait;

    public const MIN_TEST_COUNT = 6;

    /**
     * Selected agents for installation.
     *
     * @var \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Agents\Agent>
     */
    protected Collection $selectedAgents;

    /**
     * Selected Ignis features.
     *
     * @var \Cake\Collection\Collection<int, string>
     */
    protected Collection $selectedIgnisFeatures;

    /**
     * Selected third-party packages.
     *
     * @var \Cake\Collection\Collection<int, string>
     */
    protected Collection $selectedThirdPartyPackages;

    /**
     * Project display name.
     */
    protected string $projectName = '';

    /**
     * System-installed agent keys discovered during install.
     *
     * @var array<int, string>
     */
    protected array $systemInstalledAgents = [];

    /**
     * Project-installed agent keys discovered during install.
     *
     * @var array<int, string>
     */
    protected array $projectInstalledAgents = [];

    /**
     * Whether enforce-tests guidelines should be included.
     */
    protected bool $enforceTests = true;

    /**
     * Skill names installed during the current run.
     *
     * @var array<int, string>
     */
    protected array $installedSkillNames = [];

    /**
     * Active command arguments.
     */
    protected ?Arguments $arguments = null;

    /**
     * Constructor.
     *
     * @param \Crustum\Ignis\Install\AgentsDetector $agentsDetector Agent detector
     * @param \Crustum\Ignis\Support\Config $config Ignis install config
     * @param \Crustum\Ignis\Install\GuidelineComposer $guidelineComposer Guideline composer
     * @param \Crustum\Ignis\Install\SkillComposer $skillComposer Skill composer
     * @param \Crustum\Ignis\Rules\RuleRepository $ruleRepository Rule repository
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory
     */
    public function __construct(
        protected AgentsDetector $agentsDetector,
        protected Config $config,
        protected GuidelineComposer $guidelineComposer,
        protected SkillComposer $skillComposer,
        protected RuleRepository $ruleRepository,
        protected ProjectManager $project,
        ?CommandFactoryInterface $factory = null,
    ) {
        parent::__construct($factory);

        $this->selectedAgents = new Collection([]);
        $this->selectedIgnisFeatures = new Collection([]);
        $this->selectedThirdPartyPackages = new Collection([]);
    }

    /**
     * Execute the command.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int|null
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $this->skillParseFailures()->flush();
        $this->arguments = $args;
        $this->projectName = (string)Configure::read('App.name', 'Application');

        $previousOverride = ProjectRoot::override();

        try {
            $installPath = $this->applyInstallPathOption($args, $io);

            if ($installPath === false) {
                return static::CODE_ERROR;
            }

            $this->displayIgnisHeader($io, 'Install', $this->projectName);

            if (is_string($installPath)) {
                $io->info(sprintf('Writing Ignis assets to [%s]', $installPath));
            }

            $this->discoverEnvironment();
            $this->collectInstallationPreferences($args, $io);
            $this->performInstallation($io);

            $this->reportRenderFailures($io);
            $this->reportSkillParseFailures($io);

            $this->noteInferConventions($io);

            $this->outro($io);

            return static::CODE_SUCCESS;
        } finally {
            ProjectRoot::set($previousOverride);
        }
    }

    /**
     * Apply --path write root and conflict guard. Returns resolved path, null when unused, false on error.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return string|false|null
     */
    protected function applyInstallPathOption(Arguments $args, ConsoleIo $io): string|null|false
    {
        $pathOption = $args->getOption('path');

        if (in_array($pathOption, [null, false, ''], true)) {
            return null;
        }

        try {
            $resolved = InstallPath::resolve((string)$pathOption);
        } catch (RuntimeException $runtimeException) {
            $io->error($runtimeException->getMessage());

            return false;
        }

        if (!$args->getBooleanOption('force') && InstallPath::hasAiConflict($resolved)) {
            $io->error(sprintf(
                'Install path [%s] already contains a [.ai] directory. '
                . 'Choose an empty target or re-run with --force to overwrite.',
                $resolved,
            ));

            return false;
        }

        ProjectRoot::set($resolved);

        return $resolved;
    }

    /**
     * Discover installed agents when ignis.json has no saved agents.
     *
     * @return void
     */
    protected function discoverEnvironment(): void
    {
        if ($this->config->getAgents() !== []) {
            return;
        }

        $this->systemInstalledAgents = $this->agentsDetector->discoverSystemInstalledAgents();
        $this->projectInstalledAgents = $this->agentsDetector->discoverProjectInstalledAgents(ProjectRoot::path());
    }

    /**
     * Collect feature, package, and agent selections.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function collectInstallationPreferences(Arguments $args, ConsoleIo $io): void
    {
        $this->selectedIgnisFeatures = $this->selectIgnisFeatures($args, $io);

        $this->selectedThirdPartyPackages = $this->selectedIgnisFeatures->contains('guidelines')
            || $this->selectedIgnisFeatures->contains('skills')
            ? $this->selectThirdPartyPackages($args, $io)
            : new Collection([]);

        $this->selectedAgents = $this->selectAgents($args, $io);
        $this->enforceTests = $this->selectedIgnisFeatures->contains('guidelines')
            && $this->determineTestEnforcement();
    }

    /**
     * Run the selected installation steps.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function performInstallation(ConsoleIo $io): void
    {
        if ($this->selectedIgnisFeatures->contains('guidelines')) {
            $this->installGuidelines($io);
        }

        if ($this->selectedIgnisFeatures->contains('skills')) {
            $this->installSkills($io);
        }

        if ($this->selectedIgnisFeatures->contains('mcp')) {
            $this->installMcpServerConfig($io);
        }

        $this->storeConfig();
    }

    /**
     * Report guidelines that failed to render before the outro.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function reportRenderFailures(ConsoleIo $io): void
    {
        $renderFailures = $this->renderFailures();
        if ($renderFailures->isEmpty()) {
            return;
        }

        $paths = $renderFailures->paths();
        $packages = $renderFailures->packages();

        $io->out('');
        $io->warning(sprintf(
            'Skipped %d %s that could not be rendered:',
            count($paths),
            count($paths) === 1 ? 'file' : 'files',
        ));

        foreach ($paths as $path) {
            $io->out('  - ' . str_replace(ProjectRoot::path() . DS, '', $path));
        }

        if ($packages !== []) {
            $io->warning(
                'These ship Ignis files built for an older Ignis version, so Ignis used its own where it had them. '
                . 'Update them with: composer update ' . implode(' ', $packages),
            );
        }
    }

    /**
     * Return the shared render-failures recorder.
     *
     * @return \Crustum\Ignis\Support\RenderFailures
     */
    protected function renderFailures(): RenderFailures
    {
        $container = Configure::read('app.container');

        if ($container instanceof ContainerInterface && $container->has(RenderFailures::class)) {
            return $container->get(RenderFailures::class);
        }

        return new RenderFailures();
    }

    /**
     * Nudge agents to run the infer-conventions skill after install.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function noteInferConventions(ConsoleIo $io): void
    {
        $this->displayNote(
            $io,
            'Run the infer-conventions skill to record your app conventions and sharpen code generation.',
        );
    }

    /**
     * Render the install outro banner.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function outro(ConsoleIo $io): void
    {
        $url = 'https://github.com/crustum/ignis';
        $this->displayOutro($io, 'Enjoy the ignis. Next steps: ' . $url);
    }

    /**
     * Determine whether enforce-tests guidelines should be included.
     *
     * @return bool
     */
    protected function determineTestEnforcement(): bool
    {
        $configured = Configure::read('Ignis.enforce_tests');

        if ($configured !== null) {
            return (bool)$configured;
        }

        $phpunit = ProjectRoot::applicationPath() . DS . 'vendor' . DS . 'bin' . DS . 'phpunit';

        if (!is_file($phpunit)) {
            return false;
        }

        $process = new Process([PHP_BINARY, $phpunit, '--list-tests'], ProjectRoot::applicationPath());

        try {
            $process->run();
        } catch (ProcessSignaledException) {
            return false;
        }

        $count = 0;

        foreach (explode("\n", trim($process->getOutput())) as $line) {
            if (str_contains($line, '::') || str_contains($line, ' with data set ')) {
                $count++;
            }
        }

        return $count >= self::MIN_TEST_COUNT;
    }

    /**
     * Select Ignis features to install.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return \Cake\Collection\Collection<int, string>
     */
    protected function selectIgnisFeatures(Arguments $args, ConsoleIo $io): Collection
    {
        $featureLabels = [
            'guidelines' => 'AI Guidelines',
            'skills' => 'Agent Skills',
            'mcp' => 'Ignis MCP Server Configuration',
        ];

        $explicit = (new Collection(array_keys($featureLabels)))
            ->filter(fn(string $feature): bool => $args->getBooleanOption($feature) === true);

        if (!$explicit->isEmpty()) {
            return new Collection($explicit->toList());
        }

        $configValues = [
            'guidelines' => $this->config->getGuidelines(),
            'skills' => $this->config->hasSkills(),
            'mcp' => $this->config->getMcp(),
        ];

        $defaults = [];

        foreach ($configValues as $feature => $enabled) {
            if ($enabled) {
                $defaults[] = $feature;
            }
        }

        if ($defaults === []) {
            $defaults = array_keys($featureLabels);
        }

        if (!$this->isInteractive($args, $io)) {
            return new Collection($defaults);
        }

        return new Collection($this->promptMultiselect(
            $io,
            'Which Ignis features would you like to configure?',
            $featureLabels,
            $defaults,
            true,
            'This will override the current guidelines, skills, and MCP configuration',
        ));
    }

    /**
     * Select third-party guideline and skill packages.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return \Cake\Collection\Collection<int, string>
     */
    protected function selectThirdPartyPackages(Arguments $args, ConsoleIo $io): Collection
    {
        $packages = ThirdPartyPackage::discover($this->project);

        if ($packages->isEmpty()) {
            return new Collection([]);
        }

        $defaults = (new Collection($this->config->getPackages()))
            ->filter(fn(string $name): bool => $packages->some(
                fn(ThirdPartyPackage $package, string $packageName): bool => $packageName === $name,
            ))
            ->toList();

        if (!$this->isInteractive($args, $io)) {
            return new Collection($defaults);
        }

        $options = $packages
            ->map(fn(ThirdPartyPackage $package, string $name): array => [$name => $package->displayLabel()])
            ->reduce(fn(array $carry, array $item): array => array_merge($carry, $item), []);

        return new Collection($this->promptMultiselect(
            $io,
            'Which third-party AI guidelines/skills would you like to install?',
            $options,
            $defaults,
            false,
            'You can add or remove them later by running this command again',
        ));
    }

    /**
     * Select agents to configure.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Agents\Agent>
     */
    protected function selectAgents(Arguments $args, ConsoleIo $io): Collection
    {
        $allAgents = $this->agentsDetector->getAgents();

        if ($allAgents->isEmpty()) {
            return new Collection([]);
        }

        $filteredAgents = $allAgents->filter(
            fn(Agent $agent): bool => $this->selectedIgnisFeatures->some(
                fn(string $feature): bool => match ($feature) {
                    'guidelines' => $agent instanceof SupportsGuidelines,
                    'skills' => $agent instanceof SupportsSkills,
                    'mcp' => true,
                    default => false,
                },
            ),
        )->indexBy(fn(Agent $agent): string => $agent->name());

        $filteredAgentMap = $filteredAgents->toArray();

        if ($filteredAgentMap === []) {
            return new Collection([]);
        }

        $options = $filteredAgents
            ->map(fn(Agent $agent): array => [$agent->name() => $agent->displayName()])
            ->reduce(fn(array $carry, array $item): array => array_merge($carry, $item), []);

        ksort($options);

        $defaults = (new Collection($this->config->getAgents()))
            ->filter(fn(string $name): bool => array_key_exists($name, $filteredAgentMap))
            ->toList();

        if ($defaults === []) {
            $defaults = (new Collection([...$this->projectInstalledAgents, ...$this->systemInstalledAgents]))
                ->filter(fn(string $name): bool => array_key_exists($name, $filteredAgentMap))
                ->toList();
        }

        if (!$this->isInteractive($args, $io)) {
            $selectedAgents = [];

            foreach ($defaults as $name) {
                if (isset($filteredAgentMap[$name])) {
                    $selectedAgents[$name] = $filteredAgentMap[$name];
                }
            }

            return new Collection($selectedAgents);
        }

        $selected = $this->promptMultiselect(
            $io,
            'Which AI agents would you like to configure?',
            $options,
            $defaults,
            true,
        );

        $selectedAgents = [];

        foreach ($selected as $name) {
            if (isset($filteredAgentMap[$name])) {
                $selectedAgents[$name] = $filteredAgentMap[$name];
            }
        }

        return new Collection($selectedAgents);
    }

    /**
     * Install composed guidelines for selected agents.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function installGuidelines(ConsoleIo $io): void
    {
        $guidelinesAgents = $this->agentsWithGuidelines();
        $composer = $this->guidelineComposer->config($this->buildGuidelineConfig());
        $this->syncRuleFiles($io, $composer);
        $guidelines = $composer->guidelines();
        $composedAiGuidelines = $composer->compose();

        $this->installFeature(
            io: $io,
            agents: $guidelinesAgents,
            emptyMessage: 'No agents are selected for guideline installation.',
            headerMessage: sprintf('Adding %d guidelines to your selected agents', $guidelines->count()),
            nameResolver: fn(Agent $agent): string => $agent->displayName(),
            processor: function (Agent $agent) use ($composedAiGuidelines): int {
                if (!$agent instanceof SupportsGuidelines) {
                    throw new RuntimeException(sprintf('Agent [%s] does not support guidelines.', $agent->name()));
                }

                return (new GuidelineWriter($agent))->write($composedAiGuidelines);
            },
            featureName: 'guidelines',
            beforeProcess: function () use ($io, $guidelines): void {
                $labels = [];

                foreach ($guidelines->toArray() as $key => $guideline) {
                    $labels[] = $key . ($guideline['custom'] ? '*' : '');
                }

                sort($labels);
                $this->displayGrid($io, $labels);
            },
            withDelay: true,
        );
    }

    /**
     * Extract path-scoped guideline blocks into `.ai/rules/ignis`.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param \Crustum\Ignis\Install\GuidelineComposer $composer Guideline composer
     * @return void
     */
    protected function syncRuleFiles(ConsoleIo $io, GuidelineComposer $composer): void
    {
        if (
            !(bool)Configure::read('Ignis.rules.enabled', true)
            || !(bool)Configure::read('Ignis.rules.scoped_guidelines', false)
        ) {
            try {
                $this->ruleRepository->clearManaged();
            } catch (Throwable) {
            }

            return;
        }

        try {
            $written = $this->ruleRepository->syncManaged((new RuleComposer($composer))->composeManaged());
        } catch (Throwable) {
            try {
                $this->ruleRepository->clearManaged();
            } catch (Throwable $cleanupError) {
                throw new RuntimeException(
                    'Failed to write path-scoped rules and could not clear .ai/rules/ignis. '
                    . 'Resolve the directory (it may be locked) and re-run ignis install.',
                    0,
                    $cleanupError,
                );
            }

            $composer->withoutRuleExtraction();
            $io->warning('Could not write path-scoped rules to .ai/rules/ignis — keeping them inline in the guidelines instead.');

            return;
        }

        if ($written === []) {
            return;
        }

        $io->info(sprintf(
            'Extracted %d path-scoped %s to .ai/rules/ignis',
            count($written),
            count($written) === 1 ? 'rule file' : 'rule files',
        ));
    }

    /**
     * Install skills for selected agents.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function installSkills(ConsoleIo $io): void
    {
        $skillsAgents = $this->agentsWithSkills();
        $skillsComposer = $this->skillComposer->config($this->buildGuidelineConfig());
        $skills = $skillsComposer->skills();
        $previouslyTrackedSkills = $this->config->getSkills();
        $invalidSkillNames = $this->skillParseFailures()->skillNames();
        $preservedSkillNames = array_values(array_intersect($previouslyTrackedSkills, $invalidSkillNames));
        $trackedSkillsToSync = array_values(array_diff($previouslyTrackedSkills, $preservedSkillNames));

        $this->installedSkillNames = array_values(array_unique([
            ...array_keys($skills->toArray()),
            ...$preservedSkillNames,
        ]));

        $this->installFeature(
            io: $io,
            agents: $skillsAgents,
            emptyMessage: 'No agents are selected for skill installation.',
            headerMessage: sprintf('Syncing %d skills for skills-capable agents', $skills->count()),
            nameResolver: fn(Agent $agent): string => $agent->displayName(),
            processor: function (Agent $agent) use ($skills, $trackedSkillsToSync): array {
                if (!$agent instanceof SupportsSkills) {
                    throw new RuntimeException(sprintf('Agent [%s] does not support skills.', $agent->name()));
                }

                $results = (new SkillWriter($agent))->sync($skills, $trackedSkillsToSync);
                $failedSkills = array_keys($results, SkillWriter::FAILED, true);

                $this->installedSkillNames = array_values(array_diff($this->installedSkillNames, $failedSkills));

                if ($failedSkills !== []) {
                    throw new RuntimeException('Failed to sync skills: ' . implode(', ', $failedSkills));
                }

                return $results;
            },
            featureName: 'skills',
            beforeProcess: $skills->isEmpty()
                ? null
                : function () use ($io, $skills): void {
                    $labels = array_map(
                        static fn(Skill $skill): string => $skill->displayName(),
                        $skills->toList(),
                    );
                    sort($labels);
                    $this->displayGrid($io, $labels);
                },
        );
    }

    /**
     * Build the guideline configuration for the current install run.
     *
     * @return \Crustum\Ignis\Install\GuidelineConfig
     */
    protected function buildGuidelineConfig(): GuidelineConfig
    {
        $guidelineConfig = new GuidelineConfig();
        $guidelineConfig->enforceTests = $this->enforceTests;
        $guidelineConfig->cakeStyle = true;
        $guidelineConfig->hasAnApi = false;
        $guidelineConfig->aiGuidelines = $this->selectedThirdPartyPackages->toList();
        $guidelineConfig->hasSkills = $this->selectedIgnisFeatures->contains('skills');
        $guidelineConfig->hasMcp = $this->selectedIgnisFeatures->contains('mcp')
            || ($this->isExplicitFlagMode() && $this->config->getMcp());

        return $guidelineConfig;
    }

    /**
     * Persist the selected install configuration to ignis.json.
     *
     * @return void
     */
    protected function storeConfig(): void
    {
        $explicitMode = $this->isExplicitFlagMode();

        if (!$explicitMode) {
            $this->config->flush();
            $this->config->setAgents(array_map(
                static fn(Agent $agent): string => $agent->name(),
                $this->selectedAgents->toList(),
            ));
            $this->config->setPackages($this->selectedThirdPartyPackages->toList());
        } elseif (
            $this->selectedIgnisFeatures->contains('guidelines')
            || $this->selectedIgnisFeatures->contains('skills')
        ) {
            $this->config->setPackages($this->selectedThirdPartyPackages->toList());
        }

        if ($this->selectedIgnisFeatures->contains('guidelines')) {
            $this->config->setGuidelines(true);
        }

        if ($this->selectedIgnisFeatures->contains('skills')) {
            $this->config->setSkills($this->installedSkillNames);
        }

        if ($this->selectedIgnisFeatures->contains('mcp')) {
            $this->config->setMcp(true);
        }
    }

    /**
     * Determine whether explicit feature flags were passed.
     *
     * @return bool
     */
    protected function isExplicitFlagMode(): bool
    {
        if (!$this->arguments instanceof Arguments) {
            return false;
        }

        if ($this->arguments->getBooleanOption('guidelines') === true) {
            return true;
        }

        if ($this->arguments->getBooleanOption('skills') === true) {
            return true;
        }

        return $this->arguments->getBooleanOption('mcp') === true;
    }

    /**
     * Install MCP server configuration for selected agents.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function installMcpServerConfig(ConsoleIo $io): void
    {
        $this->installFeature(
            io: $io,
            agents: $this->agentsWithMcp(),
            emptyMessage: 'No agents are selected for MCP installation.',
            headerMessage: 'Installing MCP servers to your selected Agents',
            nameResolver: fn(Agent $agent): string => $agent->displayName(),
            processor: fn(Agent $agent): int => (new McpWriter($agent))->write(),
            featureName: 'MCP servers',
            withDelay: true,
        );
    }

    /**
     * Return selected agents that support MCP.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Agents\Agent>
     */
    protected function agentsWithMcp(): Collection
    {
        return $this->selectedAgents;
    }

    /**
     * Return selected agents that support guidelines.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Agents\Agent>
     */
    protected function agentsWithGuidelines(): Collection
    {
        return $this->selectedAgents->filter(
            fn(Agent $agent): bool => $agent instanceof SupportsGuidelines,
        );
    }

    /**
     * Return selected agents that support skills.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Agents\Agent>
     */
    protected function agentsWithSkills(): Collection
    {
        return $this->selectedAgents->filter(fn(Agent $agent): bool => $agent instanceof SupportsSkills);
    }

    /**
     * Install a feature across selected agents with progress output.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Agents\Agent> $agents Selected agents
     * @param string $emptyMessage Message when no agents are selected
     * @param string $headerMessage Header message before installation
     * @param callable(\Crustum\Ignis\Install\Agents\Agent): string $nameResolver Agent display name resolver
     * @param callable(\Crustum\Ignis\Install\Agents\Agent): mixed $processor Installation callback
     * @param string $featureName Feature label for error output
     * @param callable|null $beforeProcess Optional pre-install callback
     * @param bool $withDelay Whether to pause before processing
     * @return void
     */
    protected function installFeature(
        ConsoleIo $io,
        Collection $agents,
        string $emptyMessage,
        string $headerMessage,
        callable $nameResolver,
        callable $processor,
        string $featureName,
        ?callable $beforeProcess = null,
        bool $withDelay = false,
    ): void {
        if ($agents->isEmpty()) {
            $io->info($emptyMessage);

            return;
        }

        $io->out('');
        $io->info($headerMessage);

        if ($beforeProcess !== null) {
            $beforeProcess();
        }

        $io->out('');

        if ($withDelay) {
            usleep(750000);
        }

        $failed = [];
        $longestName = 0;

        foreach ($agents as $agent) {
            $longestName = max($longestName, mb_strlen($nameResolver($agent)));
        }

        foreach ($agents->toList() as $agent) {
            $name = $nameResolver($agent);
            $io->out('  ' . str_pad($name, $longestName) . '... ', 0);

            try {
                $processor($agent);
                $io->out('OK');
            } catch (Exception $exception) {
                $failed[$name] = $exception->getMessage();
                $io->out('FAIL');
            }
        }

        if ($failed !== []) {
            $io->out('');
            $io->error(sprintf(
                'Failed to install %s to %d agent%s:',
                $featureName,
                count($failed),
                count($failed) === 1 ? '' : 's',
            ));

            foreach ($failed as $agentName => $error) {
                $io->out("  - {$agentName}: {$error}");
            }
        }

        $io->out('');
    }

    /**
     * Build the option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser = $this->configureIgnisOptionParser($parser);

        $parser
            ->addOption('guidelines', [
                'help' => 'Install AI guidelines',
                'boolean' => true,
            ])
            ->addOption('skills', [
                'help' => 'Install agent skills',
                'boolean' => true,
            ])
            ->addOption('mcp', [
                'help' => 'Install MCP server configuration',
                'boolean' => true,
            ])
            ->addOption('path', [
                'help' => 'Write guidelines/skills/MCP into this directory (discover packages from the CakePHP application). '
                    . 'Aborts when the target already has a .ai directory unless --force is set.',
                'short' => 'p',
            ])
            ->addOption('force', [
                'help' => 'With --path, allow installing into a directory that already contains .ai',
                'boolean' => true,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'ignis install';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Install CakePHP Ignis guidelines, skills, and MCP configuration';
    }
}
