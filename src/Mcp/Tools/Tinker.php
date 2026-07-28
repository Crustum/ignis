<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools;

use Cake\Core\Configure;
use Crustum\Ignis\Tinker\TinkerExecutor;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Override;

/**
 * Executes PHP code in the CakePHP application context.
 */
class Tinker extends Tool
{
    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'Execute PHP code in the CakePHP application context. ' .
        'Use for debugging, testing code snippets, and exploring the application. ' .
        'DO NOT create or modify data without explicit user approval. ' .
        'Prefer feature tests and existing commands over custom code. ' .
        'Use fully-qualified CakePHP classes such as \\Cake\\Core\\Configure and \\Cake\\Datasource\\ConnectionManager. ' .
        'Bare expressions are automatically returned; use echo or print for side-effect output only.';

    /**
     * @param \Crustum\Ignis\Tinker\TinkerExecutor $executor Code executor
     */
    public function __construct(protected TinkerExecutor $executor)
    {
    }

    /**
     * Determine whether the tool is enabled.
     *
     * @return bool Whether the tool can be registered
     */
    public function shouldRegister(): bool
    {
        return (bool)Configure::read('Ignis.tinker_tool_enabled', false);
    }

    /**
     * Define the tool input schema.
     *
     * @param \Crustum\JsonSchema\Contracts\JsonSchema $schema JSON schema builder
     * @return array<string, mixed>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()
                ->description('PHP code to execute without opening <?php tags.')
                ->required(),
            'timeout' => $schema->integer()
                ->description('Maximum execution time in seconds (default: 30, max: 180).')
                ->default(30),
        ];
    }

    /**
     * Execute PHP code in the application context.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Execution result
     */
    public function handle(Request $request): Response
    {
        $result = $this->executor->execute(
            (string)$request->get('code', ''),
            max(1, min(180, (int)$request->get('timeout', 30))),
        );

        if (!($result['success'] ?? false)) {
            return Response::error((string)($result['error'] ?? 'Execution failed'));
        }

        return Response::json($result);
    }
}
