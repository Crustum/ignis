<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Mcp;

use Crustum\Ignis\Install\Mcp\TomlFileWriter;

it('creates the new TOML file with the correct structure', function (): void {
    $path = prepareMcpFile(false, '', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('cake_ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
            'cwd' => '/path/to/project',
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[mcp_servers.cake_ignis]')
        ->and($capturedContent)->toContain('command = "php"')
        ->and($capturedContent)->toContain('args = ["bin/cake.php", "ignis", "mcp"]')
        ->and($capturedContent)->toContain('cwd = "/path/to/project"');
});

it('creates a new file with base config options', function (): void {
    $path = prepareMcpFile(false, '', '.toml');

    $result = (new TomlFileWriter($path, [
        'model' => 'gpt-4',
        'sandbox_mode' => 'workspace-write',
    ]))
        ->configKey('mcp_servers')
        ->addServerConfig('it', ['command' => 'php'])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('model = "gpt-4"')
        ->and($capturedContent)->toContain('sandbox_mode = "workspace-write"')
        ->and($capturedContent)->toContain('[mcp_servers.it]');
});

it('handles nested env table correctly', function (): void {
    $path = prepareMcpFile(false, '', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('mysql', [
            'command' => 'npx',
            'args' => ['@mysql/mcp-server'],
            'env' => [
                'DB_HOST' => 'localhost',
                'DB_PORT' => '3306',
            ],
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[mcp_servers.mysql]')
        ->and($capturedContent)->toContain('command = "npx"')
        ->and($capturedContent)->toContain('[mcp_servers.mysql.env]')
        ->and($capturedContent)->toContain('DB_HOST = "localhost"')
        ->and($capturedContent)->toContain('DB_PORT = "3306"');
});

it('appends to an existing TOML file preserving other servers', function (): void {
    $path = prepareMcpFile(true, fixtureContent('codex-config.toml'), '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('cake_ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
            'cwd' => '/new/path',
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[mcp_servers.existing_server]')
        ->and($capturedContent)->toContain('[mcp_servers.cake_ignis]')
        ->and($capturedContent)->toContain('command = "npm"')
        ->and($capturedContent)->toContain('command = "php"');
});

it('updates the existing server by removing an old section and appending a new one', function (): void {
    $existingContent = <<<'TOML'
model = "o3"

[mcp_servers.cake_ignis]
command = "php"
args = ["bin/cake.php", "ignis", "mcp"]
cwd = "/old/path"

[mcp_servers.other_server]
command = "npm"
args = ["start"]
TOML;

    $path = prepareMcpFile(true, $existingContent, '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('cake_ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
            'cwd' => '/new/path',
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[mcp_servers.other_server]')
        ->and($capturedContent)->toContain('[mcp_servers.cake_ignis]')
        ->and($capturedContent)->toContain('cwd = "/new/path"')
        ->and($capturedContent)->not->toContain('cwd = "/old/path"');
});

it('filters empty values from server config', function (): void {
    $path = prepareMcpFile(false, '', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('it', [
            'command' => 'php',
            'args' => [],
            'cwd' => '/path',
            'env' => [],
            'empty_string' => '',
            'null_value' => null,
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('command = "php"')
        ->and($capturedContent)->toContain('cwd = "/path"')
        ->and($capturedContent)->not->toContain('args')
        ->and($capturedContent)->not->toContain('[mcp_servers.it.env]')
        ->and($capturedContent)->not->toContain('empty_string')
        ->and($capturedContent)->not->toContain('null_value');
});

it('handles multiple servers in the same file', function (): void {
    $path = prepareMcpFile(false, '', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('server1', [
            'command' => 'cmd1',
            'cwd' => '/path1',
        ])
        ->addServerConfig('server2', [
            'command' => 'cmd2',
            'cwd' => '/path2',
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[mcp_servers.server1]')
        ->and($capturedContent)->toContain('[mcp_servers.server2]')
        ->and($capturedContent)->toContain('command = "cmd1"')
        ->and($capturedContent)->toContain('command = "cmd2"');
});

it('removes server with env subtable when updating', function (): void {
    $existingContent = <<<'TOML'
[mcp_servers.cake_ignis]
command = "php"
args = ["bin/cake.php", "ignis", "mcp"]

[mcp_servers.cake_ignis.env]
APP_ENV = "local"

[mcp_servers.other]
command = "npm"
TOML;

    $path = prepareMcpFile(true, $existingContent, '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('cake_ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
            'cwd' => '/updated/path',
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[mcp_servers.other]')
        ->and($capturedContent)->toContain('[mcp_servers.cake_ignis]')
        ->and($capturedContent)->toContain('cwd = "/updated/path"')
        ->and($capturedContent)->not->toContain('APP_ENV');
});

it('preserves full codex config with top-level settings', function (): void {
    $existingContent = <<<'TOML'
model = "o3"
approval_policy = "on-request"
sandbox_mode = "workspace-write"

[mcp_servers.context7]
command = "npx"
args = ["-y", "@upstash/context7-mcp"]
TOML;

    $path = prepareMcpFile(true, $existingContent, '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('cake_ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
            'cwd' => '/project',
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('model = "o3"')
        ->and($capturedContent)->toContain('approval_policy = "on-request"')
        ->and($capturedContent)->toContain('sandbox_mode = "workspace-write"')
        ->and($capturedContent)->toContain('[mcp_servers.context7]')
        ->and($capturedContent)->toContain('[mcp_servers.cake_ignis]');
});

it('uses a custom config key', function (): void {
    $path = prepareMcpFile(false, '', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('servers')
        ->addServerConfig('cake_ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[servers.cake_ignis]')
        ->and($capturedContent)->not->toContain('[mcp_servers');
});

it('handles special characters in string values', function (): void {
    $path = prepareMcpFile(false, '', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('it', [
            'command' => 'node',
            'args' => ['--eval', 'console.log("hello")'],
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('args = ["--eval", "console.log(\\"hello\\")"]');
});

it('handles paths with spaces', function (): void {
    $path = prepareMcpFile(false, '', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('cake_ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
            'cwd' => '/Users/My User/My Projects/app',
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('cwd = "/Users/My User/My Projects/app"');
});

it('handles windows-style paths with backslashes', function (): void {
    $path = prepareMcpFile(false, '', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('cake_ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
            'cwd' => 'C:\\Users\\Developer\\Projects\\my-app',
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('cwd = "C:\\\\Users\\\\Developer\\\\Projects\\\\my-app"');
});

it('repeated updates do not accumulate blank lines', function (): void {
    $existingContent = <<<'TOML'
model = "o3"

[mcp_servers.cake_ignis]
command = "php"
args = ["bin/cake.php", "ignis", "mcp"]
cwd = "/old/path"
TOML;

    $path = prepareMcpFile(true, $existingContent, '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('cake_ignis', [
            'command' => 'php',
            'args' => ['bin/cake.php', 'ignis', 'mcp'],
            'cwd' => '/new/path',
        ])
        ->save();

    $capturedContent = mcpFileContents($path);
    $eol = PHP_EOL;

    expect($result)->toBeTrue()
        ->and($capturedContent)->not->toMatch('/(\r?\n){3,}/')
        ->and($capturedContent)->toContain("model = \"o3\"{$eol}{$eol}[mcp_servers.cake_ignis]");
});

it('formats boolean values correctly', function (): void {
    $path = prepareMcpFile(false, '', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('it', [
            'command' => 'php',
            'enabled' => true,
            'disabled' => false,
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('enabled = true')
        ->and($capturedContent)->toContain('disabled = false');
});

it('formats numeric values correctly', function (): void {
    $path = prepareMcpFile(false, '', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('it', [
            'command' => 'php',
            'timeout' => 30,
            'retries' => 0,
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('timeout = 30')
        ->and($capturedContent)->toContain('retries = 0');
});

it('handles server names with regex special characters', function (): void {
    $existingContent = <<<'TOML'
[mcp_servers.my-server.v2]
command = "old"

[mcp_servers.other]
command = "npm"
TOML;

    $path = prepareMcpFile(true, $existingContent, '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('my-server.v2', [
            'command' => 'new',
        ])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[mcp_servers.other]')
        ->and($capturedContent)->toContain('[mcp_servers.my-server.v2]')
        ->and($capturedContent)->toContain('command = "new"')
        ->and($capturedContent)->not->toContain('command = "old"');
});

it('treats an empty file as a new file', function (): void {
    $path = prepareMcpFile(true, '', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('it', ['command' => 'php'])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[mcp_servers.it]')
        ->and($capturedContent)->toContain('command = "php"');
});

it('treats a file with only whitespace as a new file', function (): void {
    $path = prepareMcpFile(true, '  ', '.toml');

    $result = (new TomlFileWriter($path))
        ->configKey('mcp_servers')
        ->addServerConfig('it', ['command' => 'php'])
        ->save();

    $capturedContent = mcpFileContents($path);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[mcp_servers.it]')
        ->and($capturedContent)->toContain('command = "php"');
});
