<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Cake\Core\Configure;
use Crustum\Ignis\Install\Agents\Amp;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Crustum\Ignis\Install\Enums\McpInstallationStrategy;
use Mockery;

beforeEach(function (): void {
    $this->strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
});

afterEach(function (): void {
    Configure::delete('Ignis.agents.amp');
});

test('name returns amp', function (): void {
    $agent = new Amp($this->strategyFactory);

    expect($agent->name())->toBe('amp');
});

test('displayName returns Amp', function (): void {
    $agent = new Amp($this->strategyFactory);

    expect($agent->displayName())->toBe('Amp');
});

test('mcpInstallationStrategy returns FILE', function (): void {
    $agent = new Amp($this->strategyFactory);

    expect($agent->mcpInstallationStrategy())->toBe(McpInstallationStrategy::FILE);
});

test('guidelinesPath returns AGENTS.md by default', function (): void {
    $agent = new Amp($this->strategyFactory);

    expect($agent->guidelinesPath())->toBe('AGENTS.md');
});

test('skillsPath returns .agents/skills by default', function (): void {
    $agent = new Amp($this->strategyFactory);

    expect($agent->skillsPath())->toBe('.agents/skills');
});

test('projectDetectionConfig only uses .amp directory', function (): void {
    $agent = new Amp($this->strategyFactory);

    expect($agent->projectDetectionConfig())->toBe([
        'paths' => ['.amp'],
    ]);
});

test('installMcp with env vars writes directly to settings file', function (): void {
    $settingsPath = tempAgentConfigPath('settings.json');
    Configure::write('Ignis.agents.amp.mcp_config_path', $settingsPath);

    $agent = new Amp($this->strategyFactory);

    expect($agent->installMcp('herd', 'herd php', ['/path/to/mcp'], ['SITE_PATH' => '/project']))->toBeTrue();

    $decoded = json_decode(mcpFileContents($settingsPath), true);

    expect($decoded['amp.mcpServers']['herd']['command'])->toBe('herd');
    expect($decoded['amp.mcpServers']['herd']['args'][0])->toBe('php');
    expect($decoded['amp.mcpServers']['herd']['args'][1])->toBe('/path/to/mcp');
    expect($decoded['amp.mcpServers']['herd']['env']['SITE_PATH'])->toBe('/project');
});

test('installMcp without env vars writes directly to settings file', function (): void {
    $settingsPath = tempAgentConfigPath('settings.json');
    Configure::write('Ignis.agents.amp.mcp_config_path', $settingsPath);

    $agent = new Amp($this->strategyFactory);

    expect($agent->installMcp('cake-ignis', 'php', ['bin/cake.php', 'ignis', 'mcp']))->toBeTrue();

    $decoded = json_decode(mcpFileContents($settingsPath), true);

    expect($decoded['amp.mcpServers']['cake-ignis']['command'])->toBe('php');
    expect($decoded['amp.mcpServers']['cake-ignis']['args'])->toBe(['bin/cake.php', 'ignis', 'mcp']);
    expect($decoded['amp.mcpServers']['cake-ignis'])->not->toHaveKey('env');
});

test('installHttpMcp writes url config directly to settings file', function (): void {
    $settingsPath = tempAgentConfigPath('settings.json');
    Configure::write('Ignis.agents.amp.mcp_config_path', $settingsPath);

    $agent = new Amp($this->strategyFactory);

    expect($agent->installHttpMcp('remote-mcp', 'https://example.com/mcp'))->toBeTrue();

    $decoded = json_decode(mcpFileContents($settingsPath), true);

    expect($decoded['amp.mcpServers']['remote-mcp']['url'])->toBe('https://example.com/mcp');
});

test('installMcp overwrites existing amp server config while preserving unrelated settings', function (): void {
    $settingsPath = tempAgentConfigPath('settings.json');
    Configure::write('Ignis.agents.amp.mcp_config_path', $settingsPath);

    $existingConfig = json_encode([
        'amp.defaultVisibility' => 'workspace',
        'amp.mcpServers' => [
            'cake-ignis' => [
                'command' => 'old-php',
                'args' => ['old-bin/cake', 'old:command'],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    file_put_contents($settingsPath, (string)$existingConfig);

    $agent = new Amp($this->strategyFactory);

    expect($agent->installMcp('cake-ignis', 'php', ['bin/cake.php', 'ignis', 'mcp']))->toBeTrue();

    $decoded = json_decode(mcpFileContents($settingsPath), true);

    expect($decoded['amp.defaultVisibility'])->toBe('workspace');
    expect($decoded['amp.mcpServers']['cake-ignis']['command'])->toBe('php');
    expect($decoded['amp.mcpServers']['cake-ignis']['args'])->toBe(['bin/cake.php', 'ignis', 'mcp']);
});

test('installMcp returns false when existing settings json is invalid', function (): void {
    $settingsPath = tempAgentConfigPath('settings.json');
    Configure::write('Ignis.agents.amp.mcp_config_path', $settingsPath);
    file_put_contents($settingsPath, '{invalid json');

    $agent = new Amp($this->strategyFactory);

    expect($agent->installMcp('cake-ignis', 'php', ['bin/cake.php', 'ignis', 'mcp']))->toBeFalse();
});
