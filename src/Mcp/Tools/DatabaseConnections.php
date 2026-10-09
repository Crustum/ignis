<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools;

use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\Database\Driver\Sqlserver;
use Cake\Datasource\ConnectionManager;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

/**
 * Lists configured CakePHP database connections with their engine types.
 */
#[IsReadOnly]
class DatabaseConnections extends Tool
{
    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'List configured CakePHP datasource connection names with their engine types. SQL connections work with database-query and database-schema; other engines need their own tools (e.g. mongo-database-query for mongo).';

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Connection details
     */
    public function handle(Request $request): Response
    {
        $aliases = ConnectionManager::aliases();

        return Response::json([
            'default_connection' => $aliases['default'] ?? 'default',
            'connections' => array_map(
                fn(string $name): array => [
                    'name' => $name,
                    'engine' => $this->resolveEngine($name),
                ],
                ConnectionManager::configured(),
            ),
        ]);
    }

    /**
     * Resolve the engine type for a configured connection without exposing credentials.
     *
     * Engine knowledge is derived, never hardcoded per plugin, so new engines
     * (Oracle, ClickHouse, Mongo, …) need no Ignis core change:
     *
     * 1. A string `engine` connection config key acts as an explicit override.
     * 2. The four Cake core drivers keep canonical names.
     * 3. Any other driver derives it from its class short name without the
     *    `Driver` suffix (`MongoDriver` → `mongo`, `OracleOCI` → `oracleoci`).
     *
     * Every configured connection is listed — SQL-only tools keep rejecting
     * non-SQL connections themselves.
     *
     * @param string $name Connection name
     * @return string
     */
    protected function resolveEngine(string $name): string
    {
        try {
            $connection = ConnectionManager::get($name, false);
        } catch (Throwable) {
            return 'unknown';
        }

        try {
            $config = $connection->config();
        } catch (Throwable) {
            $config = null;
        }

        if (is_array($config) && is_string($config['engine'] ?? null) && $config['engine'] !== '') {
            return strtolower($config['engine']);
        }

        try {
            $driver = $connection->getDriver();
        } catch (Throwable) {
            return 'unknown';
        }

        if ($driver instanceof Mysql) {
            return 'mysql';
        }

        if ($driver instanceof Postgres) {
            return 'pgsql';
        }

        if ($driver instanceof Sqlite) {
            return 'sqlite';
        }

        if ($driver instanceof Sqlserver) {
            return 'sqlsrv';
        }

        $namespaced = strrchr($driver::class, '\\');
        $short = $namespaced === false ? $driver::class : substr($namespaced, 1);

        if (str_ends_with($short, 'Driver')) {
            $short = substr($short, 0, -strlen('Driver'));
        }

        return strtolower($short) ?: 'unknown';
    }
}
