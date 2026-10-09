<?php
declare(strict_types=1);

namespace Crustum\Ignis\Test\Fixtures\Database;

use Cake\Database\Driver;
use Cake\Database\DriverFeatureEnum;
use Cake\Database\Schema\SchemaDialect;
use RuntimeException;

/**
 * Base stub for third-party SQL drivers.
 */
abstract class FakeDriverBase extends Driver
{
    /**
     * Establish a connection (no-op stub).
     *
     * @return void
     */
    public function connect(): void
    {
    }

    /**
     * Whether the driver is available (always true for stubs).
     *
     * @return bool
     */
    public function enabled(): bool
    {
        return true;
    }

    /**
     * Disable foreign key SQL (unused stub).
     *
     * @return string
     */
    public function disableForeignKeySQL(): string
    {
        return '';
    }

    /**
     * Enable foreign key SQL (unused stub).
     *
     * @return string
     */
    public function enableForeignKeySQL(): string
    {
        return '';
    }

    /**
     * Schema dialect (never built in tests).
     *
     * @return \Cake\Database\Schema\SchemaDialect
     */
    public function schemaDialect(): SchemaDialect
    {
        throw new RuntimeException('No schema dialect in tests.');
    }

    /**
     * Whether the driver supports a feature (always false for stubs).
     *
     * @param \Cake\Database\DriverFeatureEnum $feature Feature name
     * @return bool
     */
    public function supports(DriverFeatureEnum $feature): bool
    {
        return false;
    }
}
