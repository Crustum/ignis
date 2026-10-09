<?php

declare(strict_types=1);

use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Crustum\Ignis\Mcp\Tools\DatabaseConnections;
use Crustum\Ignis\Test\Fixtures\Database\ClickHouseDriver;
use Crustum\Ignis\Test\Fixtures\Database\EngineReportingConnection;
use Crustum\Ignis\Test\Fixtures\Database\OracleOCI;
use Crustum\Mcp\Request;

beforeEach(function (): void {
    ConnectionManager::setConfig('feature_mysql', [
        'className' => Connection::class,
        'driver' => Sqlite::class,
        'database' => ':memory:',
    ]);
    ConnectionManager::setConfig('feature_pgsql', [
        'className' => Connection::class,
        'driver' => Sqlite::class,
        'database' => ':memory:',
    ]);
    ConnectionManager::alias('feature_mysql', 'default');
});

afterEach(function (): void {
    if (array_key_exists('default', ConnectionManager::aliases())) {
        ConnectionManager::dropAlias('default');
    }

    foreach (['feature_mysql', 'feature_pgsql', 'feature_clickhouse', 'feature_oracle', 'feature_engine_config'] as $name) {
        if (ConnectionManager::getConfig($name) !== null) {
            ConnectionManager::drop($name);
        }
    }
});

test('it returns database connections with engine types', function (): void {
    $tool = new DatabaseConnections();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $data): void {
            expect($data['default_connection'])->toBe('feature_mysql')
                ->and($data['connections'])->toContain(
                    ['name' => 'feature_mysql', 'engine' => 'sqlite'],
                    ['name' => 'feature_pgsql', 'engine' => 'sqlite'],
                );
        });
});

test('it returns configured connection names even when only default is set', function (): void {
    ConnectionManager::drop('feature_pgsql');

    $tool = new DatabaseConnections();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $data): void {
            $names = array_column($data['connections'], 'name');

            expect($data['default_connection'])->toBe('feature_mysql')
                ->and($names)->toContain('feature_mysql')
                ->and($names)->not->toContain('feature_pgsql');
        });
});

test('it falls back to default when no alias is set', function (): void {
    ConnectionManager::dropAlias('default');

    $tool = new DatabaseConnections();
    $response = $tool->handle(new Request([]));

    expect($response)->toolJsonContent(function (array $data): void {
        expect($data['default_connection'])->toBe('default');
    });
});

test('it derives engine names from third-party driver classes', function (): void {
    ConnectionManager::setConfig('feature_clickhouse', [
        'className' => Connection::class,
        'driver' => ClickHouseDriver::class,
        'database' => ':memory:',
    ]);
    ConnectionManager::setConfig('feature_oracle', [
        'className' => Connection::class,
        'driver' => OracleOCI::class,
        'database' => ':memory:',
    ]);

    $tool = new DatabaseConnections();
    $response = $tool->handle(new Request([]));

    expect($response)->toolJsonContent(function (array $data): void {
        expect($data['connections'])->toContain(
            ['name' => 'feature_clickhouse', 'engine' => 'clickhouse'],
            ['name' => 'feature_oracle', 'engine' => 'oracleoci'],
        );
    });

    ConnectionManager::drop('feature_clickhouse');
    ConnectionManager::drop('feature_oracle');
});

test('it honors an explicit engine config key', function (): void {
    ConnectionManager::setConfig(
        'feature_engine_config',
        new EngineReportingConnection(['engine' => 'Oracle']),
    );

    $tool = new DatabaseConnections();
    $response = $tool->handle(new Request([]));

    expect($response)->toolJsonContent(function (array $data): void {
        expect($data['connections'])->toContain(
            ['name' => 'feature_engine_config', 'engine' => 'oracle'],
        );
    });

    ConnectionManager::drop('feature_engine_config');
});
