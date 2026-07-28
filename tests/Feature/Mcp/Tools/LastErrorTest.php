<?php

declare(strict_types=1);

use Crustum\Ignis\Mcp\Tools\LastError;
use Crustum\Mcp\Request;
use Cake\Log\Formatter\JsonFormatter;

beforeEach(function (): void {
    cleanFeatureLogDirectory();
});

it('returns the latest error written to the error log channel', function (): void {
    configureFeatureAppLogChannels();

    writeFeatureLog('debug', 'Debug message');
    writeFeatureLog('error', 'File-based error message');
    writeFeatureLog('info', 'Info message');

    $tool = new LastError();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('error:', 'File-based error message')
        ->toolTextDoesNotContain('Debug message', 'Info message');
});

it('returns an error when the configured log file does not exist yet', function (): void {
    configureFeatureLogChannel('error', 'missing', options: [
        'levels' => ['error'],
    ]);

    $tool = new LastError();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('Log file not found');
});

it('returns an error when the error log has no error entries', function (): void {
    configureFeatureAppLogChannels();

    writeFeatureLog('debug', 'Debug message');
    writeFeatureLog('info', 'Info message');
    writeFeatureLog('warning', 'Warning message');

    $tool = new LastError();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('Unable to find an ERROR entry');
});

it('reads from a named log channel configured with Log::setConfig', function (): void {
    configureFeatureLogChannel('payments', 'payments', options: [
        'scopes' => ['payment'],
    ]);

    writeFeatureLog('error', 'Payment channel error', ['scope' => 'payment']);

    $tool = new LastError();
    $response = $tool->handle(new Request(['channel' => 'payments']));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('Payment channel error');
});

it('returns an error when the log channel is not configured', function (): void {
    $tool = new LastError();
    $response = $tool->handle(new Request(['channel' => 'missing']));

    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('Log channel `missing` is not configured');
});

it('finds errors written with the JsonFormatter', function (): void {
    configureFeatureLogChannel('error', 'error', options: [
        'levels' => ['error', 'info'],
        'formatter' => [
            'className' => JsonFormatter::class,
        ],
    ]);

    writeFeatureLog('info', 'Info message');
    writeFeatureLog('error', 'JSON error message');

    $tool = new LastError();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('JSON error message')
        ->toolTextDoesNotContain('Info message');
});

it('returns an error when no error entry exists in JsonFormatter logs', function (): void {
    configureFeatureLogChannel('error', 'error', options: [
        'levels' => ['error', 'info', 'debug'],
        'formatter' => [
            'className' => JsonFormatter::class,
        ],
    ]);

    writeFeatureLog('info', 'Info message');
    writeFeatureLog('debug', 'Debug message');

    $tool = new LastError();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('Unable to find an ERROR entry');
});

it('finds errors in imported Logstash-formatted log files', function (): void {
    $logFile = configureFeatureLogChannel('error', 'error', options: [
        'levels' => ['error'],
    ]);

    writeFeatureLogFile($logFile, implode("\n", [
        '{"@timestamp":"2024-01-15T10:00:00.000Z","@version":1,"host":"server","message":"Logstash info","type":"app","channel":"local","level":"INFO","monolog_level":200}',
        '{"@timestamp":"2024-01-15T10:01:00.000Z","@version":1,"host":"server","message":"Logstash error found","type":"app","channel":"local","level":"ERROR","monolog_level":400}',
        '{"@timestamp":"2024-01-15T10:02:00.000Z","@version":1,"host":"server","message":"Logstash warning","type":"app","channel":"local","level":"WARNING","monolog_level":300}',
    ]));

    $tool = new LastError();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('Logstash error found')
        ->toolTextDoesNotContain('Logstash info', 'Logstash warning');
});

it('does not return info or warning entries when an error exists', function (): void {
    configureFeatureAppLogChannels();

    writeFeatureLog('info', 'This is an info message');
    writeFeatureLog('warning', 'This is a warning message');
    writeFeatureLog('error', 'This is the actual error');

    $tool = new LastError();
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('This is the actual error')
        ->toolTextDoesNotContain('This is an info message', 'This is a warning message');
});
