<?php
declare(strict_types=1);

namespace Crustum\Ignis\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Ignis\Trait\ConsolePromptTrait;
use Crustum\Mcp\Command\McpInspectorCommand;
use Override;

/**
 * Opens the MCP Inspector for the Cake Ignis server.
 */
class InspectorCommand extends Command
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
        $inspectorArgs = ['cake-ignis'];

        $host = $args->getOption('host');

        if (is_string($host) && $host !== '') {
            $inspectorArgs[] = '--host=' . $host;
        }

        $port = $args->getOption('port');

        if (is_string($port) && $port !== '') {
            $inspectorArgs[] = '--port=' . $port;
        }

        $url = $args->getOption('url');

        if (is_string($url) && $url !== '') {
            $inspectorArgs[] = '--url=' . $url;
        }

        return $this->executeCommand(McpInspectorCommand::class, $inspectorArgs, $io);
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
            ->addOption('host', [
                'help' => 'The host the inspector should bind to.',
                'default' => null,
            ])
            ->addOption('port', [
                'help' => 'The port the inspector should bind to.',
                'default' => null,
            ])
            ->addOption('url', [
                'help' => 'Absolute MCP server URL for HTTP transport.',
                'default' => null,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'ignis inspector';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Open the MCP Inspector for the Cake Ignis server';
    }
}
