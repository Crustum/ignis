<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Cake\Core\Configure;
use Crustum\Ignis\Install\Agents\Factory;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Crustum\Ignis\Install\Enums\Platform;
use Mockery;

beforeEach(function (): void {
    $this->strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
});

afterEach(function (): void {
    Configure::delete('Ignis.agents.factory');
});

test('name returns factory', function (): void {
    $agent = new Factory($this->strategyFactory);

    expect($agent->name())->toBe('factory');
});

test('displayName returns Factory Droid', function (): void {
    $agent = new Factory($this->strategyFactory);

    expect($agent->displayName())->toBe('Factory Droid');
});

test('guidelinesPath returns AGENTS.md by default', function (): void {
    $agent = new Factory($this->strategyFactory);

    expect($agent->guidelinesPath())->toBe('AGENTS.md');
});

test('skillsPath returns .factory/skills by default', function (): void {
    $agent = new Factory($this->strategyFactory);

    expect($agent->skillsPath())->toBe('.factory/skills');
});

test('mcpConfigPath returns .factory/mcp.json by default', function (): void {
    $agent = new Factory($this->strategyFactory);

    expect($agent->mcpConfigPath())->toBe('.factory/mcp.json');
});

test('projectDetectionConfig detects via .factory directory', function (): void {
    $agent = new Factory($this->strategyFactory);

    expect($agent->projectDetectionConfig())->toBe([
        'paths' => ['.factory'],
    ]);
});

test('systemDetectionConfig detects droid on macOS and linux', function (Platform $platform): void {
    $agent = new Factory($this->strategyFactory);

    expect($agent->systemDetectionConfig($platform))->toBe([
        'command' => 'command -v droid',
        'paths' => ['~/.factory'],
    ]);
})->with([Platform::Darwin, Platform::Linux]);

test('systemDetectionConfig detects droid on windows', function (): void {
    $agent = new Factory($this->strategyFactory);

    expect($agent->systemDetectionConfig(Platform::Windows))->toBe([
        'command' => 'cmd /c where droid 2>nul',
        'paths' => ['%USERPROFILE%\\.factory'],
    ]);
});

test('mcpServerConfig returns factory stdio config', function (): void {
    $agent = new Factory($this->strategyFactory);

    expect($agent->mcpServerConfig('php', ['bin/cake.php', 'ignis', 'mcp']))->toBe([
        'type' => 'stdio',
        'command' => 'php',
        'args' => ['bin/cake.php', 'ignis', 'mcp'],
    ]);
});

test('mcpServerConfig includes environment variables', function (): void {
    $agent = new Factory($this->strategyFactory);

    expect($agent->mcpServerConfig('herd', ['php', '/path/to/mcp'], ['SITE_PATH' => '/project']))->toBe([
        'type' => 'stdio',
        'command' => 'herd',
        'args' => ['php', '/path/to/mcp'],
        'env' => ['SITE_PATH' => '/project'],
    ]);
});

test('httpMcpServerConfig returns default http config', function (): void {
    $agent = new Factory($this->strategyFactory);

    expect($agent->httpMcpServerConfig('https://example.com/mcp'))->toBe([
        'type' => 'http',
        'url' => 'https://example.com/mcp',
    ]);
});

test('installMcp writes factory project mcp config', function (): void {
    $mcpPath = tempAgentConfigPath('mcp.json');
    Configure::write('Ignis.agents.factory.mcp_config_path', $mcpPath);

    $agent = new Factory($this->strategyFactory);

    expect($agent->installMcp('cake-ignis', 'php', ['bin/cake.php', 'ignis', 'mcp']))->toBeTrue();

    $decoded = json_decode(mcpFileContents($mcpPath), true);

    expect($decoded['mcpServers']['cake-ignis'])->toMatchArray([
        'type' => 'stdio',
        'command' => 'php',
        'args' => ['bin/cake.php', 'ignis', 'mcp'],
    ]);
    expect($decoded['mcpServers']['cake-ignis'])->not->toHaveKey('env');
});
