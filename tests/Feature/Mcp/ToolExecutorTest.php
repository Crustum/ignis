<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Mcp\ToolExecutor;
use Crustum\Ignis\Mcp\ToolRegistry;
use Crustum\Ignis\Mcp\Tools\DatabaseConnections;
use Crustum\Mcp\Response;

beforeEach(function (): void {
    ToolRegistry::clearCache();
});

test('can execute tool in subprocess', function (): void {
    $executor = Mockery::mock(ToolExecutor::class)->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $executor->shouldReceive('buildCommand')
        ->once()
        ->andReturnUsing(buildToolExecutorSubprocessCommand(...));

    $response = $executor->execute(DatabaseConnections::class, []);

    expect($response)->toBeInstanceOf(Response::class);

    if ($response->isError()) {
        expect(false)->toBeTrue('Tool execution failed with error: ' . $response->content());
    }

    expect($response->isError())->toBeFalse();

    $textContent = (string)$response->content();
    expect($textContent)->toContain('connections');
});

test('rejects unregistered tools', function (): void {
    $executor = new ToolExecutor();
    $response = $executor->execute('NonExistentToolClass');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->isError())->toBeTrue();
});

test('subprocess proves fresh process isolation', function (): void {
    $executor = Mockery::mock(ToolExecutor::class)->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $executor->shouldReceive('buildCommand')
        ->andReturnUsing(fn (): array => [
            PHP_BINARY,
            '-r',
            'echo json_encode(["isError" => false, "content" => [["type" => "text", "text" => (string) getmypid()]]]);',
        ]);

    $response1 = $executor->execute(DatabaseConnections::class, []);
    $response2 = $executor->execute(DatabaseConnections::class, []);

    expect($response1->isError())->toBeFalse()
        ->and($response2->isError())->toBeFalse();

    $pid1 = (int)trim((string)$response1->content());
    $pid2 = (int)trim((string)$response2->content());

    expect($pid1)->toBeGreaterThan(0)->not->toBe(getmypid())
        ->and($pid2)->toBeGreaterThan(0)->not->toBe(getmypid())
        ->and($pid1)->not->toBe($pid2);
});

test('subprocess sees modified autoloaded code changes', function (): void {
    $executor = Mockery::mock(ToolExecutor::class)->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $executor->shouldReceive('buildCommand')
        ->andReturnUsing(buildToolExecutorSubprocessCommand(...));

    $toolPath = pluginSourceFile('src/Mcp/Tools/DatabaseConnections.php');
    $originalContent = file_get_contents($toolPath);

    $cleanup = function () use ($toolPath, $originalContent): void {
        file_put_contents($toolPath, $originalContent);
    };

    try {
        $response1 = $executor->execute(DatabaseConnections::class, []);

        expect($response1->isError())->toBeFalse();
        $responseData1 = json_decode((string)$response1->content(), true);
        expect($responseData1)->toHaveKey('default_connection');

        $modifiedContent = str_replace(
            "'default_connection' => (string)Configure::read('Datasources.default', 'default'),",
            "'default_connection' => 'MODIFIED_BY_TEST',",
            $originalContent,
        );
        file_put_contents($toolPath, $modifiedContent);

        $response2 = $executor->execute(DatabaseConnections::class, []);
        $responseData2 = json_decode((string)$response2->content(), true);

        expect($response2->isError())->toBeFalse()
            ->and($responseData2['default_connection'])->toBe('MODIFIED_BY_TEST');
    } finally {
        $cleanup();
    }
});

test('respects custom timeout parameter', function (): void {
    $executor = Mockery::mock(ToolExecutor::class)->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $executor->shouldReceive('buildCommand')
        ->andReturnUsing(buildToolExecutorSubprocessCommand(...));

    $response = $executor->execute(DatabaseConnections::class, [
        'timeout' => 30,
    ]);

    expect($response->isError())->toBeFalse();
});

test('resolves timeout from argument, then config, then default', function (): void {
    Configure::write('Ignis.mcp.tool_timeout', 300);

    $executor = new ToolExecutor();

    $method = (new ReflectionClass($executor))->getMethod('getTimeout');

    expect($method->invoke($executor, ['timeout' => 60]))->toBe(60)
        ->and($method->invoke($executor, []))->toBe(300);

    Configure::write('Ignis.mcp.tool_timeout');

    expect($method->invoke($executor, []))->toBe(180);

    Configure::delete('Ignis.mcp.tool_timeout');
});

test('output buffering discards stray stdout during tool execution', function (): void {
    $executor = Mockery::mock(ToolExecutor::class)->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $executor->shouldReceive('buildCommand')
        ->andReturnUsing(buildToolExecutorSubprocessCommand(...));

    $toolPath = pluginSourceFile('src/Mcp/Tools/DatabaseConnections.php');
    $originalContent = file_get_contents($toolPath);

    try {
        $modifiedContent = str_replace(
            'public function handle(Request $request): Response',
            "public function handle(Request \$request): Response\n    {\n        echo \"Deprecated: Implicitly marking parameter as nullable\\n\";\n        return \$this->handleOriginal(\$request);\n    }\n\n    public function handleOriginal(Request \$request): Response",
            $originalContent,
        );
        file_put_contents($toolPath, $modifiedContent);

        $response = $executor->execute(DatabaseConnections::class, []);

        expect($response->isError())->toBeFalse();
    } finally {
        file_put_contents($toolPath, $originalContent);
    }
});

test('buildCommand preserves absolute paths with spaces', function (): void {
    Configure::write('Ignis.executable_paths.php', '/Applications/Some App/bin/php');

    $executor = new ToolExecutor();

    $reflection = new ReflectionClass($executor);
    $method = $reflection->getMethod('buildCommand');

    $command = $method->invoke($executor, 'SomeTool', []);

    expect($command[0])->toBe('/Applications/Some App/bin/php');

    Configure::delete('Ignis.executable_paths.php');
});

test('buildCommand splits multi-token wrapper commands', function (): void {
    Configure::write('Ignis.executable_paths.php', 'herd php');

    $executor = new ToolExecutor();

    $reflection = new ReflectionClass($executor);
    $method = $reflection->getMethod('buildCommand');

    $command = $method->invoke($executor, 'SomeTool', []);

    expect($command[0])->toBe('herd')
        ->and($command[1])->toBe('php');

    Configure::delete('Ignis.executable_paths.php');
});

test('buildCommand uses PHP_BINARY when no config is set', function (): void {
    Configure::delete('Ignis.executable_paths.php');

    $executor = new ToolExecutor();

    $reflection = new ReflectionClass($executor);
    $method = $reflection->getMethod('buildCommand');

    $command = $method->invoke($executor, 'SomeTool', []);

    expect($command[0])->toBe(PHP_BINARY);
});

test('clamps timeout values correctly', function (): void {
    $executor = new ToolExecutor();

    $reflection = new ReflectionClass($executor);
    $method = $reflection->getMethod('getTimeout');

    expect($method->invoke($executor, []))->toBe(180);
    expect($method->invoke($executor, ['timeout' => 60]))->toBe(60);
    expect($method->invoke($executor, ['timeout' => 0]))->toBe(1);
    expect($method->invoke($executor, ['timeout' => -5]))->toBe(1);
    expect($method->invoke($executor, ['timeout' => 1000]))->toBe(1000);
});
