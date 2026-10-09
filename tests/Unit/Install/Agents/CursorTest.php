<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Crustum\Ignis\Install\Agents\Cursor;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;

beforeEach(function (): void {
    $this->strategyFactory = new DetectionStrategyFactory(freshTestContainer());
});

test('httpMcpServerConfig returns npx mcp-remote config', function (): void {
    $agent = new Cursor($this->strategyFactory);

    expect($agent->httpMcpServerConfig('https://example.com/mcp'))->toBe([
        'command' => 'npx',
        'args' => ['-y', 'mcp-remote', 'https://example.com/mcp'],
    ]);
});
