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
use Crustum\Ignis\Skills\Remote\AuditResult;
use Crustum\Ignis\Skills\Remote\GitHubRepository;
use Crustum\Ignis\Skills\Remote\GitHubSkillProvider;
use Crustum\Ignis\Skills\Remote\RemoteSkill;
use Crustum\Ignis\Skills\Remote\SkillAuditor;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Ignis\Trait\ConsolePromptTrait;
use InvalidArgumentException;
use Override;
use RuntimeException;

/**
 * Downloads and installs skills from a remote GitHub repository.
 */
class AddSkillCommand extends Command
{
    use ConsolePromptTrait;

    /**
     * Parsed GitHub repository.
     */
    protected GitHubRepository $repository;

    /**
     * Remote skill provider.
     */
    protected GitHubSkillProvider $fetcher;

    /**
     * Discovered remote skills keyed by skill name.
     *
     * @var \Cake\Collection\Collection<string, \Crustum\Ignis\Skills\Remote\RemoteSkill>
     */
    protected Collection $availableSkills;

    /**
     * Relative project skills directory.
     */
    protected string $defaultSkillsPath = '.ai' . DS . 'skills';

    /**
     * Optional skill provider injected for testing.
     */
    protected ?GitHubSkillProvider $skillProvider = null;

    /**
     * Optional skill auditor injected for testing.
     */
    protected ?SkillAuditor $skillAuditor = null;

    /**
     * Constructor.
     *
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory
     * @param \Crustum\Ignis\Skills\Remote\GitHubSkillProvider|null $skillProvider Optional skill provider
     * @param \Crustum\Ignis\Skills\Remote\SkillAuditor|null $skillAuditor Optional skill auditor
     */
    public function __construct(
        ?CommandFactoryInterface $factory = null,
        ?GitHubSkillProvider $skillProvider = null,
        ?SkillAuditor $skillAuditor = null,
    ) {
        parent::__construct($factory);

        $this->skillProvider = $skillProvider;
        $this->skillAuditor = $skillAuditor;
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
        $this->displayHeader($io);

        if (!$this->initializeRepository($args, $io)) {
            return static::CODE_ERROR;
        }

        if (!$this->discoverAvailableSkills($io)) {
            return static::CODE_ERROR;
        }

        return $this->handleAction($args, $io);
    }

    /**
     * Parse and validate the GitHub repository input.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return bool
     */
    protected function initializeRepository(Arguments $args, ConsoleIo $io): bool
    {
        $repository = $this->parseRepository($args, $io);

        if (!$repository instanceof GitHubRepository) {
            return false;
        }

        $this->repository = $repository;
        $this->fetcher = $this->skillProvider ?? new GitHubSkillProvider($this->repository);

        return true;
    }

    /**
     * Discover skills from the configured repository.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return bool
     */
    protected function discoverAvailableSkills(ConsoleIo $io): bool
    {
        try {
            $this->availableSkills = $this->promptSpin(
                $io,
                fn(): Collection => $this->fetcher->discoverSkills(),
                "Fetching skills from {$this->repository->source()}...",
            );
        } catch (RuntimeException $runtimeException) {
            $io->error($runtimeException->getMessage());

            return false;
        }

        if ($this->availableSkills->isEmpty()) {
            $io->error('No valid skills are found in the repository.');

            return false;
        }

        return true;
    }

    /**
     * Route to list or install actions.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int|null
     */
    protected function handleAction(Arguments $args, ConsoleIo $io): ?int
    {
        if ($args->getBooleanOption('list') === true) {
            return $this->displaySkillsTable($io);
        }

        return $this->installSkills($args, $io);
    }

