<?php

declare(strict_types=1);

use Crustum\Ignis\Install\ThirdPartyPackage;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;
use JMac\Testing\Double;

afterEach(function (): void {
    clearInspectorPackages();
});

it('creates a package with all properties', function (): void {
    $package = new ThirdPartyPackage(
        name: 'vendor/package-name',
        hasGuidelines: true,
        hasSkills: true,
    );

    expect($package->name)->toBe('vendor/package-name')
        ->and($package->hasGuidelines)->toBeTrue()
        ->and($package->hasSkills)->toBeTrue();
});

it('returns correct feature label', function (bool $hasGuidelines, bool $hasSkills, string $expected): void {
    $package = new ThirdPartyPackage(
        name: 'vendor/package',
        hasGuidelines: $hasGuidelines,
        hasSkills: $hasSkills,
    );

    expect($package->featureLabel())->toBe($expected);
})->with([
    'both features' => [true, true, 'guidelines, skills'],
    'guidelines only' => [true, false, 'guideline'],
    'skills only' => [false, true, 'skills'],
    'no features' => [false, false, ''],
]);

it('returns correct display label', function (bool $hasGuidelines, bool $hasSkills, string $expected): void {
    $package = new ThirdPartyPackage(
        name: 'vendor/package',
        hasGuidelines: $hasGuidelines,
        hasSkills: $hasSkills,
    );

    expect($package->displayLabel())->toBe($expected);
})->with([
    'both features' => [true, true, 'vendor/package (guidelines, skills)'],
    'guidelines only' => [true, false, 'vendor/package (guideline)'],
    'skills only' => [false, true, 'vendor/package (skills)'],
]);

it('excludes first-party packages from discover results', function (): void {
    $packages = ThirdPartyPackage::discover(new ProjectManager());

    $firstPartyNames = [
        'cakephp/cakephp',
        'cakephp/bake',
        'crustum/ignis',
        'crustum/mcp',
        'crustum/inspector',
        'pestphp/pest',
        'phpunit/phpunit',
    ];

    $packageNames = array_keys($packages->toArray());

    foreach ($firstPartyNames as $name) {
        expect($packageNames)->not->toContain($name);
    }
});

it('discovers composer and npm packages while excluding first-party and indirect ones', function (): void {
    $project = Double::for(ProjectManager::class, override: true);

    $toolkit = stageInspectorPackage('acme/toolkit', 'guidelines', 'skills');
    $ui = stageInspectorPackage('@acme/ui', 'guidelines');
    $firstPartyComposer = stageInspectorPackage('cakephp/queue', 'guidelines');
    $firstPartyNpm = stageInspectorPackage('@crustum/ui', 'guidelines');
    $indirect = stageInspectorPackage('acme/indirect', 'guidelines');

    mockProjectPackages($project, new PackageCollection([
        inspectorPackage('acme/toolkit', '1.0.0', path: $toolkit)->setDirect(),
        inspectorPackage('@acme/ui', '1.0.0', path: $ui)->setDirect(),
        inspectorPackage('cakephp/queue', '2.0.0', path: $firstPartyComposer)->setDirect(),
        inspectorPackage('@crustum/ui', '1.0.0', path: $firstPartyNpm)->setDirect(),
        inspectorPackage('acme/indirect', '1.0.0', path: $indirect),
    ]));

    $discovered = ThirdPartyPackage::discover($project->instance())->toArray();

    expect($discovered)
        ->toHaveKeys(['acme/toolkit', '@acme/ui'])
        ->and($discovered['acme/toolkit']->hasGuidelines)->toBeTrue()
        ->and($discovered['acme/toolkit']->hasSkills)->toBeTrue()
        ->and($discovered['@acme/ui']->hasGuidelines)->toBeTrue()
        ->and($discovered['@acme/ui']->hasSkills)->toBeFalse();
});

it('resolves guideline and skill directories per ecosystem', function (): void {
    $project = Double::for(ProjectManager::class, override: true);

    $toolkit = stageInspectorPackage('acme/toolkit', 'guidelines', 'skills');

    mockProjectPackages($project, new PackageCollection([
        inspectorPackage('acme/toolkit', '1.0.0', path: $toolkit)->setDirect(),
    ]));

    $guidelines = ThirdPartyPackage::guidelineDirectories($project->instance());
    $skills = ThirdPartyPackage::skillDirectories($project->instance());

    expect($guidelines)->toHaveKey('acme/toolkit')
        ->and($skills)->toHaveKey('acme/toolkit');
});
