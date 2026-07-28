<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Mcp\ToolRegistry;
use Crustum\Ignis\Mcp\Tools\DatabaseConnections;
use Crustum\Ignis\Mcp\Tools\DatabaseQuery;
use Crustum\Ignis\Test\Fixtures\ThrowingTool;

beforeEach(function (): void {
    ToolRegistry::clearCache();
    Configure::write('App.name', 'TestApp');
    Configure::write('Ignis.mcp.tools.exclude', []);
});

afterEach(function (): void {
    Configure::delete('Ignis.mcp.tools.exclude');
    Configure::delete('Ignis.mcp.tools.include');
    ToolRegistry::clearCache();
});

it('exits with error when the tool class is not in the registry', function (): void {
    $this->exec('ignis execute-tool App\\Fake\\NonExistentTool ' . base64_encode('{}'));

    $this->assertExitError();
    $this->assertErrorContains('Tool not registered or not allowed');
});

it('exits with error when the tool is in the exclude config', function (): void {
    Configure::write('Ignis.mcp.tools.exclude', [DatabaseConnections::class]);
    ToolRegistry::clearCache();

    $this->exec('ignis execute-tool ' . DatabaseConnections::class . ' ' . base64_encode('{}'));

    $this->assertExitError();
    $this->assertErrorContains('Tool not registered or not allowed');
});

it('exits with error when decoded arguments contain invalid JSON', function (): void {
    $this->exec('ignis execute-tool ' . DatabaseConnections::class . ' ' . base64_encode('{not valid json'));

    $this->assertExitError();
    $this->assertErrorContains('Invalid arguments format');
});

it('outputs JSON with isError false on successful tool execution', function (): void {
    $this->exec('ignis execute-tool ' . DatabaseConnections::class . ' ' . base64_encode('{}'));

    $this->assertExitSuccess();

    $json = json_decode($this->_out->messages()[0] ?? '', true);

    expect($json)->toHaveKeys(['isError', 'content'])
        ->and($json['isError'])->toBeFalse();
});

it('outputs JSON with isError true when the tool returns an error response', function (): void {
    $this->exec('ignis execute-tool ' . DatabaseQuery::class . ' ' . base64_encode(json_encode([
        'query' => 'DELETE FROM users',
    ])));

    $json = json_decode($this->_out->messages()[0] ?? '', true);

    expect($json['isError'])->toBeTrue();
});

it('catches tool exceptions and outputs error JSON with failure exit code', function (): void {
    Configure::write('Ignis.mcp.tools.include', [ThrowingTool::class]);
    ToolRegistry::clearCache();
    $this->mockService(ThrowingTool::class, static fn (): ThrowingTool => new ThrowingTool());

    $this->exec('ignis execute-tool ' . ThrowingTool::class . ' ' . base64_encode('{}'));

    $this->assertExitError();
    $this->assertErrorContains('Intentional test exception');
});
