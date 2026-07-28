<?php

declare(strict_types=1);

use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Crustum\Ignis\Controller\BrowserLogsController;
use Crustum\Ignis\Mcp\Tools\BrowserLogs;
use Crustum\Mcp\Request;

beforeEach(function (): void {
    cleanFeatureLogDirectory();
});

it('returns log entries when file exists', function (): void {
    createBrowserLogFile(<<<'LOG'
2024-01-15 10:00:00 debug: console log message {"url":"http://example.com","user_agent":"Mozilla/5.0","timestamp":"2024-01-15T10:00:00.000000Z"}
2024-01-15 10:01:00 error: JavaScript error occurred {"url":"http://example.com/page","user_agent":"Mozilla/5.0","timestamp":"2024-01-15T10:01:00.000000Z"}
2024-01-15 10:02:00 warning: Warning message {"url":"http://example.com/other","user_agent":"Mozilla/5.0","timestamp":"2024-01-15T10:02:00.000000Z"}
LOG);

    $tool = new BrowserLogs();
    $response = $tool->handle(new Request(['entries' => 2]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('warning: Warning message', 'error: JavaScript error occurred')
        ->toolTextDoesNotContain('debug: console log message');
});

it('returns error when entries argument is invalid', function (): void {
    $tool = new BrowserLogs();

    $response = $tool->handle(new Request(['entries' => 0]));
    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('The "entries" argument must be greater than 0.');

    $response = $tool->handle(new Request(['entries' => -5]));
    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('The "entries" argument must be greater than 0.');
});

it('returns error when a log file does not exist', function (): void {
    $tool = new BrowserLogs();
    $response = $tool->handle(new Request(['entries' => 10]));

    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('No log file found, probably means no logs yet.');
});

it('returns message when log file is empty', function (): void {
    createBrowserLogFile('');

    $tool = new BrowserLogs();
    $response = $tool->handle(new Request(['entries' => 5]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('Unable to retrieve log entries, or no logs');
});

it('browser logs endpoint processes logs correctly', function (): void {
    configureFeatureLogChannel('browser', 'browser', options: [
        'scopes' => ['browser'],
        'levels' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
    ]);

    $payload = json_encode([
        'logs' => [
            [
                'type' => 'log',
                'timestamp' => '2024-01-15T10:00:00.000Z',
                'data' => ['Test message'],
                'url' => 'http://example.com',
                'userAgent' => 'Mozilla/5.0',
            ],
            [
                'type' => 'error',
                'timestamp' => '2024-01-15T10:01:00.000Z',
                'data' => ['Error occurred'],
                'url' => 'http://example.com/error',
                'userAgent' => 'Chrome/96',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $request = new ServerRequest([
        'environment' => [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/json',
        ],
        'input' => $payload,
    ]);

    $controller = new BrowserLogsController($request);
    $response = $controller->store();

    expect($response->getStatusCode())->toBe(200)
        ->and((string)$response->getBody())->toBe('{"status":"logged"}')
        ->and(browserLogPath())->toBeFile()
        ->and(getBrowserLogContent())
        ->toContain('Test message')
        ->toContain('Error occurred')
        ->toContain('http://example.com')
        ->toContain('Mozilla/5.0');
});
