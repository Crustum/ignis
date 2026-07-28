<?php

declare(strict_types=1);

use Crustum\Ignis\Install\Detection\CommandDetectionStrategy;
use Crustum\Ignis\Install\Enums\Platform;

beforeEach(function (): void {
    $this->strategy = new CommandDetectionStrategy();
});

test('detects command with successful exit code', function (): void {
    $command = PHP_OS_FAMILY === 'Windows' ? 'cmd /c exit 0' : 'true';

    $result = $this->strategy->detect([
        'command' => $command,
    ]);

    expect($result)->toBeTrue();
});

test('fails for command with non zero exit code', function (): void {
    $command = PHP_OS_FAMILY === 'Windows' ? 'cmd /c exit 1' : 'false';

    $result = $this->strategy->detect([
        'command' => $command,
    ]);

    expect($result)->toBeFalse();
});

test('returns false when no command config', function (): void {
    $result = $this->strategy->detect([
        'other_config' => 'value',
    ]);

    expect($result)->toBeFalse();
});

test('handles command with output', function (): void {
    $command = PHP_OS_FAMILY === 'Windows' ? 'cmd /c echo test' : 'echo test';

    $result = $this->strategy->detect([
        'command' => $command,
    ]);

    expect($result)->toBeTrue();
})->skip(PHP_OS_FAMILY === 'Windows', 'Windows proc_open echo behavior varies by shell');

test('handles command with error output', function (): void {
    $command = PHP_OS_FAMILY === 'Windows' ? 'cmd /c exit 127' : 'sh -c "exit 127"';

    $result = $this->strategy->detect([
        'command' => $command,
    ]);

    expect($result)->toBeFalse();
});

test('works with different platforms parameter', function (): void {
    $command = PHP_OS_FAMILY === 'Windows' ? 'cmd /c exit 0' : 'true';

    $result = $this->strategy->detect([
        'command' => $command,
    ], Platform::Windows);

    expect($result)->toBeTrue();
});
