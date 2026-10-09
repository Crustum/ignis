<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Mcp\Methods\CallToolWithExecutor;
use Crustum\Ignis\Mcp\ToolExecutor;
use Crustum\Ignis\Mcp\Tools\DatabaseConnections;
use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Transport\JsonRpcRequest;
use JMac\Testing\Double;

test('throws JsonRpcException when name parameter is missing', function (): void {
    $method = new CallToolWithExecutor(new ToolExecutor());
    $context = createMcpServerContext();

    $request = new JsonRpcRequest(id: 1, method: 'tools/call', params: []);

    $method->handle($request, $context);
})->throws(JsonRpcException::class, 'Missing [name] parameter.', -32602);

test('throws JsonRpcException when tool does not exist', function (): void {
    $method = new CallToolWithExecutor(new ToolExecutor());
    $context = createMcpServerContext([DatabaseConnections::class]);

    $method->handle(createToolRequest('non-existent-tool'), $context);
})->throws(JsonRpcException::class, 'Tool [non-existent-tool] not found.', -32602);

test('successful tool execution returns proper response', function (): void {
    $executor = Double::for(ToolExecutor::class);
    $executor->expects('execute')
        ->with(DatabaseConnections::class, [])
        ->returns(Response::text('Success result'));

    $method = new CallToolWithExecutor($executor);
    $context = createMcpServerContext([DatabaseConnections::class]);

    $response = $method->handle(createToolRequest('database-connections', id: 42), $context);

    expect($response->toArray())
        ->toMatchArray(['jsonrpc' => '2.0', 'id' => 42])
        ->toHaveKey('result.content')
        ->toHaveKey('result.isError', false);
});

test('tool execution exceptions are caught and returned as error responses', function (): void {
    $executor = Double::for(ToolExecutor::class);
    $executor->expects('execute')
        ->with(DatabaseConnections::class, [])
        ->throws(new RuntimeException('Database connection failed'));

    $method = new CallToolWithExecutor($executor);
    $context = createMcpServerContext([DatabaseConnections::class]);

    $response = $method->handle(createToolRequest('database-connections'), $context);

    expect($response->toArray())
        ->toHaveKey('result.isError', true)
        ->and($response->toArray()['result']['content'][0]['text'])
        ->toContain('Tool execution error: Database connection failed');
});

test('arguments are properly passed to executor', function (): void {
    $expectedArgs = ['database' => 'default'];

    $executor = Double::for(ToolExecutor::class);
    $executor->expects('execute')
        ->with(DatabaseConnections::class, $expectedArgs)
        ->returns(Response::text('{"connections":["default"]}'));

    $method = new CallToolWithExecutor($executor);
    $context = createMcpServerContext([DatabaseConnections::class]);

    $response = $method->handle(createToolRequest('database-connections', $expectedArgs), $context);

    expect($response->toArray())->toHaveKey('result.isError', false);
});
