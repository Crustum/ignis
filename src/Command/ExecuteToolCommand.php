<?php
declare(strict_types=1);

namespace Crustum\Ignis\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Ignis\Mcp\ToolRegistry;
use Crustum\Ignis\Trait\ConsolePromptTrait;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Override;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

/**
 * Executes a Ignis MCP tool in an isolated process.
 */
class ExecuteToolCommand extends Command
{
    use ConsolePromptTrait;

    /**
     * Constructor.
     *
     * @param \Psr\Container\ContainerInterface $container Application container
     */
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct();
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
        $toolClass = $args->getArgument('tool');
        $argumentsEncoded = $args->getArgument('arguments');

        if (!is_string($toolClass) || $toolClass === '') {
            $io->error('Tool class name is required.');

            return static::CODE_ERROR;
        }

        if (!is_string($argumentsEncoded) || $argumentsEncoded === '') {
            $io->error('Encoded arguments are required.');

            return static::CODE_ERROR;
        }

        if (!ToolRegistry::isToolAllowed($toolClass)) {
            $io->error("Tool not registered or not allowed: {$toolClass}");

            return static::CODE_ERROR;
        }

        $decodedPayload = base64_decode($argumentsEncoded, true);
        $arguments = json_decode($decodedPayload !== false ? $decodedPayload : '', true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error('Invalid arguments format: ' . json_last_error_msg());

            return static::CODE_ERROR;
        }

        if (!$this->container->has($toolClass)) {
            $io->error("Tool not resolvable from container: {$toolClass}");

            return static::CODE_ERROR;
        }

        /** @var object $tool */
        $tool = $this->container->get($toolClass);
        $request = new Request(is_array($arguments) ? $arguments : []);

        ob_start();

        try {
            $response = $tool->handle($request);
            if (!$response instanceof Response) {
                throw new RuntimeException('Tool did not return a valid MCP response.');
            }
        } catch (Throwable $throwable) {
            ob_end_clean();

            $errorResult = Response::error("Tool execution failed (E_THROWABLE): {$throwable->getMessage()}");

            $io->error(json_encode([
                'isError' => true,
                'content' => [
                    $errorResult->content()->toTool($tool),
                ],
            ], JSON_THROW_ON_ERROR));

            return static::CODE_ERROR;
        }

        ob_end_clean();

        $io->out(json_encode([
            'isError' => $response->isError(),
            'content' => [
                $response->content()->toTool($tool),
            ],
        ], JSON_THROW_ON_ERROR), 0);

        return static::CODE_SUCCESS;
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
            ->addArgument('tool', [
                'help' => 'Fully-qualified MCP tool class name',
                'required' => true,
            ])
            ->addArgument('arguments', [
                'help' => 'Base64-encoded JSON tool arguments',
                'required' => true,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'ignis execute-tool';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Execute a Ignis MCP tool in isolation (internal command)';
    }
}
