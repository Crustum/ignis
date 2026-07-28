<?php

declare(strict_types=1);

use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Install\McpWriter;

it('installs ignis mcp successfully', function (): void {
    $agent = Mockery::mock(SupportsMcp::class);
    $agent->shouldReceive('getPhpPath')
        ->once()
        ->andReturn('php');
    $agent->shouldReceive('getCakePath')
        ->once()
        ->andReturn('bin/cake.php');
    $agent->shouldReceive('installMcp')
        ->with('cake-ignis', 'php', ['bin/cake.php', 'ignis', 'mcp'])
        ->once()
        ->andReturn(true);

    $writer = new McpWriter($agent);
    $result = $writer->write();

    expect($result)->toBe(McpWriter::SUCCESS);
});

it('throws exception when ignis mcp installation returns false', function (): void {
    $agent = Mockery::mock(SupportsMcp::class);
    $agent->shouldReceive('getPhpPath')
        ->andReturn('php');
    $agent->shouldReceive('getCakePath')
        ->andReturn('bin/cake.php');
    $agent->shouldReceive('installMcp')
        ->with('cake-ignis', 'php', ['bin/cake.php', 'ignis', 'mcp'])
        ->once()
        ->andReturn(false);

    $writer = new McpWriter($agent);

    expect(fn (): int => $writer->write())
        ->toThrow(RuntimeException::class, 'Failed to install Ignis MCP: could not write configuration');
});

it('throws exception when ignis mcp installation throws exception', function (): void {
    $agent = Mockery::mock(SupportsMcp::class);
    $agent->shouldReceive('getPhpPath')
        ->andReturn('php');
    $agent->shouldReceive('getCakePath')
        ->andReturn('bin/cake.php');
    $agent->shouldReceive('installMcp')
        ->with('cake-ignis', 'php', ['bin/cake.php', 'ignis', 'mcp'])
        ->once()
        ->andThrow(new RuntimeException('Permission denied'));

    $writer = new McpWriter($agent);

    expect(fn (): int => $writer->write())
        ->toThrow(RuntimeException::class, 'Permission denied');
});
