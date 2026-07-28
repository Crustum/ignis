<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools;

use Cake\Routing\Router;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Override;

/**
 * Resolves relative paths or named routes to absolute URLs.
 */
#[IsReadOnly]
class GetAbsoluteUrl extends Tool
{
    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'Get the absolute URL for a relative path or named route. If no arguments are provided, returns the absolute URL for /.';

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
            'path' => $schema->string()
                ->description('Relative URL path to convert (for example /dashboard).'),
            'route' => $schema->string()
                ->description('Named CakePHP route to generate a URL for.'),
        ];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Absolute URL
     */
    public function handle(Request $request): Response
    {
        $path = $request->get('path');
        $route = $request->get('route');

        if (is_string($path) && $path !== '') {
            return Response::text(Router::url($path, true));
        }

        if (is_string($route) && $route !== '') {
            return Response::text(Router::url(['_name' => $route], true));
        }

        return Response::text(Router::url('/', true));
    }
}
