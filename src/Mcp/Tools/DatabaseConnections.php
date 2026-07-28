<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Lists configured CakePHP database connections.
 */
#[IsReadOnly]
class DatabaseConnections extends Tool
{
    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'List configured CakePHP database connection names.';

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Connection details
     */
    public function handle(Request $request): Response
    {
        return Response::json([
            'default_connection' => (string)Configure::read('Datasources.default', 'default'),
            'connections' => ConnectionManager::configured(),
        ]);
    }
}
