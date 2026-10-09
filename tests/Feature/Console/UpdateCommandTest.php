<?php

declare(strict_types=1);

use Cake\Collection\Collection;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Crustum\Ignis\Command\InstallCommand;
use Crustum\Ignis\Install\ThirdPartyPackage;
use Crustum\Ignis\Support\Config;
use Crustum\Ignis\Support\SkillParseFailures;
use Crustum\Ignis\Test\Fixtures\TestableUpdateCommand;

it('shows an error when ignis.json does not exist', function (): void {
    $this->exec('ignis update');
    $this->assertExitError();
    $this->assertErrorContains('php bin/cake.php ignis install');
});

it('shows an error when ignis.json contains invalid json', function (): void {
    file_put_contents(base_path('ignis.json'), 'invalid json {{{');

    $this->exec('ignis update');

    $this->assertExitError();
    $this->assertErrorContains('php bin/cake.php ignis install');
});

it('shows an error when agents are empty', function (): void {
    $config = new Config();
    $config->setGuidelines(true);

    $this->exec('ignis update');

    $this->assertExitError();
    $this->assertErrorContains('php bin/cake.php ignis install');
});

it('exits silently when only mcp is configured and no agents are stored', function (): void {
    $config = new Config();
    $config->setMcp(true);

    $this->exec('ignis update');

    $this->assertExitSuccess();
    $this->assertOutputNotContains('Please set up Ignis with [php bin/cake.php ignis install] first.');
});

it('exits silently when no guidelines and no skills are configured', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(false);
    $config->setSkills([]);

    $this->exec('ignis update --no-interaction');

    $this->assertExitSuccess();
    $this->assertOutputNotContains('Ignis guidelines and skills updated successfully.');
});

it('calls install command with a guidelines flag when guidelines are enabled', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(true);
    $config->setSkills([]);

    $command = new TestableUpdateCommand($config);
    $args = new Arguments([], ['no-discover' => true], ['no-discover']);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->executeCommandCalls)->toBe(1)
        ->and($command->executeCommandClass)->toBe(InstallCommand::class)
        ->and($command->executeCommandArgs)->toBe(['--no-interaction', '--guidelines']);
});

it('calls install command with skills flag when skills are configured', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(false);
    $config->setSkills(['test-skill']);

    $command = new TestableUpdateCommand($config);
    $args = new Arguments([], ['no-discover' => true], ['no-discover']);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->executeCommandCalls)->toBe(1)
        ->and($command->executeCommandClass)->toBe(InstallCommand::class)
        ->and($command->executeCommandArgs)->toBe(['--no-interaction', '--skills']);
});

it('calls install command with both flags when guidelines and skills are enabled', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(true);
    $config->setSkills(['test-skill']);

    $command = new TestableUpdateCommand($config);
    $args = new Arguments([], ['no-discover' => true], ['no-discover']);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->executeCommandCalls)->toBe(1)
        ->and($command->executeCommandClass)->toBe(InstallCommand::class)
        ->and($command->executeCommandArgs)->toBe(['--no-interaction', '--guidelines', '--skills']);
});

it('does not pass mcp flag to install command even when mcp is configured', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(true);
    $config->setMcp(true);

    $command = new TestableUpdateCommand($config);
    $args = new Arguments([], ['no-discover' => true], ['no-discover']);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->executeCommandCalls)->toBe(1)
        ->and($command->executeCommandClass)->toBe(InstallCommand::class)
        ->and($command->executeCommandArgs)->toBe(['--no-interaction', '--guidelines']);
});

it('calls install command with skills flag when .ai/skills directory exists but skills are not in config', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(false);

    ensureDirectoryExists(base_path('.ai/skills'));

    $command = new TestableUpdateCommand($config);
    $args = new Arguments([], ['no-discover' => true], ['no-discover']);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->executeCommandCalls)->toBe(1)
        ->and($command->executeCommandClass)->toBe(InstallCommand::class)
        ->and($command->executeCommandArgs)->toBe(['--no-interaction', '--skills']);
});

it('does not run discovery when --no-discover flag is set', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setSkills(['existing-skill']);

    $command = new TestableUpdateCommand($config);
    $args = new Arguments([], ['no-discover' => true], ['no-discover']);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->discoverNewContentCalled)->toBeFalse()
        ->and($command->executeCommandCalls)->toBe(1)
        ->and($command->executeCommandClass)->toBe(InstallCommand::class)
        ->and($command->executeCommandArgs)->toBe(['--no-interaction', '--skills'])
        ->and($config->getSkills())->toBe(['existing-skill']);
});

