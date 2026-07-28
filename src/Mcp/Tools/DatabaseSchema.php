<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools;

use Cake\Database\Connection;
use Cake\Database\Schema\TableSchema;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\Datasource\ConnectionManager;
use Crustum\Ignis\Mcp\Tools\DatabaseSchema\SchemaDriverFactory;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Override;
use Throwable;

/**
 * Reads CakePHP database schema metadata.
 */
#[IsReadOnly]
class DatabaseSchema extends Tool
{
    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'Read table names, columns, indexes, foreign keys, views, routines, triggers, and check constraints from a CakePHP database connection. Use summary mode first, then request full details for specific tables with filter.';

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
            'summary' => $schema->boolean()
                ->description('Return only table names and column types. Defaults to false.'),
            'database' => $schema->string()
                ->description('CakePHP datasource connection name. Defaults to default.'),
            'filter' => $schema->string()
                ->description('Filter tables by name (substring match).'),
            'include_views' => $schema->boolean()
                ->description('Include database views. Defaults to false.'),
            'include_routines' => $schema->boolean()
                ->description('Include stored procedures, functions, and sequences. Defaults to false.'),
            'include_column_details' => $schema->boolean()
                ->description('Include nullable, default, auto_increment, comments, and generation metadata. Defaults to false.'),
        ];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Schema data
     */
    public function handle(Request $request): Response
    {
        $connectionName = (string)$request->get('database', 'default');
        $filter = (string)$request->get('filter', '');
        $summary = (bool)$request->get('summary', false);
        $includeColumnDetails = (bool)$request->get('include_column_details', false);

        try {
            $connection = ConnectionManager::get($connectionName);

            if (!$connection instanceof Connection) {
                return Response::error('The configured connection does not support schema inspection.');
            }

            $result = [
                'engine' => $this->resolveEngineName($connection),
                'tables' => $summary
                    ? $this->getAllTableColumnTypes($connection, $filter)
                    : $this->getAllTablesStructure($connection, $filter, $includeColumnDetails),
            ];

            if ($summary) {
                return Response::json($result);
            }

            $driver = SchemaDriverFactory::make($connectionName);

            if ((bool)$request->get('include_views', false)) {
                $result['views'] = $driver->getViews();
            }

            if ((bool)$request->get('include_routines', false)) {
                $result['routines'] = [
                    'stored_procedures' => $driver->getStoredProcedures(),
                    'functions' => $driver->getFunctions(),
                    'sequences' => $driver->getSequences(),
                ];
            }

            return Response::json($result);
        } catch (Throwable $throwable) {
            return Response::error('Failed to read database schema: ' . $throwable->getMessage());
        }
    }

    /**
     * Resolve a short driver name for schema responses.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @return string
     */
    protected function resolveEngineName(Connection $connection): string
    {
        $driverClass = $connection->getDriver()::class;

        return match (true) {
            str_contains($driverClass, 'Sqlite') => 'sqlite',
            str_contains($driverClass, 'Mysql') => 'mysql',
            str_contains($driverClass, 'Postgres') => 'pgsql',
            str_contains($driverClass, 'Sqlserver') => 'sqlsrv',
            default => strtolower(substr(strrchr($driverClass, '\\') ?: $driverClass, 1) ?: $driverClass),
        };
    }

    /**
     * Build full table structures keyed by table name.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param string $filter Table name filter
     * @param bool $includeColumnDetails Whether to include full column metadata
     * @return array<string, array<string, mixed>>
     */
    protected function getAllTablesStructure(Connection $connection, string $filter, bool $includeColumnDetails): array
    {
        $structures = [];

        foreach ($this->listFilteredTables($connection, $filter) as $tableName) {
            $structures[$tableName] = $this->getTableStructure($connection, $tableName, $includeColumnDetails);
        }

        return $structures;
    }

    /**
     * Build summary table maps keyed by table name.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param string $filter Table name filter
     * @return array<string, array<string, string>>
     */
    protected function getAllTableColumnTypes(Connection $connection, string $filter): array
    {
        $tables = [];

        foreach ($this->listFilteredTables($connection, $filter) as $tableName) {
            $schema = $connection->getSchemaCollection()->describe($tableName);
            $columns = [];

            foreach ($schema->columns() as $columnName) {
                $column = $schema->getColumn($columnName);
                $columns[$columnName] = (string)($column['type'] ?? 'unknown');
            }

            $tables[$tableName] = $columns;
        }

        return $tables;
    }

    /**
     * List table names optionally filtered by substring.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param string $filter Table name filter
     * @return array<int, string>
     */
    protected function listFilteredTables(Connection $connection, string $filter): array
    {
        $tables = [];

        foreach ($connection->getSchemaCollection()->listTables() as $tableName) {
            if ($filter !== '' && !str_contains(strtolower($tableName), strtolower($filter))) {
                continue;
            }

            $tables[] = $tableName;
        }

        return $tables;
    }

    /**
     * Build the structure payload for one table.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param string $tableName Table name
     * @param bool $includeColumnDetails Whether to include full column metadata
     * @return array<string, mixed>
     */
    protected function getTableStructure(Connection $connection, string $tableName, bool $includeColumnDetails): array
    {
        $schema = $connection->getSchemaCollection()->describe($tableName);
        $driver = SchemaDriverFactory::make($connection->configName());

        return [
            'columns' => $this->getTableColumns($schema, $includeColumnDetails),
            'indexes' => $this->getTableIndexes($schema),
            'foreign_keys' => $this->getTableForeignKeys($schema),
            'triggers' => $driver->getTriggers($tableName),
            'check_constraints' => $driver->getCheckConstraints($tableName),
        ];
    }

    /**
     * Normalize table columns for schema output.
     *
     * @param \Cake\Database\Schema\TableSchemaInterface $schema Table schema
     * @param bool $includeColumnDetails Whether to include full column metadata
     * @return array<string, array<string, mixed>>
     */
    protected function getTableColumns(TableSchemaInterface $schema, bool $includeColumnDetails): array
    {
        $columnDetails = [];

        foreach ($schema->columns() as $columnName) {
            $column = $schema->getColumn($columnName);

            if ($column === null) {
                continue;
            }

            $detail = ['type' => (string)($column['type'] ?? 'unknown')];

            if ($includeColumnDetails) {
                $detail['nullable'] = (bool)($column['null'] ?? true);
                $detail['default'] = $column['default'] ?? null;
                $detail['auto_increment'] = (bool)($column['autoIncrement'] ?? false);

                if (isset($column['comment']) && $column['comment'] !== '') {
                    $detail['comment'] = $column['comment'];
                }
            }

            $columnDetails[$columnName] = $detail;
        }

        return $columnDetails;
    }

    /**
     * Normalize table indexes for schema output.
     *
     * @param \Cake\Database\Schema\TableSchemaInterface $schema Table schema
     * @return array<string, array<string, mixed>>
     */
    protected function getTableIndexes(TableSchemaInterface $schema): array
    {
        $indexDetails = [];

        foreach ($schema->indexes() as $indexName) {
            $index = $schema->getIndex($indexName);

            if ($index === null) {
                continue;
            }

            $type = (string)($index['type'] ?? '');
            $indexDetails[$indexName] = [
                'columns' => $index['columns'] ?? [],
                'type' => $type !== '' ? $type : null,
                'is_unique' => $type === TableSchema::CONSTRAINT_UNIQUE,
                'is_primary' => $type === TableSchema::CONSTRAINT_PRIMARY,
            ];
        }

        foreach ($schema->constraints() as $constraintName) {
            if (isset($indexDetails[$constraintName])) {
                continue;
            }

            $constraint = $schema->getConstraint($constraintName);

            if ($constraint === null) {
                continue;
            }

            $type = (string)($constraint['type'] ?? '');

            if ($type !== TableSchema::CONSTRAINT_PRIMARY && $type !== TableSchema::CONSTRAINT_UNIQUE) {
                continue;
            }

            $indexDetails[$constraintName] = [
                'columns' => $constraint['columns'] ?? [],
                'type' => $type,
                'is_unique' => $type === TableSchema::CONSTRAINT_UNIQUE,
                'is_primary' => $type === TableSchema::CONSTRAINT_PRIMARY,
            ];
        }

        return $indexDetails;
    }

    /**
     * Normalize foreign key constraints for schema output.
     *
     * @param \Cake\Database\Schema\TableSchemaInterface $schema Table schema
     * @return array<string, array<string, mixed>>
     */
    protected function getTableForeignKeys(TableSchemaInterface $schema): array
    {
        $foreignKeys = [];

        foreach ($schema->constraints() as $constraintName) {
            $constraint = $schema->getConstraint($constraintName);
            if ($constraint === null) {
                continue;
            }

            if (($constraint['type'] ?? '') !== TableSchema::CONSTRAINT_FOREIGN) {
                continue;
            }

            $foreignKeys[$constraintName] = $constraint;
        }

        return $foreignKeys;
    }
}
