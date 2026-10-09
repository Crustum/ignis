<?php
declare(strict_types=1);

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
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

if (!function_exists('flushIgnisConfig')) {
    /**
     * Remove project ignis.json install state.
     *
     * @return void
     */
    function flushIgnisConfig(): void
    {
        (new Config())->flush();
    }
}

if (!function_exists('makeTestInstallCommand')) {
    /**
     * Build an install command stub that skips side effects.
     *
     * @param \Crustum\Ignis\Support\Config $config Ignis install config
     * @param \Crustum\Ignis\Install\AgentsDetector|null $detector Optional agent detector
     * @return \Crustum\Ignis\Command\InstallCommand
     */
    function makeTestInstallCommand(Config $config, ?AgentsDetector $detector = null): InstallCommand
    {
        $container = freshTestContainer();
        registerTestAgents($container);
        $detector ??= new AgentsDetector($container, new IgnisManager());
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
        };
    }
}

if (!function_exists('buildToolExecutorSubprocessCommand')) {
    /**
     * Build a subprocess command that bootstraps the test app and runs execute-tool.
     *
     * @param string $toolClass Tool class name
     * @param array<string, mixed> $arguments Tool arguments
     * @return array<int, string>
     */
    function buildToolExecutorSubprocessCommand(string $toolClass, array $arguments): array
    {
        $root = str_replace('\\', '/', ROOT);
        $encoded = base64_encode(json_encode($arguments));
        $toolClassEscaped = addslashes($toolClass);

        $script = <<<PHP
chdir('{$root}');
require '{$root}/tests/bootstrap.php';
use Crustum\\Ignis\\Command\\ExecuteToolCommand;
use Cake\\Console\\Arguments;
use Cake\\Console\\ConsoleIo;
use Cake\\Console\\TestSuite\\StubConsoleOutput;
use TestApp\\Application;
\$app = new Application('{$root}/tests/TestApp/config');
\$app->bootstrap();
\$container = \$app->getContainer();
\$command = \$container->get(ExecuteToolCommand::class);
\$args = new Arguments(
    ['{$toolClassEscaped}', '{$encoded}'],
    [],
    ['tool', 'arguments'],
);
\$stdout = new StubConsoleOutput();
\$stderr = new StubConsoleOutput();
\$exitCode = \$command->execute(\$args, new ConsoleIo(\$stdout, \$stderr));
foreach (\$stdout->messages() as \$message) {
    echo \$message;
}
exit(\$exitCode ?? 0);
PHP;

        return [PHP_BINARY, '-r', $script];
    }
}

if (!function_exists('silentConsoleIo')) {
    /**
     * Build a console IO instance that discards output.
     *
     * @return \Cake\Console\ConsoleIo
     */
    function silentConsoleIo(): ConsoleIo
    {
        return new ConsoleIo(new StubConsoleOutput(), new StubConsoleOutput());
    }
}

if (!function_exists('runInstallCommandNonInteractive')) {
    /**
     * Run the install command stub in non-interactive mode.
     *
     * @param \Crustum\Ignis\Command\InstallCommand $command Install command
     * @param array<string, mixed> $options Command options
     * @return int|null
     */
    function runInstallCommandNonInteractive(InstallCommand $command, array $options = []): ?int
    {
        $args = new Arguments([], $options, array_keys($options));
        $io = silentConsoleIo();

        return $command->execute($args, $io);
    }
}

if (!function_exists('prepareConsoleProjectRoot')) {
    /**
     * Point ProjectRoot at the isolated test application directory.
     *
     * @return void
     */
    function prepareConsoleProjectRoot(): void
    {
        useTestApp();
    }
}

if (!function_exists('resetConsoleProjectRoot')) {
    /**
     * Reset the project root override after console feature tests.
     *
     * @return void
     */
    function resetConsoleProjectRoot(): void
    {
        resetTestApp();
    }
}

if (!function_exists('isPreferLowestCi')) {
    /**
     * Whether this Pest run is the prefer-lowest CI job.
     *
     * @return bool
     */
    function isPreferLowestCi(): bool
    {
        return filter_var(getenv('IGNIS_PREFER_LOWEST') ?: '0', FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('preferLowestPromptsStdoutSkipReason')) {
    /**
     * Skip reason for Cake stdout asserts that depend on Prompts ConsoleIo fallbacks.
     *
     * @return string
     */
    function preferLowestPromptsStdoutSkipReason(): string
    {
        return 'Prompts TTY Note/Table output is not in Cake stdout on prefer-lowest Linux CI';
    }
}
