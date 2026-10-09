<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\Database\StatementInterface;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Cake\Routing\Router;
use Crustum\Ignis\Mcp\Methods\CallToolWithExecutor;
use Crustum\Ignis\Rules\RuleRepository;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Schema\Implementation;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Transport\JsonRpcRequest;
use JMac\Testing\Double;

if (!function_exists('toolResponseText')) {
    /**
     * Extract text content from an MCP tool response.
     *
     * @param \Crustum\Mcp\Response $response Tool response
     * @return string
     */
    function toolResponseText(Response $response): string
    {
        return (string)$response->content();
    }
}

if (!function_exists('toolResponseJson')) {
    /**
     * Decode JSON content from an MCP tool response.
     *
     * @param \Crustum\Mcp\Response $response Tool response
     * @return array<string, mixed>
     */
    function toolResponseJson(Response $response): array
    {
        $decoded = json_decode(toolResponseText($response), true);

        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('featureLogsPath')) {
    /**
     * Resolve a path under the feature test logs directory.
     *
     * @param string $path Optional relative path
     * @return string
     */
    function featureLogsPath(string $path = ''): string
    {
        if ($path === '') {
            return rtrim(LOGS, DS) . DS;
        }

        return featureLogsPath() . ltrim(str_replace(['/', '\\'], DS, $path), DS);
    }
}

if (!function_exists('createFeatureLogFile')) {
    /**
     * Write a feature test log file.
     *
     * @param string $path Absolute log file path
     * @param string $content Log file contents
     * @return void
     */
    function createFeatureLogFile(string $path, string $content): void
    {
        ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
    }
}

if (!function_exists('cleanFeatureLogDirectory')) {
    /**
     * Remove all files from the feature test logs directory.
     *
     * @return void
     */
    function cleanFeatureLogDirectory(): void
    {
        if (!is_dir(LOGS)) {
            return;
        }

        foreach (glob(LOGS . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}


if (!function_exists('setFeatureLogChannel')) {
    /**
     * Configure a Cake File log channel for feature tests.
     *
     * @param string $name Channel name
     * @param array<string, mixed> $config Channel configuration
     * @return void
     */
    function setFeatureLogChannel(string $name, array $config): void
    {
        Log::setConfig($name, array_merge(['className' => 'File'], $config));
    }
}

if (!function_exists('featureLogFilePath')) {
    /**
     * Resolve the absolute path for a Cake File log channel file name.
     *
     * @param string $file Log file name
     * @param string|null $path Log directory
     * @return string
     */
    function featureLogFilePath(string $file, ?string $path = null): string
    {
        $path ??= LOGS;
        $normalizedPath = rtrim($path, DS) . DS;
        $fileName = str_ends_with($file, '.log') ? $file : $file . '.log';

        return $normalizedPath . $fileName;
    }
}

if (!function_exists('configureFeatureLogChannel')) {
    /**
     * Configure a Cake File log channel like config/app.php.
     *
     * @param string $channel Channel name
     * @param string $file Log file name
     * @param string|null $path Log directory
     * @param array<string, mixed> $options Additional FileLog options such as levels, scopes, or formatter
     * @return string Absolute log file path
     */
    function configureFeatureLogChannel(
        string $channel,
        string $file,
        ?string $path = null,
        array $options = [],
    ): string {
        $path ??= LOGS;
        ensureDirectoryExists($path);
        setFeatureLogChannel($channel, array_merge([
            'path' => $path,
            'file' => $file,
        ], $options));

        return featureLogFilePath($file, $path);
    }
}

if (!function_exists('configureFeatureAppLogChannels')) {
    /**
     * Configure common CakePHP application log channels for feature tests.
     *
     * @return void
     */
    function configureFeatureAppLogChannels(): void
    {
        configureFeatureLogChannel('debug', 'debug', options: [
            'levels' => ['notice', 'info', 'debug'],
        ]);
        configureFeatureLogChannel('error', 'error', options: [
            'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
        ]);
        configureFeatureLogChannel('queries', 'queries', options: [
            'scopes' => ['queriesLog'],
        ]);
    }
}

if (!function_exists('writeFeatureLog')) {
    /**
     * Write a log entry through CakePHP Log so FileLog creates real log files.
     *
     * @param string $level PSR-3 log level
     * @param string $message Log message
     * @param array<string, mixed> $context Log context including optional scope
     * @return void
     */
    function writeFeatureLog(string $level, string $message, array $context = []): void
    {
        Log::write($level, $message, $context);
    }
}

if (!function_exists('browserLogPath')) {
    /**
     * Resolve the browser log file path used by BrowserLogs tests.
     *
     * @return string
     */
    function browserLogPath(): string
    {
        return featureLogsPath('browser.log');
    }
}

if (!function_exists('createBrowserLogFile')) {
    /**
     * Write the browser log file used by BrowserLogs tests.
     *
     * @param string $content Log file contents
     * @return void
     */
    function createBrowserLogFile(string $content): void
    {
        createFeatureLogFile(browserLogPath(), $content);
    }
}

if (!function_exists('getBrowserLogContent')) {
    /**
     * Read the browser log file contents.
     *
     * @return string
     */
    function getBrowserLogContent(): string
    {
        $contents = file_get_contents(browserLogPath());

        return is_string($contents) ? $contents : '';
    }
}

if (!function_exists('configureFeatureLog')) {
    /**
     * Point Cake log tools at a dedicated test log file.
     *
     * @param string $basename Log file basename without extension
     * @return string Absolute log file path
     */
    function configureFeatureLog(string $basename = 'feature-test'): string
    {
        return configureFeatureLogChannel('error', $basename);
    }
}

if (!function_exists('writeFeatureLogFile')) {
    /**
     * Write content directly to a log file path.
     *
     * @param string $path Absolute log file path
     * @param string $content Log file contents
     * @return void
     */
    function writeFeatureLogFile(string $path, string $content): void
    {
        ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
    }
}

if (!function_exists('resetFeatureLogs')) {
    /**
     * Remove feature test log files and reset log configuration.
     *
     * @return void
     */
    function resetFeatureLogs(): void
    {
        foreach (Log::configured() as $channel) {
            Log::drop($channel);
        }

        cleanFeatureLogDirectory();
    }
}

if (!function_exists('restoreFeatureDatabaseBootstrapConnections')) {
    /**
     * Restore bootstrap test datasource configuration after feature test teardown.
     *
     * @return void
     */
    function restoreFeatureDatabaseBootstrapConnections(): void
    {
        if (!getenv('db_dsn')) {
            putenv('db_dsn=sqlite:///:memory:');
        }

        ConnectionManager::setConfig('test', [
            'url' => getenv('db_dsn'),
            'timezone' => 'UTC',
        ]);

        ConnectionManager::alias('test', 'default');
    }
}

if (!function_exists('resetFeatureDatabaseConnections')) {
    /**
     * Drop feature test database connections.
     *
     * @return void
     */
    function resetFeatureDatabaseConnections(): void
    {
        if (array_key_exists('default', ConnectionManager::aliases())) {
            ConnectionManager::dropAlias('default');
        }

        foreach ([
            'default',
            'test',
            'feature_schema',
            'feature_query',
            'feature_mysql',
            'feature_pgsql',
        ] as $connectionName) {
            if (ConnectionManager::getConfig($connectionName) !== null) {
                ConnectionManager::drop($connectionName);
            }
        }
    }
}

if (!function_exists('configureFeatureSchemaConnection')) {
    /**
     * Configure an in-memory SQLite default connection for schema feature tests.
     *
     * @return \Cake\Database\Connection
     */
    function configureFeatureSchemaConnection(): Connection
    {
        resetFeatureDatabaseConnections();

        ConnectionManager::setConfig('default', [
            'className' => Connection::class,
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'prefix' => '',
        ]);

        return ConnectionManager::get('default');
    }
}

if (!function_exists('featureSchemaConnection')) {
    /**
     * Return the active feature schema database connection.
     *
     * @return \Cake\Database\Connection
     */
    function featureSchemaConnection(): Connection
    {
        return ConnectionManager::get('default');
    }
}

if (!function_exists('createFeatureSchemaTable')) {
    /**
     * Create a table in the feature schema database.
     *
     * @param string $sql Create table SQL
     * @return void
     */
    function createFeatureSchemaTable(string $sql): void
    {
        featureSchemaConnection()->execute($sql);
    }
}

if (!function_exists('dropFeatureSchemaTable')) {
    /**
     * Drop a table from the feature schema database.
     *
     * @param string $table Table name
     * @return void
     */
    function dropFeatureSchemaTable(string $table): void
    {
        featureSchemaConnection()->execute('DROP TABLE IF EXISTS ' . $table);
    }
}

if (!function_exists('registerMockFeatureDatabaseConnection')) {
    /**
     * Register a mocked Cake database connection for DatabaseQuery tests.
     *
     * @param string $prefix Table prefix
     * @param array<int, string>|null $expectedQueries Expected SQL statements in order
     * @param class-string<\Cake\Database\Driver> $driverClass Driver backing read-only enforcement
     * @return void
     */
    function registerMockFeatureDatabaseConnection(string $prefix = '', ?array $expectedQueries = null, string $driverClass = Sqlite::class): void
    {
        if (array_key_exists('default', ConnectionManager::aliases())) {
            ConnectionManager::dropAlias('default');
        }

        foreach (['default', 'test'] as $connectionName) {
            if (ConnectionManager::getConfig($connectionName) !== null) {
                ConnectionManager::drop($connectionName);
            }
        }

        ConnectionManager::setConfig('default', function () use ($prefix, $expectedQueries, $driverClass): Connection {
            $statement = Double::for(StatementInterface::class);
            $statement->allows('fetchAll')->with('assoc')->returns([]);

            $connection = Double::for(Connection::class);
            $connection->allows('config')->returns(['prefix' => $prefix]);
            $connection->allows('getDriver')->returns(Double::for($driverClass));
            $connection->allows('begin');
            $connection->allows('rollback')->returns(true);

            if ($expectedQueries === null) {
                $connection->allows('execute')->returns($statement);

                return $connection;
            }

            $setupStatements = match ($driverClass) {
                Mysql::class => ['SET TRANSACTION READ ONLY'],
                Postgres::class => ['SET TRANSACTION READ ONLY'],
                Sqlite::class => ['PRAGMA query_only = ON'],
                default => [],
            };

            $teardownStatements = match ($driverClass) {
                Sqlite::class => ['PRAGMA query_only = OFF'],
                default => [],
            };

            foreach ([...$setupStatements, ...$expectedQueries, ...$teardownStatements] as $sql) {
                $connection->expects('execute')->with($sql)->returns($statement);
            }

            return $connection;
        });
    }
}

if (!function_exists('configureFeatureRouter')) {
    /**
     * Configure Router and base URL for GetAbsoluteUrl tests.
     *
     * @return void
     */
    function configureFeatureRouter(): void
    {
        Configure::write('App.fullBaseUrl', 'http://localhost');
        Router::reload();
        $routes = Router::createRouteBuilder('/');
        $routes->connect('/test', ['controller' => 'Pages', 'action' => 'display', 'home'], ['_name' => 'test.route']);
        $routes->connect('/dashboard', ['controller' => 'Pages', 'action' => 'display', 'home']);
    }
}

if (!function_exists('createMcpServerContext')) {
    /**
     * Build an MCP server context for method handler tests.
     *
     * @param array<int, \Crustum\Mcp\Server\Tool|string> $tools Registered tools
     * @return \Crustum\Mcp\Server\ServerContext
     */
    function createMcpServerContext(array $tools = []): ServerContext
    {
        return new ServerContext(
            supportedProtocolVersions: ['2025-01-01'],
            serverCapabilities: [],
            implementation: new Implementation('test-server', '1.0.0'),
            instructions: 'Test instructions',
            maxPaginationLength: 100,
            defaultPaginationLength: 50,
            tools: $tools,
            resources: [],
            prompts: [],
        );
    }
}

if (!function_exists('createToolRequest')) {
    /**
     * Build a tools/call JSON-RPC request.
     *
     * @param string $toolName Tool name
     * @param array<string, mixed> $arguments Tool arguments
     * @param int|string $id Request identifier
     * @return \Crustum\Mcp\Transport\JsonRpcRequest
     */
    function createToolRequest(string $toolName, array $arguments = [], int|string $id = 1): JsonRpcRequest
    {
        $params = ['name' => $toolName];

        if ($arguments !== []) {
            $params['arguments'] = $arguments;
        }

        return new JsonRpcRequest(
            id: $id,
            method: 'tools/call',
            params: $params,
        );
    }
}

if (!function_exists('prepareFeatureRulesSandbox')) {
    /**
     * Configure an isolated project root and rules repository for feature tests.
     *
     * @return array{originalRoot: string, basePath: string, rulesDir: string, repository: \Crustum\Ignis\Rules\RuleRepository}
     */
    function prepareFeatureRulesSandbox(): array
    {
        Configure::write('Ignis.rules.enabled', true);

        $originalRoot = ProjectRoot::path();
        $basePath = testAppTmpPath('rules-' . uniqid('', true));
        ensureDirectoryExists($basePath);
        ProjectRoot::set($basePath);

        $rulesDir = base_path('.ai/rules');
        $repository = new RuleRepository($rulesDir);

        return [
            'originalRoot' => $originalRoot,
            'basePath' => $basePath,
            'rulesDir' => $rulesDir,
            'repository' => $repository,
        ];
    }
}

if (!function_exists('resetFeatureRulesSandbox')) {
    /**
     * Tear down a feature rules sandbox directory and restore the project root.
     *
     * @param string $basePath Sandbox directory
     * @param string $originalRoot Previous project root
     * @return void
     */
    function resetFeatureRulesSandbox(string $basePath, string $originalRoot): void
    {
        if (is_dir($basePath)) {
            deleteDirectory($basePath);
        }

        ProjectRoot::set($originalRoot);
    }
}

if (!function_exists('featureRuleFiles')) {
    /**
     * List rule markdown files in a rules directory, excluding index.md.
     *
     * @param string $rulesDir Rules directory
     * @return array<int, string>
     */
    function featureRuleFiles(string $rulesDir): array
    {
        $files = glob($rulesDir . '/*.md') ?: [];

        return array_values(array_filter($files, fn (string $file): bool => basename($file) !== 'index.md'));
    }
}
