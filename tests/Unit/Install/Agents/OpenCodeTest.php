<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Crustum\Ignis\Install\Agents\OpenCode;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Mockery;

beforeEach(function (): void {
    $this->strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
});

test('projectDetectionConfig checks for both opencode.jsonc and opencode.json', function (): void {
    $agent = new OpenCode($this->strategyFactory);

    expect($agent->projectDetectionConfig())->toBe([
        'files' => ['opencode.json', 'opencode.jsonc'],
    ]);
});

test('detectInProject returns false when only AGENTS.md exists', function (): void {
    $agent = new OpenCode(detectionStrategyFactory());
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ignis_opencode_' . uniqid();
    mkdir($tempDir);
    touch($tempDir . DIRECTORY_SEPARATOR . 'AGENTS.md');

    try {
        expect($agent->detectInProject($tempDir))->toBeFalse();
    } finally {
        unlink($tempDir . DIRECTORY_SEPARATOR . 'AGENTS.md');
        rmdir($tempDir);
    }
});

test('mcpConfigPath prefers opencode.jsonc when it exists', function (): void {
    $agent = new OpenCode($this->strategyFactory);
    $jsoncPath = ROOT . DS . 'opencode.jsonc';

    touch($jsoncPath);

    try {
        expect($agent->mcpConfigPath())->toBe('opencode.jsonc');
    } finally {
        if (is_file($jsoncPath)) {
            unlink($jsoncPath);
        }
    }
});

test('mcpConfigPath returns opencode.json when jsonc does not exist', function (): void {
    $agent = new OpenCode($this->strategyFactory);

    expect($agent->mcpConfigPath())->toBe('opencode.json');
});

test('httpMcpServerConfig returns remote type config', function (): void {
    $agent = new OpenCode($this->strategyFactory);

    $config = $agent->httpMcpServerConfig('https://example.com/mcp');

    expect($config)->toMatchArray([
        'type' => 'remote',
        'enabled' => true,
        'url' => 'https://example.com/mcp',
    ]);
    expect(json_encode($config['oauth']))->toBe('{}');
});
