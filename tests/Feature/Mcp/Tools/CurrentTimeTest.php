<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Mcp\Tools\CurrentTime;
use Crustum\Mcp\Request;

test('it returns current time for the app timezone', function (): void {
    Configure::write('App.defaultTimezone', 'UTC');

    $response = (new CurrentTime())->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolTextContains('+00:00');

    expect(toolResponseText($response))->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} [+-]\d{2}:\d{2}$/');
});

test('it returns current time for an explicit timezone', function (): void {
    $response = (new CurrentTime())->handle(new Request([
        'timezone' => 'America/New_York',
    ]));

    expect($response)->isToolResult()
        ->toolHasNoError();

    expect(toolResponseText($response))->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} [+-]\d{2}:\d{2}$/');
});

test('it errors for an invalid timezone', function (): void {
    $response = (new CurrentTime())->handle(new Request([
        'timezone' => 'Not/AZone',
    ]));

    expect($response)->isToolResult()
        ->toolHasError();
});
