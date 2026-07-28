<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Override;
use Throwable;

/**
 * Executes validated read-only SQL queries.
 */
#[IsReadOnly]
class DatabaseQuery extends Tool
{
    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'Execute a read-only SQL query against a configured CakePHP database connection.';

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
            'query' => $schema->string()
                ->description('The SQL query to execute. Only read-only queries are allowed (SELECT, SHOW, EXPLAIN, DESCRIBE, DESC, WITH … SELECT).')
                ->required(),
            'database' => $schema->string()
                ->description('Optional CakePHP datasource connection name. Defaults to default.'),
        ];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Query result
     */
    public function handle(Request $request): Response
    {
        $query = trim((string)$request->get('query'));
        $token = strtok(ltrim($query), " \t\n\r");

        if ($token === false) {
            return Response::error('Please pass a valid query');
        }

        if (!$this->isReadOnlyQuery($query)) {
            return Response::error('Only read-only queries are allowed (SELECT, SHOW, EXPLAIN, DESCRIBE, DESC, WITH … SELECT).');
        }

        $database = $request->get('database');

        try {
            $connection = ConnectionManager::get(is_string($database) && $database !== '' ? $database : 'default');

            if (!$connection instanceof Connection) {
                return Response::error('The configured connection does not support SQL execution.');
            }

            $prefix = (string)($connection->config()['prefix'] ?? '');

            if ($prefix !== '') {
                $query = $this->addPrefixToQuery($query, $prefix);
            }

            $statement = $connection->execute($query);

            return Response::json($statement->fetchAll('assoc'));
        } catch (Throwable $throwable) {
            return Response::error('Query failed: ' . $throwable->getMessage());
        }
    }

    /**
     * Determine whether a SQL statement is read-only.
     *
     * @param string $query SQL query
     * @return bool Whether the query is permitted
     */
    protected function isReadOnlyQuery(string $query): bool
    {
        $token = strtok(ltrim($query), " \t\n\r");

        if ($token === false) {
            return false;
        }

        $firstWord = strtoupper($token);

        if (in_array($firstWord, ['SELECT', 'SHOW', 'EXPLAIN', 'DESCRIBE', 'DESC', 'VALUES', 'TABLE'], true)) {
            return true;
        }

        if ($firstWord !== 'WITH' || preg_match('/\)\s*SELECT\b/i', $query) !== 1) {
            return false;
        }

        return preg_match('/\)\s*(DELETE|UPDATE|INSERT|DROP|ALTER|TRUNCATE|REPLACE|RENAME|CREATE)\b/i', $query) !== 1;
    }

    /**
     * Apply a table prefix to table references inside a query.
     *
     * @param string $query SQL query
     * @param string $prefix Table prefix
     * @return string
     */
    protected function addPrefixToQuery(string $query, string $prefix): string
    {
        $cteNames = $this->extractCteNames($query);
        $describePattern = '/^(\s*)(DESCRIBE|DESC)\s+((?:[`"]?\w+[`"]?\s*\.\s*)?)([`"\']?)(\w+)\4/i';

        $query = preg_replace_callback($describePattern, function (array $matches) use ($prefix, $cteNames): string {
            [$full, $leading, $keyword, $qualifier, $quote, $tableName] = $matches;

            if ($this->tableIsPrefixedOrCte($tableName, $prefix, $cteNames)) {
                return $full;
            }

            return "{$leading}{$keyword} {$qualifier}{$quote}{$prefix}{$tableName}{$quote}";
        }, $query) ?? $query;

        $pattern = '/\b(FROM|JOIN|INTO|UPDATE|TABLE)\s+((?:[`"]?\w+[`"]?\s*\.\s*)?)([`"\']?)(\w+)\3/i';

        return preg_replace_callback($pattern, function (array $matches) use ($prefix, $cteNames): string {
            [$full, $keyword, $qualifier, $quote, $tableName] = $matches;

            if ($this->tableIsPrefixedOrCte($tableName, $prefix, $cteNames)) {
                return $full;
            }

            return "{$keyword} {$qualifier}{$quote}{$prefix}{$tableName}{$quote}";
        }, $query) ?? $query;
    }

    /**
     * Determine whether a table name is already prefixed or is a CTE alias.
     *
     * @param string $tableName Table name
     * @param string $prefix Table prefix
     * @param array<int, string> $cteNames CTE names
     * @return bool
     */
    protected function tableIsPrefixedOrCte(string $tableName, string $prefix, array $cteNames): bool
    {
        return str_starts_with($tableName, $prefix) || in_array($tableName, $cteNames, true);
    }

    /**
     * Extract CTE names from a query.
     *
     * @param string $query SQL query
     * @return array<int, string>
     */
    protected function extractCteNames(string $query): array
    {
        if (preg_match_all('/\b(\w+)\s*(?:\([^)]*\))?\s*AS\s*\(/i', $query, $matches) !== false) {
            return $matches[1];
        }

        return [];
    }
}