    /**
     * Parse repository input from argument or prompt.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return \Crustum\Ignis\Skills\Remote\GitHubRepository|null
     */
    protected function parseRepository(Arguments $args, ConsoleIo $io): ?GitHubRepository
    {
        $input = $args->getArgument('repo');

        if (!is_string($input) || $input === '') {
            if (!$this->isInteractive($args, $io)) {
                $io->error('Repository argument is required in non-interactive mode.');

                return null;
            }

            $input = $this->promptText(
                $io,
                'Which GitHub repository would you like to fetch skills from?',
                'e.g., vercel-labs/agent-skills or https://github.com/owner/repo',
                function (string $value): ?string {
                    try {
                        GitHubRepository::fromInput($value);

                        return null;
                    } catch (InvalidArgumentException $invalidArgumentException) {
                        return $invalidArgumentException->getMessage();
                    }
                },
            );
        }

        try {
            return GitHubRepository::fromInput($input);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $io->error($invalidArgumentException->getMessage());

            return null;
        }
    }

    /**
     * Render the command header.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function displayHeader(ConsoleIo $io): void
    {
        $projectName = (string)Configure::read('App.name', 'Application');
        $this->displayIgnisHeader($io, 'Skill', $projectName);
    }

    /**
     * Render available skills.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int|null
     */
    protected function displaySkillsTable(ConsoleIo $io): ?int
    {
        $this->displayNote(
            $io,
            "Found {$this->availableSkills->count()} available skills",
        );
        $this->displayGrid($io, array_keys($this->availableSkills->toArray()));

        return static::CODE_SUCCESS;
    }

