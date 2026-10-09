<?php
declare(strict_types=1);

namespace Crustum\Ignis\Test\Fixtures\Database;

use Cake\Datasource\ConnectionInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

/**
 * Non-SQL test connection stub for engine reporting.
 */
class EngineReportingConnection implements ConnectionInterface
{
    /**
     * Constructor.
     *
     * @param array<string, mixed> $connectionConfig Connection config
     */
    public function __construct(
        private readonly array $connectionConfig = [],
    ) {
    }

    /**
     * Get the driver instance.
     *
     * @param string $role Connection role
     * @return object
     */
    public function getDriver(string $role = self::ROLE_WRITE): object
    {
        return new stdClass();
    }

    /**
     * Set a cacher.
     *
     * @param \Psr\SimpleCache\CacheInterface $cacher Cacher object
     * @return $this
     */
    public function setCacher(CacheInterface $cacher)
    {
        return $this;
    }

    /**
     * Get a cacher.
     *
     * @return \Psr\SimpleCache\CacheInterface Cacher object
     */
    public function getCacher(): CacheInterface
    {
        throw new RuntimeException('No cacher in tests.');
    }

    /**
     * Get the configuration name for this connection.
     *
     * @return string
     */
    public function configName(): string
    {
        return 'feature_engine_test';
    }

    /**
     * Get the configuration data used to create the connection.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->connectionConfig;
    }
}
