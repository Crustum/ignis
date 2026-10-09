<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Cake\Core\Configure;
use Crustum\Ignis\Install\Agents\Junie;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;

beforeEach(function (): void {
    $this->strategyFactory = new DetectionStrategyFactory(freshTestContainer());
});

afterEach(function (): void {
    Configure::delete('Ignis.agents.junie');
});

test('httpMcpServerConfig returns npx mcp-remote config', function (): void {
    $agent = new Junie($this->strategyFactory);

    expect($agent->httpMcpServerConfig('https://example.com/mcp'))->toBe([
        'command' => 'npx',
        'args' => ['-y', 'mcp-remote', 'https://example.com/mcp'],
    ]);
});

test('returns default mcp config path', function (): void {
    $agent = new Junie($this->strategyFactory);

    expect($agent->mcpConfigPath())->toBe('.junie/mcp/mcp.json');
});

test('returns configured mcp config path', function (): void {
    Configure::write('Ignis.agents.junie.mcp_config_path', '../.junie/mcp/mcp.json');

    $agent = new Junie($this->strategyFactory);

    expect($agent->mcpConfigPath())->toBe('../.junie/mcp/mcp.json');
});
