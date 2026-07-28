<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools\DatabaseSchema;

use Cake\Datasource\ConnectionManager;

/**
 * Resolves the schema driver for a CakePHP database connection.
 */
class SchemaDriverFactory
{
    /**
     * Create a driver for a connection.
     *
     * @param string $connection Connection name
     * @return \Crustum\Ignis\Mcp\Tools\DatabaseSchema\DatabaseSchemaDriver Schema driver
     */
    public static function make(string $connection = 'default'): DatabaseSchemaDriver
    {
        $driver = ConnectionManager::get($connection)->getDriver()::class;

        return match (true) {
            str_contains($driver, 'Mysql') => new MySQLSchemaDriver($connection),
            str_contains($driver, 'Postgres') => new PostgreSQLSchemaDriver($connection),
            str_contains($driver, 'Sqlite') => new SQLiteSchemaDriver($connection),
            default => new NullSchemaDriver($connection),
        };
    }
}
