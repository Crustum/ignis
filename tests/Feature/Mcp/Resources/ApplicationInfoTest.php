<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Mcp\Resources\ApplicationInfo as ApplicationInfoResource;
use Crustum\Ignis\Mcp\Tools\ApplicationInfo as ApplicationInfoTool;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Mcp\Request;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Inspector\Enums\PackageSource;
use Crustum\Inspector\Package;

it('returns application info from the resource tool pipeline', function (): void {
    $project = mockApplicationInfoProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package(PackageRegistry::PEST, '3.0.0', PackageSource::Composer),
    ]);

    $container = ContainerRegistry::getInstance();
    $container->addShared(ApplicationInfoTool::class, new ApplicationInfoTool($project), true);

    $resource = new ApplicationInfoResource();
    $response = $resource->handle();

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContent(function (array $data): void {
            expect($data)->toHaveKeys(['php_version', 'cakephp_version', 'database_engine', 'packages'])
                ->and($data['cakephp_version'])->toBe('5.0.0')
                ->and($data['packages'])->toHaveCount(2)
                ->and($data['packages'][0])->toMatchArray([
                    'inspector_name' => 'CAKEPHP',
                    'package_name' => 'cakephp/cakephp',
                    'version' => '5.0.0',
                ]);
        });
});

it('returns application info with no packages', function (): void {
    $tool = new ApplicationInfoTool(mockApplicationInfoProject());
    $response = $tool->handle(new Request([]));

    expect($response)->isToolResult()
        ->toolHasNoError()
        ->toolJsonContentToMatchArray([
            'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'cakephp_version' => 'unknown',
            'packages' => [],
        ]);
});

it('reflects updated inspector data on subsequent tool calls', function (): void {
    $tool = new ApplicationInfoTool(mockApplicationInfoProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]));
    $response = $tool->handle(new Request([]));

    expect($response)->toolJsonContent(function (array $data): void {
        expect($data['packages'])->toHaveCount(1)
            ->and($data['packages'][0]['version'])->toBe('5.0.0');
    });

    $updatedTool = new ApplicationInfoTool(mockApplicationInfoProject([
        new Package(PackageRegistry::CAKEPHP, '5.1.0', PackageSource::Composer),
        new Package(PackageRegistry::PEST, '3.1.0', PackageSource::Composer),
    ]));
    $response = $updatedTool->handle(new Request([]));

    expect($response)->toolJsonContent(function (array $data): void {
        expect($data['packages'])->toHaveCount(2)
            ->and($data['packages'][0]['version'])->toBe('5.1.0')
            ->and($data['packages'][1]['package_name'])->toBe('pestphp/pest');
    });
});

it('reads the configured default datasource name', function (): void {
    configureFeatureSchemaConnection();
    Configure::write('Datasources.default', 'test');

    $response = (new ApplicationInfoTool(mockApplicationInfoProject()))->handle(new Request([]));

    expect($response)->toolJsonContent(function (array $data): void {
        expect($data['database_engine'])->toBe('sqlite')
            ->and($data['database_engine'])->not->toBeArray();
    });

    Configure::delete('Datasources.default');
});
