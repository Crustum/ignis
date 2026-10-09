<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Crustum\Ignis\Install\Agents\OpenCode;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Crustum\Ignis\Support\ProjectRoot;
beforeEach(function (): void {
    $this->strategyFactory = new DetectionStrategyFactory(freshTestContainer());
});

test('projectDetectionConfig checks root and .opencode config files', function (): void {
    $agent = new OpenCode($this->strategyFactory);

    expect($agent->projectDetectionConfig())->toBe([
        'files' => [
            'opencode.json',
            'opencode.jsonc',
            '.opencode/opencode.json',
            '.opencode/opencode.jsonc',
        ],
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

test('mcpConfigPath prefers .opencode/opencode.jsonc when it exists', function (): void {
    $agent = new OpenCode($this->strategyFactory);
    $jsoncPath = ProjectRoot::path() . DS . '.opencode' . DS . 'opencode.jsonc';

    mkdir(dirname($jsoncPath), 0777, true);
    touch($jsoncPath);

    try {
        expect($agent->mcpConfigPath())->toBe('.opencode/opencode.jsonc');
    } finally {
        if (is_file($jsoncPath)) {
            unlink($jsoncPath);
        }

        $dir = dirname($jsoncPath);

        if (is_dir($dir) && count(scandir($dir)) <= 2) {
            rmdir($dir);
        }
    }
});

test('mcpConfigPath prefers opencode.jsonc over opencode.json when it exists', function (): void {
    $agent = new OpenCode($this->strategyFactory);
    $jsoncPath = ProjectRoot::path() . DS . 'opencode.jsonc';

    touch($jsoncPath);

    try {
        expect($agent->mcpConfigPath())->toBe('opencode.jsonc');
    } finally {
        if (is_file($jsoncPath)) {
            unlink($jsoncPath);
        }
    }
});

test('mcpConfigPath defaults to .opencode/opencode.jsonc when no config exists', function (): void {
    $agent = new OpenCode($this->strategyFactory);

    expect($agent->mcpConfigPath())->toBe('.opencode/opencode.jsonc');
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
