<?php

declare(strict_types=1);

use Cake\Command\Command;
use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Ignis\Install\AgentsDetector;
use Crustum\Ignis\Support\Config;

beforeEach(function (): void {
    flushIgnisConfig();
    Configure::write('Ignis.enforce_tests', false);
});

afterEach(function (): void {
    flushIgnisConfig();
    Configure::delete('Ignis.enforce_tests');
});

it('does not throw when no agents are saved and none are auto-detected in non-interactive mode', function (): void {
    $config = new Config();

    $container = freshTestContainer();
    registerTestAgents($container);
    $detector = Mockery::mock(AgentsDetector::class);
    $detector->shouldReceive('getAgents')->andReturn(new Collection([]));
    $detector->shouldReceive('discoverSystemInstalledAgents')->andReturn([]);
    $detector->shouldReceive('discoverProjectInstalledAgents')->andReturn([]);

    $command = makeTestInstallCommand($config, $detector);

    expect(runInstallCommandNonInteractive($command, ['guidelines' => true, 'no-interaction' => true]))
        ->toBe(Command::CODE_SUCCESS);
});

it('silently drops stale agents no longer in the available list in non-interactive mode', function (): void {
    $config = new Config();
    $config->setAgents(['gemini']);

    $command = makeTestInstallCommand($config);

    expect(runInstallCommandNonInteractive($command, ['guidelines' => true, 'no-interaction' => true]))
        ->toBe(Command::CODE_SUCCESS);
});
