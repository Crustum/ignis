<?php

declare(strict_types=1);

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOutput;
use Crustum\Ignis\Command\StartCommand;
use Crustum\Mcp\Command\McpStartCommand;

it('invokes mcp start with cake-ignis as the server name', function (): void {
    $command = new class extends StartCommand {
        public ?string $executeCommandClass = null;

        public array $executeCommandArgs = [];

        public function executeCommand(CommandInterface|string $command, array $args = [], ?ConsoleIo $io = null): int
        {
            $this->executeCommandClass = is_string($command) ? $command : $command::class;
            $this->executeCommandArgs = $args;

            return Command::CODE_SUCCESS;
        }
    };

    $args = new Arguments([], [], []);
    $io = new ConsoleIo(new ConsoleOutput(), new ConsoleOutput());

    expect($command->execute($args, $io))->toBe(Command::CODE_SUCCESS)
        ->and($command->executeCommandClass)->toBe(McpStartCommand::class)
        ->and($command->executeCommandArgs)->toBe(['cake-ignis']);
});

it('returns the same exit code that mcp start returns', function (): void {
    $command = new class extends StartCommand {
        public function executeCommand(CommandInterface|string $command, array $args = [], ?ConsoleIo $io = null): int
        {
            return Command::CODE_ERROR;
        }
    };

    $args = new Arguments([], [], []);
    $io = new ConsoleIo(new ConsoleOutput(), new ConsoleOutput());

    expect($command->execute($args, $io))->toBe(Command::CODE_ERROR);
});
