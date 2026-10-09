<?php

declare(strict_types=1);

use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Install\McpWriter;
use JMac\Testing\Double;

it('installs ignis mcp successfully', function (): void {
    $agent = Double::for(SupportsMcp::class);
    $agent->expects('getPhpPath')
        ->returns('php');
    $agent->expects('getCakePath')
        ->returns('bin/cake.php');
    $agent->expects('installMcp')
        ->with('cake-ignis', 'php', ['bin/cake.php', 'ignis', 'mcp'])
        ->returns(true);

    $writer = new McpWriter($agent);
    $result = $writer->write();

    expect($result)->toBe(McpWriter::SUCCESS);
});

it('throws exception when ignis mcp installation returns false', function (): void {
    $agent = Double::for(SupportsMcp::class);
    $agent->allows('getPhpPath')
        ->returns('php');
    $agent->allows('getCakePath')
        ->returns('bin/cake.php');
    $agent->expects('installMcp')
        ->with('cake-ignis', 'php', ['bin/cake.php', 'ignis', 'mcp'])
        ->returns(false);

    $writer = new McpWriter($agent);

    expect(fn (): int => $writer->write())
        ->toThrow(RuntimeException::class, 'Failed to install Ignis MCP: could not write configuration');
});

it('throws exception when ignis mcp installation throws exception', function (): void {
    $agent = Double::for(SupportsMcp::class);
    $agent->allows('getPhpPath')
        ->returns('php');
    $agent->allows('getCakePath')
        ->returns('bin/cake.php');
    $agent->expects('installMcp')
        ->with('cake-ignis', 'php', ['bin/cake.php', 'ignis', 'mcp'])
        ->throws(new RuntimeException('Permission denied'));

    $writer = new McpWriter($agent);

    expect(fn (): int => $writer->write())
        ->toThrow(RuntimeException::class, 'Permission denied');
});
