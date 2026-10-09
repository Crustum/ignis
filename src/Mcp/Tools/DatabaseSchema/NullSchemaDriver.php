<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools\DatabaseSchema;

use Cake\Collection\Collection;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Throwable;

/**
 * Empty schema driver for unsupported database vendors.
 */
class NullSchemaDriver extends DatabaseSchemaDriver
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getViews(): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStoredProcedures(): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFunctions(): array
    {
        return [];
    }

    /**
     * @param string|null $table Table name
     * @return array<int, array<string, mixed>>
     */
    public function getTriggers(?string $table = null): array
    {
        return [];
    }

    /**
     * @param string $table Table name
     * @return array<int, array<string, mixed>>
     */
    public function getCheckConstraints(string $table): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSequences(): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTables(): array
    {
        try {
            $connection = ConnectionManager::get($this->connection);

            if (!$connection instanceof Connection) {
                return [];
            }

            /** @var array<int, array<string, mixed>> $tables */
            $tables = (new Collection($connection->getSchemaCollection()->listTables()))
                ->map(fn(string $table): array => ['name' => $table])
                ->toList();

            return $tables;
        } catch (Throwable) {
            return [];
        }
    }
}
