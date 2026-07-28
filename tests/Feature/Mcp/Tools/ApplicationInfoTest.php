<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Crustum\Ignis\Mcp\Tools\ApplicationInfo;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Mcp\Request;
use Crustum\Inspector\Enums\PackageSource;
use Crustum\Inspector\Package;

test('it returns application info with packages', function (): void {
    $tool = new ApplicationInfo(mockApplicationInfoProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package(PackageRegistry::PEST, '3.0.0', PackageSource::Composer),
    ]));
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContentToMatchArray([
            'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'cakephp_version' => '5.0.0',
            'packages' => [
                [
                    'inspector_name' => 'CAKEPHP',
                    'package_name' => 'cakephp/cakephp',
                    'version' => '5.0.0',
                ],
                [
                    'inspector_name' => 'PEST',
                    'package_name' => 'pestphp/pest',
                    'version' => '3.0.0',
                ],
            ],
        ]);
});

test('it returns application info with no packages', function (): void {
    $tool = new ApplicationInfo(mockApplicationInfoProject());
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContentToMatchArray([
            'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'cakephp_version' => 'unknown',
            'packages' => [],
        ]);
});

test('it falls back to Configure when cakephp is missing from inspector', function (): void {
    Configure::write('Cake.version', '5.4.2');

    $tool = new ApplicationInfo(mockApplicationInfoProject([
        new Package(PackageRegistry::PEST, '3.0.0', PackageSource::Composer),
    ]));
    $response = $tool->handle(new Request([]));

    expect($response)->toolJsonContentToMatchArray([
        'cakephp_version' => '5.4.2',
    ]);

    Configure::delete('Cake.version');
});

it('returns updated package versions when a new inspector instance is used', function (): void {
    $tool = new ApplicationInfo(mockApplicationInfoProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]));
    $response = $tool->handle(new Request([]));

    expect($response)->toolJsonContent(function (array $data): void {
        expect($data)->toHaveKeys(['packages', 'php_version', 'cakephp_version', 'database_engine'])
            ->and($data['cakephp_version'])->toBe('5.0.0')
            ->and($data['packages'])->toHaveCount(1)
            ->and($data['packages'][0]['version'])->toBe('5.0.0');
    });

    $tool = new ApplicationInfo(mockApplicationInfoProject([
        new Package(PackageRegistry::CAKEPHP, '5.1.0', PackageSource::Composer),
        new Package(PackageRegistry::PEST, '3.0.0', PackageSource::Composer),
    ]));
    $response = $tool->handle(new Request([]));

    expect($response)->toolJsonContent(function (array $data): void {
        expect($data['packages'])->toHaveCount(2)
            ->and($data['cakephp_version'])->toBe('5.1.0')
            ->and($data['packages'][0]['version'])->toBe('5.1.0')
            ->and($data['packages'][1]['package_name'])->toBe('pestphp/pest');
    });
});

it('reports the configured default datasource connection name', function (): void {
    Configure::write('Datasources.default', 'test');

    $response = (new ApplicationInfo(mockApplicationInfoProject()))->handle(new Request([]));

    expect($response)->toolJsonContent(function (array $data): void {
        expect($data['database_engine'])->toBe('test');
    });
});

it('does not expose datasource credentials in application info', function (): void {
    $secretPassword = 'ignis-secret-db-password-' . uniqid();

    ConnectionManager::setConfig('leaky', [
        'className' => \Cake\Database\Connection::class,
        'driver' => \Cake\Database\Driver\Sqlite::class,
        'database' => ':memory:',
        'username' => 'secret-user',
        'password' => $secretPassword,
        'url' => 'sqlite://secret-user:' . $secretPassword . '@/:memory:',
    ]);
    Configure::write('Datasources.default', 'leaky');

    $response = (new ApplicationInfo(mockApplicationInfoProject()))->handle(new Request([]));

    expect($response)->toolJsonContent(function (array $data) use ($secretPassword): void {
        expect($data['database_engine'])->toBe('leaky')
            ->and($data['database_engine'])->not->toBeArray();

        $encoded = json_encode($data);
        expect($encoded)->not->toContain($secretPassword)
            ->and($encoded)->not->toContain('secret-user')
            ->and($encoded)->not->toContain('password');
    });

    ConnectionManager::drop('leaky');
    Configure::delete('Datasources.default');
});
