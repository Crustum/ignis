<?php
declare(strict_types=1);

namespace Crustum\Ignis\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\JsonSchema\Contracts\JsonSchema;
use RuntimeException;

/**
 * MCP tool fixture that always throws for execute-tool feature tests.
 */
class ThrowingTool extends Tool
{
    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'A test tool that always throws an exception';

    /**
     * Define the tool input schema.
     *
     * @param \Crustum\JsonSchema\Contracts\JsonSchema $schema JSON schema builder
     * @return array<string, mixed>
     */
    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response
     */
    public function handle(Request $request): Response
    {
        throw new RuntimeException('Intentional test exception');
    }
}
