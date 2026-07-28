<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools\DatabaseSchema;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Throwable;

/**
 * Base class for vendor-specific database schema queries.
 */
abstract class DatabaseSchemaDriver
{
    /**
     * Create a schema driver.
     *
     * @param string $connection Connection name
     */
    public function __construct(protected string $connection = 'default')
    {
    }

    /**
     * Execute a schema query and return associative rows.
     *
     * @param string $sql Schema SQL
     * @param array<int, mixed> $params Bound parameters
     * @return array<int, array<string, mixed>>
     */
    protected function query(string $sql, array $params = []): array
    {
        try {
            $connection = ConnectionManager::get($this->connection);

            if (!$connection instanceof Connection) {
                return [];
            }

            return $connection->execute($sql, $params)->fetchAll('assoc');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Get database views.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public function getViews(): array;

    /**
     * Get stored procedures.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public function getStoredProcedures(): array;

    /**
     * Get database functions.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public function getFunctions(): array;

    /**
     * Get table triggers.
     *
     * @param string|null $table Optional table name
     * @return array<int, array<string, mixed>>
     */
    abstract public function getTriggers(?string $table = null): array;

    /**
     * Get table check constraints.
     *
     * @param string $table Table name
     * @return array<int, array<string, mixed>>
     */
    abstract public function getCheckConstraints(string $table): array;

    /**
     * Get database sequences.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public function getSequences(): array;

    /**
     * Get base tables.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public function getTables(): array;
}
