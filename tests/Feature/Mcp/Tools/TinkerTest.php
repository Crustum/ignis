<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Core\Container;
use Crustum\Ignis\Mcp\Tools\Tinker;
use Crustum\Ignis\Tinker\TinkerExecutor;
use Crustum\Mcp\Request;

beforeEach(function (): void {
    Configure::write('Ignis.tinker_tool_enabled', true);
});

afterEach(function (): void {
    Configure::delete('Ignis.tinker_tool_enabled');
    set_time_limit(0);
});

test('it is disabled by default via shouldRegister', function (): void {
    Configure::write('Ignis.tinker_tool_enabled', false);

    $tool = new Tinker(new TinkerExecutor());

    expect($tool->shouldRegister())->toBeFalse();
});

test('it executes code through the MCP tool', function (): void {
    $tool = new Tinker(new TinkerExecutor());
    $response = $tool->handle(new Request([
        'code' => 'return 40 + 2;',
        'timeout' => 600,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError();

    $payload = toolResponseJson($response);

    expect($payload['success'])->toBeTrue()
        ->and($payload['result'])->toBe(42);
});

test('it returns an error for empty code', function (): void {
    $tool = new Tinker(new TinkerExecutor());
    $response = $tool->handle(new Request([
        'code' => '',
        'timeout' => 600,
    ]));

    expect($response)->isToolResult()
        ->toolHasError();
});

test('it defines code and timeout schema fields', function (): void {
    $tool = new Tinker(new TinkerExecutor());

    $properties = $tool->toArray()['inputSchema']['properties'] ?? [];

    expect($properties)->toHaveKeys(['code', 'timeout']);
});

test('it can read Configure through the MCP tool', function (): void {
    $tool = new Tinker(new TinkerExecutor());
    $response = $tool->handle(new Request([
        'code' => 'return ' . \Cake\Core\Configure::class . '::read("App.encoding");',
        'timeout' => 600,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContentToMatchArray([
            'success' => true,
            'result' => 'UTF-8',
            'type' => 'string',
        ]);
});

test('it can resolve services from the application container', function (): void {
    $container = new Container();
    $container->addShared('demo.service', fn (): string => 'works');
    Configure::write('app.container', $container);

    $tool = new Tinker(new TinkerExecutor());
    $response = $tool->handle(new Request([
        'code' => 'return ' . \Cake\Core\Configure::class . '::read("app.container")->get("demo.service");',
        'timeout' => 600,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContentToMatchArray([
            'success' => true,
            'result' => 'works',
            'type' => 'string',
        ]);
});

test('it can query the default database connection', function (): void {
    $tool = new Tinker(new TinkerExecutor());
    $response = $tool->handle(new Request([
        'code' => \Cake\Datasource\ConnectionManager::class . '::get("default")->configName()',
        'timeout' => 600,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $data): void {
            expect($data['success'])->toBeTrue()
                ->and($data['result'])->toBe('test')
                ->and($data['type'])->toBe('string');
        });
});
