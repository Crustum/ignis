<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\Database\Driver\Sqlserver;
use Cake\Datasource\ConnectionManager;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\Package;
use Crustum\Inspector\ProjectManager;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

/**
 * Provides runtime and installed package information.
 */
#[IsReadOnly]
class ApplicationInfo extends Tool
{
    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'Get PHP, CakePHP, configured database, and installed package version information.';

    /**
     * Create the application information tool.
     *
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     */
    public function __construct(protected ProjectManager $project)
    {
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Application information
     */
    public function handle(Request $request): Response
    {
        return Response::json([
            'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'cakephp_version' => $this->resolveCakephpVersion(),
            'database_engine' => $this->resolveDatabaseEngine(),
            'packages' => array_map(
                static fn(Package $package): array => [
                    'inspector_name' => PackageRegistry::inspectorName($package->name()),
                    'version' => $package->version(),
                    'package_name' => $package->name(),
                ],
                [
                    ...$this->project->php()->packages()->all(),
                    ...$this->project->js()->packages()->all(),
                ],
            ),
        ]);
    }

    /**
     * Resolve the installed CakePHP version from packages or configuration.
     *
     * @return string
     */
    protected function resolveCakephpVersion(): string
    {
        $package = $this->project->php()->package(PackageRegistry::CAKEPHP);

        if ($package instanceof Package) {
            return $package->version();
        }

        $configuredVersion = Configure::read('Cake.version');

        if (is_string($configuredVersion) && $configuredVersion !== '') {
            return $configuredVersion;
        }

        return 'unknown';
    }

    /**
     * Resolve the database driver name without exposing connection details.
     *
     * @return string
     */
    protected function resolveDatabaseEngine(): string
    {
        try {
            $connection = ConnectionManager::get('default');
        } catch (Throwable) {
            return 'default';
        }

        if (!$connection instanceof Connection) {
            return 'default';
        }

        $driver = $connection->getDriver();

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

        return 'default';
    }
}
