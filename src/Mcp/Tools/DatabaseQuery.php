<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools;

use Cake\Database\Connection;
use Cake\Database\Driver;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
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
     * Statement-starting keywords that write data.
     */
    private const WRITE_KEYWORDS = 'DELETE|UPDATE|DROP|ALTER|TRUNCATE|RENAME|CREATE|MERGE';

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

        if ($query === '') {
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
                $query = $this->addPrefixToQuery($query, $prefix, $this->usesBackslashEscapes($connection->getDriver()));
            }

            $rows = $this->runReadOnlyQuery($connection, $query);

            return Response::json($rows);
        } catch (Throwable $throwable) {
            return Response::error('Query failed: ' . $throwable->getMessage());
        }
    }

    /**
     * Run a query inside a database-enforced read-only transaction.
     *
     * The lexical guard above is a fast-fail, not the source of truth: SQL
     * dialects have too many shapes (data-modifying CTEs, INTO OUTFILE, vendor
     * extensions) for parsing to be exhaustive. This wraps execution in a
     * transaction the engine itself treats as read-only and always rolls it
     * back so nothing persists even if that hint is ignored by the driver.
     *
     * @param \Cake\Database\Connection $connection Active database connection
     * @param string $query Read-only SQL query
     * @return array<int, array<string, mixed>> Result rows
     */
    protected function runReadOnlyQuery(Connection $connection, string $query): array
    {
        $driver = $connection->getDriver();

        if ($driver instanceof Mysql) {
            $connection->execute('SET TRANSACTION READ ONLY');
        }

        $connection->begin();

        try {
            if ($driver instanceof Postgres) {
                $connection->execute('SET TRANSACTION READ ONLY');
            } elseif ($driver instanceof Sqlite) {
                $connection->execute('PRAGMA query_only = ON');
            }

            return $connection->execute($query)->fetchAll('assoc');
        } finally {
            $connection->rollback();

            if ($driver instanceof Sqlite) {
                $connection->execute('PRAGMA query_only = OFF');
            }
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
        $parsed = $this->withoutLiteralsAndComments($query);
        $structure = $parsed['structure'];
        $hasVersionComment = $parsed['hasVersionComment'];

        if ($hasVersionComment) {
            return false;
        }

        if (preg_match('/;\s*\S/', $structure)) {
            return false;
        }

        $token = strtok($structure, " \t\n\r");

        if ($token === false) {
            return false;
        }

        $firstWord = strtoupper($token);

        $allowList = [
            'SELECT',
            'SHOW',
            'EXPLAIN',
            'DESCRIBE',
            'DESC',
            'WITH',
            'VALUES',
            'TABLE',
        ];

        if (!in_array($firstWord, $allowList, true)) {
            return false;
        }

        if ($firstWord === 'WITH' && preg_match('/\)\s*SELECT\b/i', $structure) !== 1) {
            return false;
        }

        if (preg_match('/(^|[();])\s*(?:(?:' . self::WRITE_KEYWORDS . ')\b|(?:INSERT|REPLACE)\b(?!\s*\())/i', $structure)) {
            return false;
        }

        if (
            $firstWord === 'EXPLAIN' && preg_match(
                '/^\s*EXPLAIN\s+(?:\([^)]*\)\s*|(?:ANALYZE|VERBOSE|QUERY\s+PLAN|FORMAT\s*=?\s*\w+)\s+)*(?:' . self::WRITE_KEYWORDS . '|INSERT|REPLACE)\b/i',
                $structure,
            )
        ) {
            return false;
        }

        return !preg_match('/\bINTO\b/i', $structure);
    }

    /**
     * Strip string literals and comments from a query, preserving structure.
     *
     * @param string $query SQL query
     * @param bool $backslashEscapes Whether backslash escapes inside string literals
     * @return array{structure: string, hasVersionComment: bool, spans: list<array{int, int}>} Query skeleton, version-comment flag, and literal/comment spans
     */
    protected function withoutLiteralsAndComments(string $query, bool $backslashEscapes = false): array
    {
        $structure = '';
        $state = 'none';
        $hasVersionComment = false;
        $spans = [];
        $spanStart = 0;
        $length = strlen($query);

        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];
            $next = $query[$i + 1] ?? '';

            if ($state === 'none') {
                if (in_array($char, ["'", '"', '`'], true)) {
                    $state = match ($char) {
                        "'" => 'single',
                        '"' => 'double',
                        default => 'backtick',
                    };
                    $spanStart = $i;
                    $structure .= ' ';
                } elseif ($char === '-' && $next === '-') {
                    $state = 'line_comment';
                    $spanStart = $i;
                    $structure .= ' ';
                    $i++;
                } elseif ($char === '/' && $next === '*') {
                    if (($query[$i + 2] ?? '') === '!') {
                        $hasVersionComment = true;
                        $structure .= $char;
                    } else {
                        $state = 'block_comment';
                        $spanStart = $i;
                        $structure .= ' ';
                        $i++;
                    }
                } else {
                    $structure .= $char;
                }
            } elseif ($state === 'line_comment') {
                if ($char === "\n") {
                    $spans[] = [$spanStart, $i];
                    $state = 'none';
                    $structure .= $char;
                }
            } elseif ($state === 'block_comment') {
                if ($char === '*' && $next === '/') {
                    $spans[] = [$spanStart, $i + 2];
                    $state = 'none';
                    $i++;
                }
            } else {
                $quote = match ($state) {
                    'single' => "'",
                    'double' => '"',
                    default => '`',
                };

                if ($backslashEscapes && $char === '\\' && $state !== 'backtick') {
                    $i++;

                    continue;
                }

                if ($char === $quote) {
                    if ($next === $quote) {
                        $i++;
                    } else {
                        $spans[] = [$spanStart, $i + 1];
                        $state = 'none';
                    }
                }
            }
        }

        if ($state !== 'none') {
            $spans[] = [$spanStart, $length];
        }

        return ['structure' => $structure, 'hasVersionComment' => $hasVersionComment, 'spans' => $spans];
    }

    /**
     * Apply a table prefix to table references inside a query.
     *
     * @param string $query SQL query
     * @param string $prefix Table prefix
     * @param bool $backslashEscapes Whether backslash escapes inside string literals
     * @return string
     */
    protected function addPrefixToQuery(string $query, string $prefix, bool $backslashEscapes = false): string
    {
        $describePattern = '/^(\s*)(DESCRIBE|DESC)\s+((?:[`"]?\w+[`"]?\s*\.\s*)?)([`"\']?)(\w+)\4/i';

        $query = preg_replace_callback($describePattern, function (array $matches) use ($prefix): string {
            [$full, $leading, $keyword, $qualifier, $quote, $tableName] = $matches;

            if (str_starts_with($tableName, $prefix)) {
                return $full;
            }

            return "{$leading}{$keyword} {$qualifier}{$quote}{$prefix}{$tableName}{$quote}";
        }, $query) ?? $query;

        ['structure' => $structure, 'spans' => $spans] = $this->withoutLiteralsAndComments($query, $backslashEscapes);
        $cteNames = $this->extractCteNames($structure);

        $pattern = '/\b(FROM|JOIN|INTO|UPDATE|TABLE)\s+((?:[`"]?\w+[`"]?\s*\.\s*)?)([`"\']?)(\w+)\3/i';

        if (!preg_match_all($pattern, $query, $allMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $query;
        }

        foreach (array_reverse($allMatches) as $matches) {
            [$full, $offset] = $matches[0];

            if ($this->offsetIsInsideLiteralOrComment($offset, $spans)) {
                continue;
            }

            $keyword = $matches[1][0];
            $qualifier = $matches[2][0];
            $quote = $matches[3][0];
            $tableName = $matches[4][0];

            if ($this->tableIsPrefixedOrCte($tableName, $prefix, $cteNames)) {
                continue;
            }

            $query = substr_replace($query, "{$keyword} {$qualifier}{$quote}{$prefix}{$tableName}{$quote}", $offset, strlen($full));
        }

        return $query;
    }

    /**
     * Whether the driver treats backslash as an escape inside string literals.
     *
     * @param \Cake\Database\Driver $driver Active database driver
     * @return bool
     */
    protected function usesBackslashEscapes(Driver $driver): bool
    {
        return $driver instanceof Mysql;
    }

    /**
     * Whether a match offset starts inside a literal or comment span.
     *
     * @param int $offset Match offset in the original query
     * @param list<array{int, int}> $spans Literal and comment spans
     * @return bool
     */
    protected function offsetIsInsideLiteralOrComment(int $offset, array $spans): bool
    {
        foreach ($spans as [$start, $end]) {
            if ($offset >= $start && $offset < $end) {
                return true;
            }
        }

        return false;
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
        return str_starts_with($tableName, $prefix) || in_array(strtolower($tableName), $cteNames, true);
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
            return array_map(strtolower(...), $matches[1]);
        }

        return [];
    }
}
