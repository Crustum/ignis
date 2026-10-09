<?php

declare(strict_types=1);

use Cake\Database\Connection;
use Cake\Database\StatementInterface;
use Cake\Datasource\ConnectionManager;
use Crustum\Ignis\Mcp\Tools\DatabaseSchema\MySQLSchemaDriver;
use JMac\Testing\Double;

/**
 * Register a mocked default connection with scripted execute behavior.
 *
 * @param callable $script Receives the mocked connection for expectations
 * @return void
 */
function mockSchemaDriverConnection(callable $script): void
{
    if (array_key_exists('default', ConnectionManager::aliases())) {
        ConnectionManager::dropAlias('default');
    }

    foreach (['default', 'test'] as $connectionName) {
        if (ConnectionManager::getConfig($connectionName) !== null) {
            ConnectionManager::drop($connectionName);
        }
    }

    ConnectionManager::setConfig('default', function () use ($script): Connection {
        $connection = Double::for(Connection::class);
        $script($connection);

        return $connection;
    });
}

it('returns native check constraints when the engine supports them', function (): void {
    $statement = Double::for(StatementInterface::class);
    $statement->allows('fetchAll')->with('assoc')->returns([
        ['CONSTRAINT_NAME' => 'chk_price', 'CHECK_CLAUSE' => '`price` > 0'],
    ]);

    mockSchemaDriverConnection(function (Connection $connection) use ($statement): void {
        $connection->expects('execute')->returns($statement);
    });

    expect((new MySQLSchemaDriver())->getCheckConstraints('orders'))->toBe([
        ['CONSTRAINT_NAME' => 'chk_price', 'CHECK_CLAUSE' => '`price` > 0'],
    ]);
});

it('falls back to the table constraints join when the native query fails', function (): void {
    $statement = Double::for(StatementInterface::class);
    $statement->allows('fetchAll')->with('assoc')->returns([
        ['CONSTRAINT_NAME' => 'chk_price', 'CHECK_CLAUSE' => '`price` > 0'],
    ]);

    mockSchemaDriverConnection(function (Connection $connection) use ($statement): void {
        $connection->expects('execute')
            ->times(2)
            ->resolves(function (string $sql) use ($statement): StatementInterface {
                if (!str_contains($sql, 'TABLE_CONSTRAINTS')) {
                    throw new RuntimeException('Unknown column');
                }

                return $statement;
            });
    });

    expect((new MySQLSchemaDriver())->getCheckConstraints('orders'))->toBe([
        ['CONSTRAINT_NAME' => 'chk_price', 'CHECK_CLAUSE' => '`price` > 0'],
    ]);
});
