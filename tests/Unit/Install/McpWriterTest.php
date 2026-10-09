<?php

declare(strict_types=1);

use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Install\McpWriter;
use JMac\Testing\Double;

beforeEach(function (): void {
    $this->wslDistroName = getenv('WSL_DISTRO_NAME');
    $this->isWsl = getenv('IS_WSL');
    putenv('WSL_DISTRO_NAME=');
    putenv('IS_WSL=');
});

afterEach(function (): void {
    if ($this->wslDistroName === false) {
        putenv('WSL_DISTRO_NAME');
    } else {
        putenv('WSL_DISTRO_NAME=' . $this->wslDistroName);
    }

    if ($this->isWsl === false) {
        putenv('IS_WSL');
    } else {
        putenv('IS_WSL=' . $this->isWsl);
    }
});

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

it('installs ignis mcp via wsl.exe when running inside WSL', function (): void {
    putenv('WSL_DISTRO_NAME=Ubuntu');

    try {
        $agent = Double::for(SupportsMcp::class);
        $agent->expects('getPhpPath')
            ->returns('/usr/bin/php');
        $agent->expects('getCakePath')
            ->returns('bin/cake.php');
        $agent->expects('installMcp')
            ->with('cake-ignis', 'wsl.exe', ['/usr/bin/php', 'bin/cake.php', 'ignis', 'mcp'])
            ->returns(true);

        $writer = new McpWriter($agent);

        expect($writer->write())->toBe(McpWriter::SUCCESS);
    } finally {
        putenv('WSL_DISTRO_NAME=');
    }
});
