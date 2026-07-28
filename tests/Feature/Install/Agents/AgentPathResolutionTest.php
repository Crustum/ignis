<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Install\Agents\Cursor;
use Crustum\Ignis\Install\Agents\Junie;
use Crustum\Ignis\Install\Agents\Pi;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;

test('Junie returns absolute PHP_BINARY path', function (): void {
    Configure::delete('Ignis.executable_paths.php');
    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $junie = new Junie($strategyFactory);

    expect($junie->getPhpPath())->toBe(PHP_BINARY);
});

test('Junie returns absolute cake path', function (): void {
    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $junie = new Junie($strategyFactory);

    $cakePath = $junie->getCakePath();

    expect($cakePath)->toEndWith('bin' . DS . 'cake.php')
        ->not->toBe('bin/cake.php');
});

test('Cursor returns relative php string', function (): void {
    Configure::delete('Ignis.executable_paths.php');
    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $cursor = new Cursor($strategyFactory);

    expect($cursor->getPhpPath())->toBe('php');
});

test('Cursor uses configured default_php_bin when not forcing absolute path', function (): void {
    Configure::write('Ignis.executable_paths.php', '/custom/path/to/php');

    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $cursor = new Cursor($strategyFactory);

    expect($cursor->getPhpPath())->toBe('/custom/path/to/php');

    Configure::delete('Ignis.executable_paths.php');
});

test('Cursor uses config even when forceAbsolutePath is true', function (): void {
    Configure::write('Ignis.executable_paths.php', '/custom/path/to/php');

    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $cursor = new Cursor($strategyFactory);

    expect($cursor->getPhpPath(true))->toBe('/custom/path/to/php');

    Configure::delete('Ignis.executable_paths.php');
});

test('Cursor uses PHP_BINARY when forceAbsolutePath is true and config is empty', function (): void {
    Configure::delete('Ignis.executable_paths.php');

    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $cursor = new Cursor($strategyFactory);

    expect($cursor->getPhpPath(true))->toBe(PHP_BINARY);
});

test('Cursor returns relative cake path', function (): void {
    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $cursor = new Cursor($strategyFactory);

    expect($cursor->getCakePath())->toBe('bin/cake.php');
});

test('Agents return absolute paths when forceAbsolutePath is true and config is empty', function (): void {
    Configure::delete('Ignis.executable_paths.php');
    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $cursor = new Cursor($strategyFactory);

    expect($cursor->getPhpPath(true))->toBe(PHP_BINARY)
        ->and($cursor->getCakePath(true))->toEndWith('bin' . DS . 'cake.php')
        ->not->toBe('bin/cake.php');
});

test('Agents maintain relative paths when forceAbsolutePath is false and config is empty', function (): void {
    Configure::delete('Ignis.executable_paths.php');
    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $cursor = new Cursor($strategyFactory);

    expect($cursor->getPhpPath())->toBe('php')
        ->and($cursor->getCakePath())->toBe('bin/cake.php');
});

test('Junie paths remain absolute regardless of forceAbsolutePath parameter', function (): void {
    Configure::delete('Ignis.executable_paths.php');
    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $junie = new Junie($strategyFactory);

    expect($junie->getPhpPath(true))->toBe(PHP_BINARY)
        ->and($junie->getPhpPath())->toBe(PHP_BINARY);

    $cakePath = $junie->getCakePath(true);
    expect($cakePath)->toEndWith('bin' . DS . 'cake.php')
        ->not->toBe('bin/cake.php')
        ->and($junie->getCakePath())->toBe($cakePath);
});

test('Junie uses config when configured', function (): void {
    Configure::write('Ignis.executable_paths.php', '/custom/php');
    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $junie = new Junie($strategyFactory);

    expect($junie->getPhpPath(true))->toBe('/custom/php');
    expect($junie->getPhpPath(false))->toBe('/custom/php');

    Configure::delete('Ignis.executable_paths.php');
});

test('Pi uses AGENTS.md and .pi/skills defaults', function (): void {
    Configure::delete('Ignis.executable_paths.php');
    $strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $pi = new Pi($strategyFactory);

    expect($pi->getPhpPath())->toBe('php')
        ->and($pi->getCakePath())->toBe('bin/cake.php')
        ->and($pi->guidelinesPath())->toBe('AGENTS.md')
        ->and($pi->skillsPath())->toBe('.pi/skills');
});
