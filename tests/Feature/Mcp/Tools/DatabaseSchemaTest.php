<?php

declare(strict_types=1);

use Crustum\Ignis\Mcp\Tools\DatabaseSchema;
use Crustum\Mcp\Request;

beforeEach(function (): void {
    configureFeatureSchemaConnection();

    dropFeatureSchemaTable('examples');
    dropFeatureSchemaTable('users');

    createFeatureSchemaTable(
        'CREATE TABLE examples (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL)',
    );
});

test('it returns structured database schema', function (): void {
    $tool = new DatabaseSchema();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContentToMatchArray([
            'engine' => 'sqlite',
        ])
        ->toolJsonContent(function (array $schemaArray): void {
            expect($schemaArray)->toHaveKey('tables')
                ->and($schemaArray['tables'])->toHaveKey('examples')
                ->and($schemaArray)->not->toHaveKey('views')
                ->and($schemaArray)->not->toHaveKey('routines');

            $exampleTable = $schemaArray['tables']['examples'];
            expect($exampleTable)->toHaveKeys(['columns', 'indexes', 'foreign_keys', 'triggers', 'check_constraints'])
                ->and($exampleTable['columns'])->toHaveKeys(['id', 'name'])
                ->and($exampleTable['columns']['id']['type'])->toContain('integer')
                ->and($exampleTable['columns']['name']['type'])->toMatch('/string|varchar/i')
                ->and($exampleTable['columns']['id'])->not->toHaveKey('nullable')
                ->and($exampleTable['columns']['id'])->not->toHaveKey('auto_increment')
                ->and($exampleTable['columns']['id'])->not->toHaveKey('default');
        });
});

test('it includes column details when include_column_details is true', function (): void {
    $tool = new DatabaseSchema();
    $response = $tool->handle(new Request(['include_column_details' => true]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $schemaArray): void {
            $exampleTable = $schemaArray['tables']['examples'];
            expect($exampleTable['columns'])->toHaveKeys(['id', 'name'])
                ->and($exampleTable['columns']['id']['type'])->toContain('integer')
                ->and($exampleTable['columns']['id']['nullable'])->toBeBool()
                ->and($exampleTable['columns']['id']['auto_increment'])->toBeTrue()
                ->and($exampleTable['columns']['id'])->toHaveKey('default')
                ->and($exampleTable['columns']['name']['nullable'])->toBeFalse()
                ->and($exampleTable['columns']['name']['auto_increment'])->toBeFalse();
        });
});

test('it filters tables by name', function (): void {
    createFeatureSchemaTable('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(255) NOT NULL)');

    $tool = new DatabaseSchema();

    $response = $tool->handle(new Request(['filter' => 'example']));
    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $schemaArray): void {
            expect($schemaArray['tables'])->toHaveKey('examples')
                ->and($schemaArray['tables'])->not->toHaveKey('users');
        });

    $response = $tool->handle(new Request(['filter' => 'user']));
    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $schemaArray): void {
            expect($schemaArray['tables'])->toHaveKey('users')
                ->and($schemaArray['tables'])->not->toHaveKey('examples');
        });
});

test('it includes views when include_views is true', function (): void {
    $tool = new DatabaseSchema();
    $response = $tool->handle(new Request(['include_views' => true]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $schemaArray): void {
            expect($schemaArray)->toHaveKey('views')
                ->and($schemaArray)->toHaveKey('tables')
                ->and($schemaArray)->not->toHaveKey('routines');
        });
});

test('it includes routines when include_routines is true', function (): void {
    $tool = new DatabaseSchema();
    $response = $tool->handle(new Request(['include_routines' => true]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $schemaArray): void {
            expect($schemaArray)->toHaveKey('routines')
                ->and($schemaArray['routines'])->toHaveKeys(['stored_procedures', 'functions', 'sequences'])
                ->and($schemaArray)->toHaveKey('tables')
                ->and($schemaArray)->not->toHaveKey('views');
        });
});

test('it includes both views and routines when both are true', function (): void {
    $tool = new DatabaseSchema();
    $response = $tool->handle(new Request(['include_views' => true, 'include_routines' => true]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $schemaArray): void {
            expect($schemaArray)->toHaveKey('views')
                ->and($schemaArray)->toHaveKey('routines')
                ->and($schemaArray)->toHaveKey('tables')
                ->and($schemaArray)->toHaveKey('engine');
        });
});

test('it returns only table names and column types in summary mode', function (): void {
    $tool = new DatabaseSchema();
    $response = $tool->handle(new Request(['summary' => true]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $schemaArray): void {
            expect($schemaArray)->toHaveKey('engine')
                ->and($schemaArray)->toHaveKey('tables')
                ->and($schemaArray)->not->toHaveKey('views')
                ->and($schemaArray)->not->toHaveKey('routines');

            $exampleTable = $schemaArray['tables']['examples'];
            expect($exampleTable)->toBeArray()
                ->and($exampleTable)->toHaveKeys(['id', 'name'])
                ->and($exampleTable['id'])->toContain('integer')
                ->and($exampleTable['name'])->toMatch('/string|varchar/i')
                ->and($exampleTable)->not->toHaveKey('columns')
                ->and($exampleTable)->not->toHaveKey('indexes')
                ->and($exampleTable)->not->toHaveKey('foreign_keys');
        });
});

test('it filters tables in summary mode', function (): void {
    createFeatureSchemaTable('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(255) NOT NULL)');

    $tool = new DatabaseSchema();
    $response = $tool->handle(new Request(['summary' => true, 'filter' => 'user']));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $schemaArray): void {
            expect($schemaArray['tables'])->toHaveKey('users')
                ->and($schemaArray['tables'])->not->toHaveKey('examples');

            expect($schemaArray['tables']['users'])->toHaveKeys(['id', 'email'])
                ->and($schemaArray['tables']['users']['id'])->toContain('integer')
                ->and($schemaArray['tables']['users']['email'])->toMatch('/string|varchar/i');
        });
});
