<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Cake\Core\Configure;
use Crustum\Ignis\Install\Agents\Codex;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Crustum\Ignis\Install\Enums\McpInstallationStrategy;
use Crustum\Ignis\Install\Enums\Platform;
use Mockery;

beforeEach(function (): void {
    $this->strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
});

afterEach(function (): void {
    Configure::delete('Ignis.agents.codex');
    Configure::delete('Ignis.executable_paths.current_directory');
});

test('returns correct name', function (): void {
    $codex = new Codex($this->strategyFactory);

    expect($codex->name())->toBe('codex');
});

test('returns correct display name', function (): void {
    $codex = new Codex($this->strategyFactory);

    expect($codex->displayName())->toBe('Codex');
});

test('uses FILE-based MCP installation strategy', function (): void {
    $codex = new Codex($this->strategyFactory);

    expect($codex->mcpInstallationStrategy())->toBe(McpInstallationStrategy::FILE);
});

test('returns correct MCP config path', function (): void {
    $codex = new Codex($this->strategyFactory);

    expect($codex->mcpConfigPath())->toBe('.codex/config.toml');
});

test('returns correct MCP config key', function (): void {
    $codex = new Codex($this->strategyFactory);

    expect($codex->mcpConfigKey())->toBe('mcp_servers');
});

test('builds MCP server config without cwd by default', function (): void {
    $codex = new Codex($this->strategyFactory);

    $config = $codex->mcpServerConfig('php', ['bin/cake.php', 'ignis', 'mcp']);

    expect($config)->toHaveKey('command', 'php')
        ->toHaveKey('args', ['bin/cake.php', 'ignis', 'mcp'])
        ->not->toHaveKey('cwd');
});

test('builds MCP server config with config "Ignis.executable_paths.current_directory" override', function (): void {
    Configure::write('Ignis.executable_paths.current_directory', '/Users/developer/projects/app');

    $codex = new Codex($this->strategyFactory);

    $config = $codex->mcpServerConfig('php', ['bin/cake.php', 'ignis', 'mcp']);

    expect($config)->toHaveKey('command', 'php')
        ->toHaveKey('args', ['bin/cake.php', 'ignis', 'mcp'])
        ->toHaveKey('cwd', '/Users/developer/projects/app');
});

test('builds MCP server config with env when provided', function (): void {
    $codex = new Codex($this->strategyFactory);

    $config = $codex->mcpServerConfig('php', ['bin/cake.php'], ['APP_ENV' => 'local']);

    expect($config)->toHaveKey('command', 'php')
        ->toHaveKey('args', ['bin/cake.php'])
        ->not->toHaveKey('cwd')
        ->toHaveKey('env', ['APP_ENV' => 'local']);
});

test('filters empty values from server config', function (): void {
    $codex = new Codex($this->strategyFactory);

    $config = $codex->mcpServerConfig('php', [], []);

    expect($config)->toHaveKey('command', 'php')
        ->not->toHaveKey('cwd')
        ->not->toHaveKey('args')
        ->not->toHaveKey('env');
});

test('includes config.toml in project detection', function (): void {
    $codex = new Codex($this->strategyFactory);

    $detection = $codex->projectDetectionConfig();

    expect($detection['files'])->toContain('.codex/config.toml')
        ->not->toContain('AGENTS.md');
    expect($detection['paths'])->toContain('.codex');
});

test('projectDetectionConfig only uses .codex dir and config.toml', function (): void {
    $codex = new Codex($this->strategyFactory);

    expect($codex->projectDetectionConfig())->toBe([
        'paths' => ['.codex'],
        'files' => ['.codex/config.toml'],
    ]);
});

test('detectInProject returns false when only AGENTS.md exists', function (): void {
    $codex = new Codex(detectionStrategyFactory());
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ignis_codex_' . uniqid();
    mkdir($tempDir);
    touch($tempDir . DIRECTORY_SEPARATOR . 'AGENTS.md');

    try {
        expect($codex->detectInProject($tempDir))->toBeFalse();
    } finally {
        unlink($tempDir . DIRECTORY_SEPARATOR . 'AGENTS.md');
        rmdir($tempDir);
    }
});

test('returns correct guidelines path', function (): void {
    $codex = new Codex($this->strategyFactory);

    expect($codex->guidelinesPath())->toBe('AGENTS.md');
});

test('returns correct skills path', function (): void {
    $codex = new Codex($this->strategyFactory);

    expect($codex->skillsPath())->toBe('.agents/skills');
});

test('httpMcpServerConfig returns npx mcp-remote config', function (): void {
    $codex = new Codex($this->strategyFactory);

    expect($codex->httpMcpServerConfig('https://example.com/mcp'))->toBe([
        'command' => 'npx',
        'args' => ['-y', 'mcp-remote', 'https://example.com/mcp'],
    ]);
});

test('system detection uses which command on Darwin', function (): void {
    $codex = new Codex($this->strategyFactory);

    $config = $codex->systemDetectionConfig(Platform::Darwin);

    expect($config['command'])->toBe('which codex');
});

test('system detection uses which command on Linux', function (): void {
    $codex = new Codex($this->strategyFactory);

    $config = $codex->systemDetectionConfig(Platform::Linux);

    expect($config['command'])->toBe('which codex');
});

test('system detection uses where command on Windows', function (): void {
    $codex = new Codex($this->strategyFactory);

    $config = $codex->systemDetectionConfig(Platform::Windows);

    expect($config['command'])->toBe('cmd /c where codex 2>nul');
});

test('installMcp creates TOML config file', function (): void {
    $configPath = tempAgentConfigPath('config.toml');
    Configure::write('Ignis.agents.codex.mcp_config_path', $configPath);

    $codex = new Codex($this->strategyFactory);

    $result = $codex->installMcp('cake_ignis', 'php', ['bin/cake.php', 'ignis', 'mcp']);

    $capturedContent = mcpFileContents($configPath);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[mcp_servers.cake_ignis]')
        ->and($capturedContent)->toContain('command = "php"')
        ->and($capturedContent)->toContain('args = ["bin/cake.php", "ignis", "mcp"]')
        ->and($capturedContent)->not->toContain('cwd = ');
});
