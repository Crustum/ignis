<?php

declare(strict_types=1);

use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\Database\Driver\Sqlserver;
use Cake\Datasource\ConnectionManager;
use Crustum\Ignis\Mcp\Tools\DatabaseSchema\MySQLSchemaDriver;
use Crustum\Ignis\Mcp\Tools\DatabaseSchema\NullSchemaDriver;
use Crustum\Ignis\Mcp\Tools\DatabaseSchema\PostgreSQLSchemaDriver;
use Crustum\Ignis\Mcp\Tools\DatabaseSchema\SchemaDriverFactory;
use Crustum\Ignis\Mcp\Tools\DatabaseSchema\SQLiteSchemaDriver;

beforeEach(function (): void {
    ConnectionManager::setConfig('mysql_test', [
        'className' => Connection::class,
        'driver' => Mysql::class,
        'host' => '127.0.0.1',
        'username' => 'root',
        'password' => '',
        'database' => 'test_db',
    ]);
    ConnectionManager::setConfig('mariadb_test', [
        'className' => Connection::class,
        'driver' => Mysql::class,
        'host' => '127.0.0.1',
        'username' => 'root',
        'password' => '',
        'database' => 'test_db',
    ]);
    ConnectionManager::setConfig('pgsql_test', [
        'className' => Connection::class,
        'driver' => Postgres::class,
        'host' => '127.0.0.1',
        'username' => 'postgres',
        'password' => '',
        'database' => 'test_db',
    ]);
    ConnectionManager::setConfig('sqlite_test', [
        'className' => Connection::class,
        'driver' => Sqlite::class,
        'database' => ':memory:',
    ]);
});

afterEach(function (): void {
    ConnectionManager::drop('mysql_test');
    ConnectionManager::drop('mariadb_test');
    ConnectionManager::drop('pgsql_test');
    ConnectionManager::drop('sqlite_test');

    if (ConnectionManager::getConfig('sqlsrv_test') !== null) {
        ConnectionManager::drop('sqlsrv_test');
    }
});

test('creates MySQLSchemaDriver for mysql connection', function (): void {
    $driver = SchemaDriverFactory::make('mysql_test');

    expect($driver)->toBeInstanceOf(MySQLSchemaDriver::class);
});

test('creates MySQLSchemaDriver for mariadb connection', function (): void {
    $driver = SchemaDriverFactory::make('mariadb_test');

    expect($driver)->toBeInstanceOf(MySQLSchemaDriver::class);
});

test('creates PostgreSQLSchemaDriver for pgsql connection', function (): void {
    $driver = SchemaDriverFactory::make('pgsql_test');

    expect($driver)->toBeInstanceOf(PostgreSQLSchemaDriver::class);
});

test('creates SQLiteSchemaDriver for sqlite connection', function (): void {
    $driver = SchemaDriverFactory::make('sqlite_test');

    expect($driver)->toBeInstanceOf(SQLiteSchemaDriver::class);
});

test('creates NullSchemaDriver for sqlsrv driver', function (): void {
    if (!(new Sqlserver([]))->enabled()) {
        test()->markTestSkipped('SQL Server driver is not installed');
    }

    ConnectionManager::setConfig('sqlsrv_test', [
        'className' => Connection::class,
        'driver' => Sqlserver::class,
        'host' => '127.0.0.1',
        'username' => 'sa',
        'password' => '',
        'database' => 'test_db',
    ]);

    $driver = SchemaDriverFactory::make('sqlsrv_test');

    expect($driver)->toBeInstanceOf(NullSchemaDriver::class);
});