it('runs discovery by default when --no-discover is not set', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setSkills(['existing-skill']);

    $command = new TestableUpdateCommand($config);
    $args = new Arguments([], [], []);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->discoverNewContentCalled)->toBeTrue()
        ->and($command->executeCommandCalls)->toBe(1)
        ->and($config->getSkills())->toBe(['existing-skill']);
});

it('does not change config when no new packages are found during discovery', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(true);
    $config->setSkills(['existing-skill']);

    $command = new TestableUpdateCommand($config);
    $command->resolvedNewPackages = new Collection([]);

    $args = new Arguments([], [], []);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->discoverNewContentCalled)->toBeTrue()
        ->and($config->getPackages())->toBe([]);
});

it('reports skill parse failures recorded during the install run', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(false);
    $config->setSkills(['broken-frontmatter']);

    $failures = new SkillParseFailures();
    $container = freshTestContainer();
    bindInstance($container, SkillParseFailures::class, $failures);
    Configure::write('app.container', $container);

    $command = new class($config) extends TestableUpdateCommand {
        #[\Override]
        public function executeCommand(CommandInterface|string $command, array $args = [], ?ConsoleIo $io = null): ?int
        {
            $container = Configure::read('app.container');
            $container->get(SkillParseFailures::class)->record(
                testAppPath('.ai/skills/broken-frontmatter/SKILL.md'),
                'A colon cannot be used in an unquoted mapping value',
            );

            return parent::executeCommand($command, $args, $io);
        }
    };

    $args = new Arguments([], ['no-discover' => true], ['no-discover']);
    $out = new StubConsoleOutput();

    try {
        expect($command->execute($args, new ConsoleIo($out, $out)))->toBe(Command::CODE_SUCCESS);

        $output = implode("\n", $out->messages());

        expect($output)
            ->toContain('Skipped 1 skill with invalid or incomplete frontmatter, leaving existing registration unchanged:')
            ->toContain('- broken-frontmatter (.ai/skills/broken-frontmatter/SKILL.md): A colon cannot be used in an unquoted mapping value')
            ->toContain('Ignis guidelines and skills updated successfully.');
    } finally {
        Configure::delete('app.container');
    }
});

it('skips skills when --ignore-skills flag is set even if skills are configured', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(true);
    $config->setSkills(['test-skill']);

    $command = new TestableUpdateCommand($config);
    $args = new Arguments([], ['ignore-skills' => true, 'no-discover' => true], ['ignore-skills', 'no-discover']);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->executeCommandCalls)->toBe(1)
        ->and($command->executeCommandClass)->toBe(InstallCommand::class)
        ->and($command->executeCommandArgs)->toBe(['--no-interaction', '--guidelines']);
});

it('skips skills when --ignore-skills flag is set even if .ai/skills directory exists', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(true);

    ensureDirectoryExists(base_path('.ai/skills'));

    $command = new TestableUpdateCommand($config);
    $args = new Arguments([], ['ignore-skills' => true, 'no-discover' => true], ['ignore-skills', 'no-discover']);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->executeCommandCalls)->toBe(1)
        ->and($command->executeCommandClass)->toBe(InstallCommand::class)
        ->and($command->executeCommandArgs)->toBe(['--no-interaction', '--guidelines']);
});

it('exits silently when --ignore-skills flag is set and no guidelines are configured', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(false);
    $config->setSkills(['test-skill']);

    $this->exec('ignis update --ignore-skills --no-interaction --no-discover');

    $this->assertExitSuccess();
    $this->assertOutputNotContains('Ignis guidelines and skills updated successfully.');
});

it('skips new-package discovery prompt when running in non-interactive mode', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(true);
    $config->setPackages([]);

    $newPackage = new ThirdPartyPackage('vendor/awesome-pkg', true, false);

    $command = new TestableUpdateCommand($config);
    $command->resolvedNewPackages = new Collection(['vendor/awesome-pkg' => $newPackage]);

    $args = new Arguments([], ['no-interaction' => true], ['no-interaction']);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->discoverNewContentCalled)->toBeTrue()
        ->and($config->getPackages())->toBe([]);
});

it('skips new-package discovery prompt when running as a composer script', function (): void {
    $config = new Config();
    $config->setAgents(['claude_code']);
    $config->setGuidelines(true);
    $config->setPackages([]);

    $newPackage = new ThirdPartyPackage('vendor/awesome-pkg', true, false);

    $command = new class($config) extends TestableUpdateCommand {
        #[\Override]
        protected function runningAsComposerScript(): bool
        {
            return true;
        }
    };
    $command->resolvedNewPackages = new Collection(['vendor/awesome-pkg' => $newPackage]);

    $args = new Arguments([], [], []);
    $io = silentConsoleIo();

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->discoverNewContentCalled)->toBeTrue()
        ->and($config->getPackages())->toBe([]);
});
