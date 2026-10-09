<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Cake\Core\Configure;
use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Install\Agents\Pi;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;

beforeEach(function (): void {
    $this->strategyFactory = new DetectionStrategyFactory(freshTestContainer());
});

afterEach(function (): void {
    Configure::delete('Ignis.agents.pi');
});

test('implements MCP support', function (): void {
    $agent = new Pi($this->strategyFactory);

    expect($agent)->toBeInstanceOf(SupportsMcp::class);
});

test('uses AGENTS.md, .pi/skills, and .pi/mcp.json defaults', function (): void {
    $agent = new Pi($this->strategyFactory);

    expect($agent->guidelinesPath())->toBe('AGENTS.md')
        ->and($agent->skillsPath())->toBe('.pi/skills')
        ->and($agent->mcpConfigPath())->toBe('.pi/mcp.json');
});

test('returns configured mcp config path', function (): void {
    Configure::write('Ignis.agents.pi.mcp_config_path', '.custom/pi-mcp.json');

    $agent = new Pi($this->strategyFactory);

    expect($agent->mcpConfigPath())->toBe('.custom/pi-mcp.json');
});
