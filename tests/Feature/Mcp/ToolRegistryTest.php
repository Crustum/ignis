<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Mcp\Tools\ApplicationInfo;
use Crustum\Ignis\Mcp\ToolRegistry;
use Crustum\Ignis\Test\Fixtures\ThrowingTool;

afterEach(function (): void {
    Configure::delete('Ignis.mcp.tools.include');
    Configure::delete('Ignis.mcp.tools.exclude');
    ToolRegistry::clearCache();
});

it('can discover available tools', function (): void {
    $tools = ToolRegistry::getAvailableTools();

    expect($tools)->toBeArray()
        ->and($tools)->toContain(ApplicationInfo::class);
});

it('can check if the tool is allowed', function (): void {
    expect(ToolRegistry::isToolAllowed(ApplicationInfo::class))->toBeTrue()
        ->and(ToolRegistry::isToolAllowed('NonExistentTool'))->toBeFalse();
});

it('can get tool names', function (): void {
    $tools = ToolRegistry::getToolNames();

    expect($tools)->toBeArray()
        ->and($tools)->toHaveKey('ApplicationInfo')
        ->and($tools['ApplicationInfo'])->toBe(ApplicationInfo::class);
});

it('can clear cache', function (): void {
    $tools1 = ToolRegistry::getAvailableTools();

    ToolRegistry::clearCache();

    $tools2 = ToolRegistry::getAvailableTools();

    expect($tools1)->toEqual($tools2);
});

it('eagerly merges include tools into an existing cache', function (): void {
    ToolRegistry::getAvailableTools();

    expect(ToolRegistry::isToolAllowed(ThrowingTool::class))->toBeFalse();

    Configure::write('Ignis.mcp.tools.include', [ThrowingTool::class]);

    expect(ToolRegistry::isToolAllowed(ThrowingTool::class))->toBeTrue()
        ->and(ToolRegistry::getAvailableTools())->toContain(ThrowingTool::class);
});

it('does not merge excluded include tools into the cache', function (): void {
    ToolRegistry::getAvailableTools();

    Configure::write('Ignis.mcp.tools.exclude', [ThrowingTool::class]);
    Configure::write('Ignis.mcp.tools.include', [ThrowingTool::class]);

    expect(ToolRegistry::isToolAllowed(ThrowingTool::class))->toBeFalse();
});
