<?php

declare(strict_types=1);

use Crustum\Ignis\Mcp\Tools\DatabaseSchema\NullSchemaDriver;

beforeEach(function (): void {
    configureFeatureSchemaConnection();

    dropFeatureSchemaTable('users');

    createFeatureSchemaTable(
        'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL)',
    );
});

test('returns tables from the schema collection', function (): void {
    $tables = (new NullSchemaDriver('default'))->getTables();

    expect(array_column($tables, 'name'))->toBe(['users']);
});

test('returns empty list for an unknown connection', function (): void {
    expect((new NullSchemaDriver('missing'))->getTables())->toBe([]);
});