    /**
     * Install selected skills from the repository.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int|null
     */
    protected function installSkills(Arguments $args, ConsoleIo $io): ?int
    {
        $selectedSkills = $this->selectSkills($args, $io);

        if ($selectedSkills->isEmpty()) {
            $io->warning('No skills are selected.');

            return static::CODE_SUCCESS;
        }

        $skillsToInstall = $this->skillsToInstall($selectedSkills, $args, $io);

        if ($skillsToInstall->isEmpty()) {
            return static::CODE_SUCCESS;
        }

        $auditAllowed = $this->runAuditBeforeInstall($skillsToInstall, $args, $io);

        if ($auditAllowed === null) {
            return static::CODE_ERROR;
        }

        if ($auditAllowed === false) {
            return static::CODE_SUCCESS;
        }

        $results = $this->promptSpin(
            $io,
            fn(): array => $this->addSkills($skillsToInstall),
            'Downloading skills...',
        );

        if ($results['installedNames'] !== []) {
            $io->success('Skills installed:');
            $this->displayGrid($io, $results['installedNames']);
            $this->runIgnisUpdate($io);
            $this->showOutro($io);
        }

        if ($results['failedDetails'] !== []) {
            $io->error('Some skills failed to install:');
            $this->displayGrid($io, array_keys($results['failedDetails']));
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Select skills to install from flags or prompt.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Skills\Remote\RemoteSkill>
     */
    protected function selectSkills(Arguments $args, ConsoleIo $io): Collection
    {
        if ($args->getBooleanOption('all') === true) {
            return $this->availableSkills;
        }

        $skillOptions = $args->getArrayOption('skill') ?? [];

        if ($skillOptions !== []) {
            return $this->availableSkills->filter(
                fn(RemoteSkill $skill, string $name): bool => in_array($name, $skillOptions, true),
            );
        }

        if (!$this->isInteractive($args, $io)) {
            $io->error('Use --all or --skill to select skills in non-interactive mode.');

            return new Collection([]);
        }

        $options = $this->availableSkills
            ->map(fn(RemoteSkill $skill, string $name): array => [$name => $name])
            ->reduce(fn(array $carry, array $item): array => array_merge($carry, $item), []);

        $selected = $this->promptMultiselect(
            $io,
            'Which skills would you like to install?',
            $options,
            [],
            true,
            'Use --all to install all skills at once',
        );

        return $this->availableSkills->filter(
            fn(RemoteSkill $skill, string $name): bool => in_array($name, $selected, true),
        );
    }

    /**
     * Filter out existing skills unless overwrite is confirmed.
     *
     * @param \Cake\Collection\Collection<string, \Crustum\Ignis\Skills\Remote\RemoteSkill> $skills Selected skills
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Skills\Remote\RemoteSkill>
     */
    protected function skillsToInstall(Collection $skills, Arguments $args, ConsoleIo $io): Collection
    {
        $existingSkills = $skills->filter(fn(RemoteSkill $skill): bool => $this->skillExists($skill));
        $newSkills = $skills->filter(fn(RemoteSkill $skill): bool => !$this->skillExists($skill));

        if ($existingSkills->isEmpty() || $this->shouldUpdateExisting($existingSkills, $args, $io)) {
            return $skills;
        }

        return $newSkills;
    }

    /**
     * Determine whether existing skills should be overwritten.
     *
     * @param \Cake\Collection\Collection<string, \Crustum\Ignis\Skills\Remote\RemoteSkill> $existingSkills Existing skills
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return bool
     */
    protected function shouldUpdateExisting(Collection $existingSkills, Arguments $args, ConsoleIo $io): bool
    {
        if ($args->getBooleanOption('force') === true) {
            return true;
        }

        if (!$this->isInteractive($args, $io)) {
            return false;
        }

        return $this->promptConfirm($io, "Update {$existingSkills->count()} existing skill(s)?");
    }

    /**
     * Determine whether a skill directory already exists.
     *
     * @param \Crustum\Ignis\Skills\Remote\RemoteSkill $skill Remote skill
     * @return bool
     */
    protected function skillExists(RemoteSkill $skill): bool
    {
        return is_dir($this->skillTargetPath($skill));
    }

    /**
     * Resolve the local install path for a skill.
     *
     * @param \Crustum\Ignis\Skills\Remote\RemoteSkill $skill Remote skill
     * @return string
     */
    protected function skillTargetPath(RemoteSkill $skill): string
    {
        return ProjectRoot::path() . DS . $this->defaultSkillsPath . DS . $skill->name;
    }

    /**
     * Download and install skills to the project.
     *
     * @param \Cake\Collection\Collection<string, \Crustum\Ignis\Skills\Remote\RemoteSkill> $skills Skills to install
     * @return array{installedNames: array<int, string>, failedDetails: array<string, string>}
     */
    protected function addSkills(Collection $skills): array
    {
        $results = ['installedNames' => [], 'failedDetails' => []];

        foreach ($skills as $skill) {
            $targetPath = $this->skillTargetPath($skill);

            if ($this->skillExists($skill)) {
                $this->deleteDirectory($targetPath);
            }

            try {
                if ($this->fetcher->downloadSkill($skill, $targetPath)) {
                    $results['installedNames'][] = $skill->name;
                } else {
                    $results['failedDetails'][$skill->name] = 'Download failed';
                }
            } catch (RuntimeException $exception) {
                $results['failedDetails'][$skill->name] = $exception->getMessage();
            }
        }

        return $results;
    }

    /**
     * Run security audit before installing skills.
     *
     * Returns true to continue, false when the user declines a risky install,
     * or null when no auditor is configured (caller should exit with an error).
     *
     * @param \Cake\Collection\Collection<string, \Crustum\Ignis\Skills\Remote\RemoteSkill> $selectedSkills Selected skills
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return bool|null
     */
    protected function runAuditBeforeInstall(Collection $selectedSkills, Arguments $args, ConsoleIo $io): ?bool
    {
        if ($args->getBooleanOption('skip-audit') === true) {
            return true;
        }

        $auditor = $this->skillAuditor ?? new SkillAuditor();

        if (!$auditor->isConfigured()) {
            $io->warning(
                'No remote skill auditor is configured (Ignis.hosted.audit_url / IGNIS_HOSTED_AUDIT_URL).',
            );
            $io->warning(
                'Set an audit endpoint, or re-run with --skip-audit to install without an audit.',
            );

            return null;
        }

        $skillNames = array_keys($selectedSkills->toArray());

        $auditResults = $this->promptSpin(
            $io,
            fn(): array => $auditor->audit(
                $this->repository->source(),
                $skillNames,
            ),
            'Running security audit...',
        );

        if (!$this->hasRiskySkills($auditResults)) {
            return true;
        }

        $this->displayAuditResults($io, $auditResults, $skillNames);

        if (!$this->isInteractive($args, $io)) {
            return true;
        }

        return $this->promptConfirm($io, 'Do you want to install these skills?');
    }

    /**
     * Render audit results in a table.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param array<string, array<int, \Crustum\Ignis\Skills\Remote\AuditResult>> $auditResults Audit results
     * @param array<int, string> $skillNames Skill names
     * @return void
     */
    protected function displayAuditResults(ConsoleIo $io, array $auditResults, array $skillNames): void
    {
        $partnerKeys = (new Collection($auditResults))
            ->map(fn(array $results): array => array_map(
                fn(AuditResult $auditResult): string => $auditResult->partner,
                $results,
            ))
            ->reduce(fn(array $carry, array $partners): array => array_merge($carry, $partners), []);

        $partnerKeys = array_values(array_unique($partnerKeys));

        $headers = array_merge(['Skill'], array_map(ucfirst(...), $partnerKeys));
        $rows = [];

        foreach ($skillNames as $skillName) {
            $partnerResults = $auditResults[$skillName] ?? [];
            $partnerMap = (new Collection($partnerResults))
                ->indexBy(fn(AuditResult $auditResult): string => $auditResult->partner)
                ->toArray();

            $row = [$skillName];

            foreach ($partnerKeys as $partnerKey) {
                $row[] = isset($partnerMap[$partnerKey])
                    ? $this->colorizeRisk($io, $partnerMap[$partnerKey])
                    : '—';
            }

            $rows[] = $row;
        }

        $io->out('');
        $this->displayNote($io, 'Security Audit');
        $this->displayTable($io, $headers, $rows);
    }

    /**
     * Determine whether any audit results exceed the risk threshold.
     *
     * @param array<string, array<int, \Crustum\Ignis\Skills\Remote\AuditResult>> $auditResults Audit results
     * @return bool
     */
    protected function hasRiskySkills(array $auditResults): bool
    {
        foreach ($auditResults as $results) {
            foreach ($results as $result) {
                if ($result->risk->weight() >= 3) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Colorize a risk label for console output.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param \Crustum\Ignis\Skills\Remote\AuditResult $result Audit result
     * @return string
     */
    protected function colorizeRisk(ConsoleIo $io, AuditResult $result): string
    {
        return match ($result->risk->color()) {
            'red' => '<error>' . $result->risk->label() . '</error>',
            'yellow' => '<warning>' . $result->risk->label() . '</warning>',
            'green' => '<success>' . $result->risk->label() . '</success>',
            default => '<comment>' . $result->risk->label() . '</comment>',
        };
    }

    /**
     * Run ignis update after installing skills.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function runIgnisUpdate(ConsoleIo $io): void
    {
        $this->executeCommand(UpdateCommand::class, ['--no-interaction'], $io);
    }

    /**
     * Render the command outro banner.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function showOutro(ConsoleIo $io): void
    {
        $this->displayOutro($io, 'Enjoy the ignis');
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $path Directory path
     * @return void
     */
    protected function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $target = $path . DS . $item;

            if (is_dir($target)) {
                $this->deleteDirectory($target);
            } else {
                unlink($target);
            }
        }

        rmdir($path);
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
            ->addArgument('repo', [
                'help' => 'GitHub repository (owner/repo or full URL)',
                'required' => false,
            ])
            ->addOption('list', [
                'help' => 'List available skills',
                'boolean' => true,
            ])
            ->addOption('all', [
                'help' => 'Install all skills',
                'boolean' => true,
            ])
            ->addOption('skill', [
                'help' => 'Specific skills to install',
                'multiple' => true,
            ])
            ->addOption('force', [
                'help' => 'Overwrite existing skills',
                'boolean' => true,
            ])
            ->addOption('skip-audit', [
                'help' => 'Skip security audit',
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
        return 'ignis add-skill';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Add skills from a remote GitHub repository';
    }
}
