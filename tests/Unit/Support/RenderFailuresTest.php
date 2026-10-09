<?php

declare(strict_types=1);

use Crustum\Ignis\Support\RenderFailures;

test('records a failure against the vendor package that shipped the file', function (): void {
    $failures = new RenderFailures();
    $path = '/app/vendor/crustum/ignis/resources/ignis/guidelines/core.twig';

    $failures->record($path);

    expect($failures->isEmpty())->toBeFalse()
        ->and($failures->failedFor($path))->toBeTrue()
        ->and($failures->failedFor('/app/vendor/cakedc/users/resources/ignis/guidelines/core.twig'))->toBeFalse()
        ->and($failures->packages())->toBe(['crustum/ignis']);
});

test('does not attribute a package to files outside the vendor directory', function (): void {
    $failures = new RenderFailures();

    $failures->record('/app/.ai/guidelines/custom.twig');

    expect($failures->packages())->toBe([])
        ->and($failures->paths())->toBe(['/app/.ai/guidelines/custom.twig']);
});

test('records each file once and reports every failing package', function (): void {
    $failures = new RenderFailures();
    $path = '/app/vendor/cakedc/users/resources/ignis/guidelines/core.twig';

    $failures->record($path);
    $failures->record($path);
    $failures->record('/app/vendor/crustum/ignis/resources/ignis/guidelines/core.twig');

    expect($failures->paths())->toHaveCount(2)
        ->and($failures->packages())->toBe(['cakedc/users', 'crustum/ignis']);

    $failures->flush();

    expect($failures->isEmpty())->toBeTrue()
        ->and($failures->paths())->toBe([]);
});

test('handles windows separators when attributing a package', function (): void {
    $failures = new RenderFailures();

    $failures->record('C:\\app\\vendor\\cakedc\\users\\resources\\ignis\\guidelines\\core.twig');

    expect($failures->packages())->toBe(['cakedc/users']);
});
