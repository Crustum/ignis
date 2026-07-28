<?php
declare(strict_types=1);

namespace Crustum\Ignis\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Ignis\Trait\ConsolePromptTrait;
use Crustum\Mcp\Command\McpStartCommand;
use Override;

/**
 * Starts the Cake Ignis MCP server over STDIO.
 */
class StartCommand extends Command
{
    use ConsolePromptTrait;

    /**
     * Constructor.
     *
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory
     */
    public function __construct(?CommandFactoryInterface $factory = null)
    {
        parent::__construct($factory);
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
        return $this->executeCommand(McpStartCommand::class, ['cake-ignis'], $io);
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

        return $this->configureIgnisOptionParser($parser);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'ignis mcp';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Start the Cake Ignis MCP server (usually from mcp.json)';
    }
}
