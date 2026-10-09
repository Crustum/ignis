<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Mcp\ToolRegistry;
use Crustum\Ignis\Test\TestCase\IgnisTestCase;
use Crustum\Mcp\Response;

pest()->extend(IgnisTestCase::class)->in('TestCase', 'Feature/Install', 'Feature/Mcp', 'Feature/Rules', 'Feature/Conventions', 'Unit');

pest()->beforeEach(function (): void {
    useTestApp();
})->in('Unit/Support');

pest()->afterEach(function (): void {
    resetTestApp();
})->in('Unit/Support');

pest()->beforeEach(function (): void {
    useTestApp();
})->in('Unit/Install');

pest()->afterEach(function (): void {
    resetTestApp();
    resetInstallTestSandbox();
})->in('Unit/Install');

pest()->afterEach(function (): void {
    Configure::delete('Ignis.github.token');
    Configure::delete('Ignis.hosted.audit_url');
})->in('Unit/Skills');

pest()->beforeEach(function (): void {
    useTestApp();
})->in('Feature/Mcp');

pest()->beforeEach(function (): void {
    ToolRegistry::clearCache();
})->in('Feature/Mcp');

pest()->afterEach(function (): void {
    Configure::delete('Ignis.hosted.audit_url');
    Configure::delete('Ignis.rules.enabled');
    Configure::delete('Datasources.default');
    resetFeatureLogs();
    resetFeatureDatabaseConnections();
    restoreFeatureDatabaseBootstrapConnections();
    resetTestApp();
})->in('Feature/Mcp');

pest()->afterEach(function (): void {
    Configure::delete('Ignis.skills.exclude');
})->in('Feature/Install');

pest()->extend(\Crustum\Ignis\Test\TestCase\ConsoleTestCase::class)->in('Feature/Console');

pest()->beforeEach(function (): void {
    prepareConsoleProjectRoot();
})->in('Feature/Console');

pest()->afterEach(function (): void {
    flushIgnisConfig();
    Configure::delete('Ignis.enforce_tests');
    Configure::delete('Ignis.mcp.tools.exclude');
    Configure::delete('Ignis.mcp.tools.include');
    Configure::delete('Ignis.github.token');
    ToolRegistry::clearCache();
    resetConsoleProjectRoot();
})->in('Feature/Console');

expect()->extend('toBeOne', fn() => $this->toBe(1));

expect()->extend('isToolResult', fn () => $this->toBeInstanceOf(Response::class));

expect()->extend('toolTextContains', function (mixed ...$needles): object {
    $output = toolResponseText($this->value);

    foreach ($needles as $needle) {
        expect($output)->toContain((string)$needle);
    }

    return $this;
});

expect()->extend('toolTextDoesNotContain', function (mixed ...$needles): object {
    $output = toolResponseText($this->value);

    foreach ($needles as $needle) {
        expect($output)->not->toContain((string)$needle);
    }

    return $this;
});

expect()->extend('toolHasError', function (): object {
    expect($this->value->isError())->toBeTrue();

    return $this;
});

expect()->extend('toolHasNoError', function (): object {
    expect($this->value->isError())->toBeFalse();

    return $this;
});

expect()->extend('toolJsonContent', function (callable $callback): object {
    $callback(toolResponseJson($this->value));

    return $this;
});

expect()->extend('toolJsonContentToMatchArray', function (array $expectedArray): object {
    expect(toolResponseJson($this->value))->toMatchArray($expectedArray);

    return $this;
});

if (!function_exists('fixture')) {
    /**
     * Resolve a path under tests/Fixtures.
     *
     * @param string $name Fixture relative path
     * @return string
     */
    function fixture(string $name): string
    {
        return testDirectory('Fixtures' . DS . $name);
    }
}

if (!function_exists('fixtureContent')) {
    /**
     * Read fixture file contents.
     *
     * @param string $name Fixture relative path
     * @return string
     */
    function fixtureContent(string $name): string
    {
        $contents = file_get_contents(fixture($name));

        if ($contents === false) {
            throw new RuntimeException('Fixture not found: ' . $name);
        }

        return $contents;
    }
}
