<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Cake\Core\Configure;
use Crustum\Ignis\Install\Agents\ClaudeCode;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;

beforeEach(function (): void {
    $this->strategyFactory = new DetectionStrategyFactory(freshTestContainer());
});

afterEach(function (): void {
    Configure::delete('Ignis.agents.claude_code');
    Configure::delete('Ignis.executable_paths.php');
});

test('returns default mcp config path', function (): void {
    $agent = new ClaudeCode($this->strategyFactory);

    expect($agent->mcpConfigPath())->toBe('.mcp.json');
});

test('returns configured mcp config path', function (): void {
    Configure::write('Ignis.agents.claude_code.mcp_config_path', '../.mcp.json');

    $agent = new ClaudeCode($this->strategyFactory);

    expect($agent->mcpConfigPath())->toBe('../.mcp.json');
});

test('returns default guidelines path', function (): void {
    $agent = new ClaudeCode($this->strategyFactory);

    expect($agent->guidelinesPath())->toBe('AGENTS.md');
});

test('returns configured guidelines path', function (): void {
    Configure::write('Ignis.agents.claude_code.guidelines_path', '.custom/ignis.md');

    $agent = new ClaudeCode($this->strategyFactory);

    expect($agent->guidelinesPath())->toBe('.custom/ignis.md');
});

test('guidelinesPath returns AGENTS.md when CLAUDE.md does not exist', function (): void {
    $agent = new ClaudeCode($this->strategyFactory);

    expect($agent->guidelinesPath())->toBe('AGENTS.md');
});

test('guidelinesPath prefers CLAUDE.md when it exists', function (): void {
    $path = base_path('CLAUDE.md');
    file_put_contents($path, "# Claude\n");

    try {
        $agent = new ClaudeCode($this->strategyFactory);

        expect($agent->guidelinesPath())->toBe('CLAUDE.md');
    } finally {
        @unlink($path);
    }
});

test('guidelinesPath returns configured path even when CLAUDE.md exists', function (): void {
    Configure::write('Ignis.agents.claude_code.guidelines_path', '.custom/AGENTS.md');

    $path = base_path('CLAUDE.md');
    file_put_contents($path, "# Claude\n");

    try {
        $agent = new ClaudeCode($this->strategyFactory);

        expect($agent->guidelinesPath())->toBe('.custom/AGENTS.md');
    } finally {
        @unlink($path);
    }
});

test('uses relative paths for MCP', function (): void {
    $agent = new ClaudeCode($this->strategyFactory);

    expect($agent->useAbsolutePathForMcp())->toBeFalse();
});

test('returns relative PHP path', function (): void {
    Configure::delete('Ignis.executable_paths.php');

    $agent = new ClaudeCode($this->strategyFactory);

    expect($agent->getPhpPath())->toBe('php');
});

test('returns relative cake path', function (): void {
    $agent = new ClaudeCode($this->strategyFactory);

    expect($agent->getCakePath())->toBe('bin/cake.php');
});

test('httpMcpServerConfig returns default http config', function (): void {
    $agent = new ClaudeCode($this->strategyFactory);

    expect($agent->httpMcpServerConfig('https://example.com/mcp'))->toBe([
        'type' => 'http',
        'url' => 'https://example.com/mcp',
    ]);
});
