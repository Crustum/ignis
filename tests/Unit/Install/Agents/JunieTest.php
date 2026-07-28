<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Crustum\Ignis\Install\Agents\Junie;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Mockery;

beforeEach(function (): void {
    $this->strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
});

test('httpMcpServerConfig returns npx mcp-remote config', function (): void {
    $agent = new Junie($this->strategyFactory);

    expect($agent->httpMcpServerConfig('https://example.com/mcp'))->toBe([
        'command' => 'npx',
        'args' => ['-y', 'mcp-remote', 'https://example.com/mcp'],
    ]);
});
