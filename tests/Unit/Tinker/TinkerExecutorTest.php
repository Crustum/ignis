<?php

declare(strict_types=1);

use Crustum\Ignis\Tinker\TinkerExecutor;

afterEach(function (): void {
    set_time_limit(0);
});

test('it evaluates a return expression', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute('return 2 + 2;', 600);

    expect($result)->toMatchArray([
        'success' => true,
        'output' => null,
        'result' => 4,
        'type' => 'int',
    ]);
});

test('it returns bare expressions without return keyword', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute('2 + 2', 600);

    expect($result['success'])->toBeTrue()
        ->and($result['result'])->toBe(4)
        ->and($result['type'])->toBe('int');
});

test('it returns bare expressions with a trailing semicolon', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute(\Cake\Core\Configure::class . '::read("App.namespace");', 600);

    expect($result['success'])->toBeTrue()
        ->and($result['result'])->toBe('TestApp')
        ->and($result['type'])->toBe('string');
});

test('it returns method call results without return keyword', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute(\Cake\Core\Configure::class . '::read("App.encoding")', 600);

    expect($result['success'])->toBeTrue()
        ->and($result['result'])->toBe('UTF-8')
        ->and($result['type'])->toBe('string');
});

test('it captures echoed output', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute('echo "hello";', 600);

    expect($result['success'])->toBeTrue()
        ->and($result['output'])->toBe('hello')
        ->and($result['result'])->toBeNull()
        ->and($result['type'])->toBe('null');
});

test('it rejects empty code', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute('   ', 600);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('No code provided');
});

test('it strips php tags from code', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute('<?php return 7;', 600);

    expect($result['success'])->toBeTrue()
        ->and($result['result'])->toBe(7);
});

test('it reports runtime errors', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute('throw new RuntimeException("boom");', 600);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('boom')
        ->and($result['type'])->toBe(RuntimeException::class);
});

test('it serializes objects without json helpers', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute('return (object)["a" => 1];', 600);

    expect($result['success'])->toBeTrue()
        ->and($result['result'])->toMatchArray([
            '__class' => 'stdClass',
            '__properties' => ['a' => 1],
        ])
        ->and($result['class'])->toBe('stdClass');
});

test('it can instantiate Cake classes', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute('return new \\Cake\\I18n\\DateTime("2026-01-01");', 600);

    expect($result['success'])->toBeTrue()
        ->and($result['class'])->toBe(\Cake\I18n\DateTime::class);

    if (is_array($result['result'])) {
        expect($result['result'])->toMatchArray([
            'year' => 2026,
            'month' => 1,
            'day' => 1,
        ]);
    } else {
        expect($result['result'])->toBeString()
            ->and($result['result'])->toContain('2026-01-01');
    }
});

test('it can read Cake Configure values', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute('return ' . \Cake\Core\Configure::class . '::read("App.namespace");', 600);

    expect($result['success'])->toBeTrue()
        ->and($result['result'])->toBe('TestApp');
});

test('it can access Cake ConnectionManager', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute('return ' . \Cake\Datasource\ConnectionManager::class . '::configured();', 600);

    expect($result['success'])->toBeTrue()
        ->and($result['result'])->toContain('test')
        ->and($result['type'])->toBe('array')
        ->and($result['count'])->toBeGreaterThan(0);
});

test('it can access the application container', function (): void {
    $executor = new TinkerExecutor();

    $result = $executor->execute(
        'return ' . \Cake\Core\Configure::class . '::read("app.container") instanceof \Psr\Container\ContainerInterface;',
        600,
    );

    expect($result['success'])->toBeTrue()
        ->and($result['result'])->toBeFalse();
});
