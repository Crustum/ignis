<?php
declare(strict_types=1);

namespace Crustum\Ignis\Test\Fixtures;

use Cake\Collection\Collection;
use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Crustum\Ignis\Command\UpdateCommand;
use Crustum\Ignis\Support\Config;
use Crustum\Inspector\ProjectManager;
use Mockery;

/**
 * Update command test double that records delegated command invocations.
 */
class TestableUpdateCommand extends UpdateCommand
{
    /**
     * Number of delegated command executions.
     */
    public int $executeCommandCalls = 0;

    /**
     * Last delegated command class name.
     */
    public ?string $executeCommandClass = null;

    /**
     * Last delegated command arguments.
     *
     * @var array<int, string>
     */
    public array $executeCommandArgs = [];

    /**
     * Whether discoverNewContent was invoked.
     */
    public bool $discoverNewContentCalled = false;

    /**
     * Package collection returned from resolveNewPackages (empty unless a test sets it).
     */
    public Collection $resolvedNewPackages;

    /**
     * Create a testable update command.
     *
     * @param \Crustum\Ignis\Support\Config $config Ignis install config
     * @param \Crustum\Inspector\ProjectManager|null $project Optional project manager
     */
    public function __construct(Config $config, ?ProjectManager $project = null)
    {
        parent::__construct($config, $project ?? Mockery::mock(ProjectManager::class));
        $this->resolvedNewPackages = new Collection([]);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function executeCommand(CommandInterface|string $command, array $args = [], ?ConsoleIo $io = null): ?int
    {
        $this->executeCommandCalls++;
        $this->executeCommandClass = is_string($command) ? $command : $command::class;
        $this->executeCommandArgs = $args;

        return static::CODE_SUCCESS;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function discoverNewContent(Arguments $args, ConsoleIo $io): void
    {
        $this->discoverNewContentCalled = true;

        parent::discoverNewContent($args, $io);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function resolveNewPackages(): Collection
    {
        return $this->resolvedNewPackages;
    }
}
