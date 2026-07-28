<?php

declare(strict_types=1);

use Cake\Collection\Collection;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Crustum\Ignis\IgnisManager;
use Crustum\Ignis\Command\InstallCommand;
use Crustum\Ignis\Install\AgentsDetector;
use Crustum\Ignis\Install\GuidelineComposer;
use Crustum\Ignis\Install\SkillComposer;
use Crustum\Ignis\Rules\RuleRepository;
use Crustum\Ignis\Support\Config;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;

beforeEach(function (): void {
    useTestApp();
    flushIgnisConfig();

    Configure::write('Ignis.enforce_tests', false);
    Configure::write('Ignis.rules.enabled', true);
    Configure::write('Ignis.rules.scoped_guidelines', true);
    Configure::write('Ignis.agents.claude_code.guidelines_path', base_path('CLAUDE.md'));

    $this->project = Mockery::mock(ProjectManager::class);
    mockProjectPackages($this->project, new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]));

    $this->config = new Config();
    $this->config->setAgents(['claude_code']);
    $this->config->setGuidelines(true);

    $this->out = new StubConsoleOutput();
    $this->err = new StubConsoleOutput();
    $this->io = new ConsoleIo($this->out, $this->err);
});

afterEach(function (): void {
    flushIgnisConfig();
    Configure::delete('Ignis.enforce_tests');
    Configure::delete('Ignis.rules.enabled');
    Configure::delete('Ignis.rules.scoped_guidelines');
    Configure::delete('Ignis.agents.claude_code.guidelines_path');
    resetTestApp();
    Mockery::close();
});

/**
 * Build an InstallCommand that runs real guideline + rule sync for tests.
 *
 * @param \Crustum\Ignis\Support\Config $config Ignis config
 * @param \Crustum\Inspector\ProjectManager $project Project manager
 * @param \Crustum\Ignis\Rules\RuleRepository|null $ruleRepository Optional rule repository
 * @return \Crustum\Ignis\Command\InstallCommand
 */
function makeRulesEndToEndInstallCommand(
    Config $config,
    ProjectManager $project,
    ?RuleRepository $ruleRepository = null,
): InstallCommand {
    $container = freshTestContainer();
    registerTestAgents($container);
    $detector = new AgentsDetector($container, new IgnisManager());
    $guidelineComposer = new GuidelineComposer($project);
    $skillComposer = Mockery::mock(SkillComposer::class);
    $ruleRepository ??= new RuleRepository(ProjectRoot::path() . DS . '.ai' . DS . 'rules');

    return new class($detector, $config, $guidelineComposer, $skillComposer, $ruleRepository, $project) extends InstallCommand {
        protected function displayIgnisHeader(ConsoleIo $io, string $featureName, string $projectName): void
        {
        }

        protected function outro(ConsoleIo $io): void
        {
        }

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
            parent::installFeature(
                $io,
                $agents,
                $emptyMessage,
                $headerMessage,
                $nameResolver,
                $processor,
                $featureName,
                $beforeProcess,
            );
        }
    };
}

/**
 * Run non-interactive guidelines install.
 *
 * @param \Crustum\Ignis\Command\InstallCommand $command Install command
 * @return int|null
 */
function runRulesEndToEndInstall(InstallCommand $command): ?int
{
    $out = new StubConsoleOutput();
    $err = new StubConsoleOutput();
    $io = new ConsoleIo($out, $err);
    $args = new Arguments([], [
        'guidelines' => true,
        'no-interaction' => true,
    ], []);

    return $command->execute($args, $io);
}

it('extracts path-scoped rules into .ai/rules/ignis when running ignis install with rules enabled', function (): void {
    Configure::write('Ignis.rules.enabled', true);

    $command = makeRulesEndToEndInstallCommand($this->config, $this->project);
    expect(runRulesEndToEndInstall($command))->toBe(0);

    $managedDir = base_path('.ai/rules/ignis');
    expect(is_dir($managedDir))->toBeTrue();

    $managed = glob($managedDir . '/*.md') ?: [];
    expect($managed)->not->toBeEmpty();

    $testsFile = null;
    foreach ($managed as $path) {
        $contents = (string)file_get_contents($path);
        if (str_contains($contents, 'tests/**')) {
            $testsFile = $path;
            break;
        }
    }

    expect($testsFile)->not->toBeNull()
        ->and((string)file_get_contents((string)$testsFile))
        ->toContain('paths:')
        ->toContain('Pest');

    $claude = (string)file_get_contents(base_path('CLAUDE.md'));

    expect($claude)
        ->not->toContain('This project uses Pest for testing')
        ->toContain('.ai/rules/index.md');
});

it('re-inlines everything and removes the managed directory when rules are disabled', function (): void {
    Configure::write('Ignis.rules.enabled', true);

    $command = makeRulesEndToEndInstallCommand($this->config, $this->project);
    expect(runRulesEndToEndInstall($command))->toBe(0);
    expect(is_dir(base_path('.ai/rules/ignis')))->toBeTrue();

    Configure::write('Ignis.rules.enabled', false);

    $command = makeRulesEndToEndInstallCommand($this->config, $this->project);
    expect(runRulesEndToEndInstall($command))->toBe(0);

    expect(is_dir(base_path('.ai/rules/ignis')))->toBeFalse();

    $claude = (string)file_get_contents(base_path('CLAUDE.md'));

    expect($claude)
        ->toContain('Pest')
        ->toContain('This project uses Pest for testing');
});

it('falls back to inlining scoped content with a warning when rule syncing fails', function (): void {
    Configure::write('Ignis.rules.enabled', true);

    $ruleRepository = Mockery::mock(RuleRepository::class);
    $ruleRepository->shouldReceive('syncManaged')->andThrow(new RuntimeException('disk full'));
    $ruleRepository->shouldReceive('clearManaged')->andReturn(false);

    $command = makeRulesEndToEndInstallCommand($this->config, $this->project, $ruleRepository);
    expect(runRulesEndToEndInstall($command))->toBe(0);

    expect(is_dir(base_path('.ai/rules/ignis')))->toBeFalse();

    $claude = (string)file_get_contents(base_path('CLAUDE.md'));

    expect($claude)
        ->toContain('Pest')
        ->toContain('This project uses Pest for testing');
});

it('aborts instead of re-inlining when both rule syncing and cleanup fail', function (): void {
    Configure::write('Ignis.rules.enabled', true);

    $ruleRepository = Mockery::mock(RuleRepository::class);
    $ruleRepository->shouldReceive('syncManaged')->andThrow(new RuntimeException('disk full'));
    $ruleRepository->shouldReceive('clearManaged')->andThrow(new RuntimeException('locked directory'));

    $command = makeRulesEndToEndInstallCommand($this->config, $this->project, $ruleRepository);

    expect(fn(): ?int => runRulesEndToEndInstall($command))
        ->toThrow(RuntimeException::class, 'could not clear .ai/rules/ignis');
});
