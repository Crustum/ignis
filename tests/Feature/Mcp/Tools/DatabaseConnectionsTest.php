<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Crustum\Ignis\Mcp\Tools\DatabaseConnections;
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
    Configure::write('Datasources.default', 'feature_mysql');
});

afterEach(function (): void {
    ConnectionManager::drop('feature_mysql');
    ConnectionManager::drop('feature_pgsql');
});

test('it returns database connections', function (): void {
    $tool = new DatabaseConnections();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $data): void {
            expect($data['default_connection'])->toBe('feature_mysql')
                ->and($data['connections'])->toContain('feature_mysql', 'feature_pgsql', 'test');
        });
});

test('it returns configured connection names even when only default is set', function (): void {
    ConnectionManager::drop('feature_pgsql');

    $tool = new DatabaseConnections();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $data): void {
            expect($data['default_connection'])->toBe('feature_mysql')
                ->and($data['connections'])->toContain('feature_mysql')
                ->and($data['connections'])->not->toContain('feature_pgsql');
        });
});
