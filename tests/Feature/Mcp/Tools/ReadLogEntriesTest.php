<?php

declare(strict_types=1);

use Crustum\Ignis\Mcp\Tools\ReadLogEntries;
use Crustum\Mcp\Request;
use Cake\Log\Formatter\JsonFormatter;

beforeEach(function (): void {
    cleanFeatureLogDirectory();
});

it('requires a configured log channel name', function (): void {
    configureFeatureAppLogChannels();

    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request(['entries' => 2]));

    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('The "channel" argument is required')
        ->toolTextContains('debug, error, queries');
});

it('returns entries written to the error log channel', function (): void {
    configureFeatureAppLogChannels();

    writeFeatureLog('debug', 'First log message');
    writeFeatureLog('error', 'Error occurred');
    writeFeatureLog('warning', 'Warning message');

    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request([
        'channel' => 'error',
        'entries' => 2,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('Warning message', 'Error occurred')
        ->toolTextDoesNotContain('First log message');
});

it('returns entries written to the debug log channel', function (): void {
    configureFeatureAppLogChannels();

    writeFeatureLog('debug', 'Debug channel log message');

    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request([
        'channel' => 'debug',
        'entries' => 1,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('Debug channel log message');
});

it('returns entries written to a scoped queries log channel', function (): void {
    configureFeatureAppLogChannels();

    writeFeatureLog('debug', 'SELECT * FROM users', ['scope' => 'queriesLog']);

    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request([
        'channel' => 'queries',
        'entries' => 1,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('SELECT * FROM users');
});

it('returns error when entries argument is invalid', function (): void {
    configureFeatureAppLogChannels();

    $tool = new ReadLogEntries();

    $response = $tool->handle(new Request(['channel' => 'error', 'entries' => 0]));
    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('The "entries" argument must be greater than 0.');

    $response = $tool->handle(new Request(['channel' => 'error', 'entries' => -5]));
    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('The "entries" argument must be greater than 0.');
});

it('returns error when the configured log file does not exist yet', function (): void {
    configureFeatureLogChannel('error', 'error', options: [
        'levels' => ['error'],
    ]);

    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request([
        'channel' => 'error',
        'entries' => 10,
    ]));

    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('Log file not found');
});

it('returns error when the log channel is not configured', function (): void {
    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request([
        'channel' => 'missing',
        'entries' => 1,
    ]));

    expect($response)->isToolResult()
        ->toolHasError()
        ->toolTextContains('Log channel `missing` is not configured');
});

it('reads entries written with the JsonFormatter', function (): void {
    configureFeatureLogChannel('error', 'error', options: [
        'levels' => ['error', 'info'],
        'formatter' => [
            'className' => JsonFormatter::class,
        ],
    ]);

    writeFeatureLog('info', 'First message');
    writeFeatureLog('error', 'Second message');
    writeFeatureLog('info', 'Third message');

    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request([
        'channel' => 'error',
        'entries' => 2,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('Second message', 'Third message')
        ->toolTextDoesNotContain('First message');
});

it('reads imported Monolog JSON-formatted log files', function (): void {
    $logFile = configureFeatureLogChannel('error', 'error', options: [
        'levels' => ['error'],
    ]);

    writeFeatureLogFile($logFile, implode("\n", [
        '{"message":"First message","context":{},"level":200,"level_name":"INFO","channel":"local","datetime":"2024-01-15T10:00:00+00:00"}',
        '{"message":"Second message","context":{},"level":400,"level_name":"ERROR","channel":"local","datetime":"2024-01-15T10:01:00+00:00"}',
        '{"message":"Third message","context":{},"level":200,"level_name":"INFO","channel":"local","datetime":"2024-01-15T10:02:00+00:00"}',
    ]));

    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request([
        'channel' => 'error',
        'entries' => 2,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('Second message', 'Third message')
        ->toolTextDoesNotContain('First message');
});

it('reads imported Logstash-formatted log files', function (): void {
    $logFile = configureFeatureLogChannel('error', 'error', options: [
        'levels' => ['error'],
    ]);

    writeFeatureLogFile($logFile, implode("\n", [
        '{"@timestamp":"2024-01-15T10:00:00.000Z","@version":1,"host":"server","message":"Logstash info","type":"app","channel":"local","level":"INFO","monolog_level":200}',
        '{"@timestamp":"2024-01-15T10:01:00.000Z","@version":1,"host":"server","message":"Logstash error","type":"app","channel":"local","level":"ERROR","monolog_level":400}',
        '{"@timestamp":"2024-01-15T10:02:00.000Z","@version":1,"host":"server","message":"Logstash warning","type":"app","channel":"local","level":"WARNING","monolog_level":300}',
    ]));

    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request([
        'channel' => 'error',
        'entries' => 2,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('Logstash error', 'Logstash warning')
        ->toolTextDoesNotContain('Logstash info');
});

it('reads imported Loggly-formatted log files', function (): void {
    $logFile = configureFeatureLogChannel('error', 'error', options: [
        'levels' => ['error'],
    ]);

    writeFeatureLogFile($logFile, implode("\n", [
        '{"message":"Loggly first","context":{},"level":200,"level_name":"INFO","channel":"local","timestamp":"2024-01-15T10:00:00.000000+00:00"}',
        '{"message":"Loggly second","context":{},"level":400,"level_name":"ERROR","channel":"local","timestamp":"2024-01-15T10:01:00.000000+00:00"}',
    ]));

    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request([
        'channel' => 'error',
        'entries' => 1,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('Loggly second')
        ->toolTextDoesNotContain('Loggly first');
});

it('returns the correct count from imported Monolog JSON log files', function (): void {
    $logFile = configureFeatureLogChannel('error', 'error', options: [
        'levels' => ['error'],
    ]);

    $entries = [];

    for ($index = 1; $index <= 10; $index++) {
        $entries[] = '{"message":"Log entry ' . $index . '","context":{},"level":200,"level_name":"INFO","channel":"local","datetime":"2024-01-15T10:' . str_pad((string)$index, 2, '0', STR_PAD_LEFT) . ':00+00:00"}';
    }

    writeFeatureLogFile($logFile, implode("\n", $entries));

    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request([
        'channel' => 'error',
        'entries' => 3,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('Log entry 8', 'Log entry 9', 'Log entry 10')
        ->toolTextDoesNotContain('Log entry 7');
});

it('returns a message when the log file exists but is empty', function (): void {
    $logFile = configureFeatureLogChannel('error', 'error', options: [
        'levels' => ['error'],
    ]);

    writeFeatureLogFile($logFile, '');

    $tool = new ReadLogEntries();
    $response = $tool->handle(new Request([
        'channel' => 'error',
        'entries' => 5,
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('Unable to retrieve log entries, or no entries yet.');
});
