<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Cake\Core\Configure;
use Crustum\Ignis\Install\Agents\GrokBuild;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Crustum\Ignis\Install\Enums\McpInstallationStrategy;
use Crustum\Ignis\Install\Enums\Platform;
use Mockery;

beforeEach(function (): void {
    $this->strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
});

afterEach(function (): void {
    Configure::delete('Ignis.agents.grok_build');
});

test('returns correct name', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->name())->toBe('grok_build');
});

test('returns correct display name', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->displayName())->toBe('Grok Build');
});

test('uses FILE-based MCP installation strategy', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->mcpInstallationStrategy())->toBe(McpInstallationStrategy::FILE);
});

test('returns correct MCP config path', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->mcpConfigPath())->toBe('.grok/config.toml');
});

test('returns configured MCP config path', function (): void {
    Configure::write('Ignis.agents.grok_build.mcp_config_path', '.custom/grok.toml');

    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->mcpConfigPath())->toBe('.custom/grok.toml');
});

test('returns correct MCP config key', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->mcpConfigKey())->toBe('mcp_servers');
});

test('builds MCP server config without empty values', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    $config = $agent->mcpServerConfig('php', ['bin/cake.php', 'ignis', 'mcp']);

    expect($config)->toBe([
        'command' => 'php',
        'args' => ['bin/cake.php', 'ignis', 'mcp'],
    ]);
});

test('builds MCP server config with env when provided', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    $config = $agent->mcpServerConfig('php', ['bin/cake.php'], ['APP_ENV' => 'local']);

    expect($config)->toBe([
        'command' => 'php',
        'args' => ['bin/cake.php'],
        'env' => ['APP_ENV' => 'local'],
    ]);
});

test('filters empty values from server config', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    $config = $agent->mcpServerConfig('php', [], []);

    expect($config)->toBe([
        'command' => 'php',
    ]);
});

test('httpMcpServerConfig returns url-only config', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->httpMcpServerConfig('https://example.com/mcp'))->toBe([
        'url' => 'https://example.com/mcp',
    ]);
});

test('projectDetectionConfig only uses .grok dir and config.toml', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->projectDetectionConfig())->toBe([
        'paths' => ['.grok'],
        'files' => ['.grok/config.toml'],
    ]);
});

test('detectInProject returns false when only AGENTS.md exists', function (): void {
    $agent = new GrokBuild(detectionStrategyFactory());
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ignis_grok_build_' . uniqid();
    mkdir($tempDir);
    touch($tempDir . DIRECTORY_SEPARATOR . 'AGENTS.md');

    try {
        expect($agent->detectInProject($tempDir))->toBeFalse();
    } finally {
        unlink($tempDir . DIRECTORY_SEPARATOR . 'AGENTS.md');
        rmdir($tempDir);
    }
});

test('returns correct guidelines path', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->guidelinesPath())->toBe('AGENTS.md');
});

test('returns configured guidelines path', function (): void {
    Configure::write('Ignis.agents.grok_build.guidelines_path', '.custom/AGENTS.md');

    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->guidelinesPath())->toBe('.custom/AGENTS.md');
});

test('returns correct skills path', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->skillsPath())->toBe('.grok/skills');
});

test('returns configured skills path', function (): void {
    Configure::write('Ignis.agents.grok_build.skills_path', '.custom/skills');

    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->skillsPath())->toBe('.custom/skills');
});

test('system detection uses command -v and ~/.grok on Darwin', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->systemDetectionConfig(Platform::Darwin))->toBe([
        'command' => 'command -v grok',
        'paths' => ['~/.grok'],
    ]);
});

test('system detection uses command -v and ~/.grok on Linux', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->systemDetectionConfig(Platform::Linux))->toBe([
        'command' => 'command -v grok',
        'paths' => ['~/.grok'],
    ]);
});

test('system detection uses where and USERPROFILE on Windows', function (): void {
    $agent = new GrokBuild($this->strategyFactory);

    expect($agent->systemDetectionConfig(Platform::Windows))->toBe([
        'command' => 'cmd /c where grok 2>nul',
        'paths' => ['%USERPROFILE%\\.grok'],
    ]);
});

test('installMcp creates TOML config file', function (): void {
    $configPath = tempAgentConfigPath('config.toml');
    Configure::write('Ignis.agents.grok_build.mcp_config_path', $configPath);

    $agent = new GrokBuild($this->strategyFactory);

    $result = $agent->installMcp('cake-ignis', 'php', ['bin/cake.php', 'ignis', 'mcp']);

    $capturedContent = mcpFileContents($configPath);

    expect($result)->toBeTrue()
        ->and($capturedContent)->toContain('[mcp_servers.cake-ignis]')
        ->and($capturedContent)->toContain('command = "php"')
        ->and($capturedContent)->toContain('args = ["bin/cake.php", "ignis", "mcp"]');
});
