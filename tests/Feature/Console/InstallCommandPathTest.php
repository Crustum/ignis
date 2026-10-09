<?php

declare(strict_types=1);

use Cake\Command\Command;
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
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;
use JMac\Testing\Double;

beforeEach(function (): void {
    flushIgnisConfig();
    Configure::write('Ignis.enforce_tests', false);
});

afterEach(function (): void {
    flushIgnisConfig();
    Configure::delete('Ignis.enforce_tests');
});

/**
 * @param \Crustum\Ignis\Support\Config $config Config
 * @return \Crustum\Ignis\Command\InstallCommand
 */
function makePathAwareInstallCommand(Config $config): InstallCommand
{
    $container = freshTestContainer();
    registerTestAgents($container);
    $detector = new AgentsDetector($container, new IgnisManager());
    $guidelineComposer = Double::for(GuidelineComposer::class);
    $skillComposer = Double::for(SkillComposer::class);
    $ruleRepository = Double::for(RuleRepository::class);
    $project = Double::for(ProjectManager::class, override: true);
    mockProjectPackages($project, new PackageCollection([]));

    return new class($detector, $config, $guidelineComposer, $skillComposer, $ruleRepository, $project->instance()) extends InstallCommand {
        protected function displayIgnisHeader(ConsoleIo $io, string $featureName, string $projectName): void
        {
        }

        protected function performInstallation(ConsoleIo $io): void
        {
        }

        protected function outro(ConsoleIo $io): void
        {
        }

        protected function noteInferConventions(ConsoleIo $io): void
        {
        }
    };
}

it('aborts --path when the target already has .ai without --force', function (): void {
    $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ignis-install-path-' . uniqid();
    mkdir($target . DIRECTORY_SEPARATOR . '.ai', 0777, true);
    $previous = ProjectRoot::override();

    try {
        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();
        $io = new ConsoleIo($out, $err);
        $command = makePathAwareInstallCommand(new Config());
        $args = new Arguments([], [
            'path' => $target,
            'guidelines' => true,
            'no-interaction' => true,
        ], []);

        expect($command->execute($args, $io))->toBe(Command::CODE_ERROR);
        expect(implode("\n", $err->messages()))->toContain('.ai');
        expect(ProjectRoot::override())->toBe($previous);
    } finally {
        ProjectRoot::set($previous);
        rmdir($target . DIRECTORY_SEPARATOR . '.ai');
        rmdir($target);
    }
});

it('accepts --path with --force when .ai already exists', function (): void {
    $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ignis-install-path-' . uniqid();
    mkdir($target . DIRECTORY_SEPARATOR . '.ai', 0777, true);
    $previous = ProjectRoot::override();

    try {
        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();
        $io = new ConsoleIo($out, $err);
        $command = makePathAwareInstallCommand(new Config());
        $args = new Arguments([], [
            'path' => $target,
            'force' => true,
            'guidelines' => true,
            'no-interaction' => true,
        ], []);

        expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS);
        expect(ProjectRoot::override())->toBe($previous);
    } finally {
        ProjectRoot::set($previous);
        rmdir($target . DIRECTORY_SEPARATOR . '.ai');
        rmdir($target);
    }
});

it('does not run the .ai conflict guard without --path', function (): void {
    $config = new Config();
    $container = freshTestContainer();
    registerTestAgents($container);
    $detector = Double::for(AgentsDetector::class);
    $detector->allows('getAgents')->returns(new Collection([]));
    $detector->allows('discoverSystemInstalledAgents')->returns([]);
    $detector->allows('discoverProjectInstalledAgents')->returns([]);

    $command = makeTestInstallCommand($config, $detector);

    expect(runInstallCommandNonInteractive($command, ['guidelines' => true, 'no-interaction' => true]))
        ->toBe(Command::CODE_SUCCESS);
});
