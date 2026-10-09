<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Services\BrowserLogger;

afterEach(function (): void {
    Configure::delete('Ignis.browser_log_levels');
    Configure::delete('Ignis.csp_nonce');
});

it('includes browser logger markers and console.debug', function (): void {
    expect(BrowserLogger::getScript())->toContain(
        'browser-logger-active',
        '/_ignis/browser-logs',
        'console.log',
        'console.debug',
        'console.error',
        'window.onerror',
    );
});

it('serializes log arguments without invoking proxy toJSON traps', function (): void {
    expect(BrowserLogger::getScript())->toContain(
        'function toSafeValue(value, seen)',
        'toSafeValue(event.reason, new WeakSet())',
    );
});

test('browser logger script captures the configured log levels', function (?array $configuredLevels, array $capturedTypes): void {
    Configure::write('Ignis.browser_log_levels', $configuredLevels);

    expect(BrowserLogger::getScript())->toContain('const captureTypes = ' . json_encode($capturedTypes) . ';');
})->with([
    'error' => [['error'], ['error']],
    'warning' => [['warning'], ['warning', 'error']],
    'info' => [['info'], ['info', 'warning', 'error']],
    'debug' => [['debug'], ['log', 'debug', 'info', 'warning', 'error', 'table']],
    'warn alias' => [['warn'], ['warning', 'error']],
    'missing configuration' => [null, ['log', 'debug', 'info', 'warning', 'error', 'table']],
    'empty configuration' => [[], ['log', 'debug', 'info', 'warning', 'error', 'table']],
]);
