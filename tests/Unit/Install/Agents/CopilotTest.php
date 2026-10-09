<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Crustum\Ignis\Install\Agents\Copilot;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;

beforeEach(function (): void {
    $this->strategyFactory = new DetectionStrategyFactory(freshTestContainer());
});

test('httpMcpServerConfig returns default http config', function (): void {
    $agent = new Copilot($this->strategyFactory);

    expect($agent->httpMcpServerConfig('https://example.com/mcp'))->toBe([
        'type' => 'http',
        'url' => 'https://example.com/mcp',
    ]);
});
