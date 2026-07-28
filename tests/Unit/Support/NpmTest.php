<?php
declare(strict_types=1);

use Crustum\Ignis\Support\Npm;

it('returns empty packages when package.json does not exist', function (): void {
    expect(Npm::packages())->toBe([]);
});

it('returns empty packages when package.json is invalid json', function (): void {
    file_put_contents(testAppPath('package.json'), 'invalid json {{{');

    expect(Npm::packages())->toBe([]);
});

it('reads dependencies and devDependencies from package.json', function (): void {
    file_put_contents(testAppPath('package.json'), json_encode([
        'dependencies' => [
            '@crustum/monitor-ui' => '^1.0.0',
        ],
        'devDependencies' => [
            'vite' => '^5.0.0',
        ],
    ]));

    $packages = Npm::packages();

    expect($packages)
        ->toHaveKey('@crustum/monitor-ui')
        ->toHaveKey('vite');
});

it('returns empty packages directories when node_modules does not exist', function (): void {
    file_put_contents(testAppPath('package.json'), json_encode([
        'dependencies' => [
            '@crustum/monitor-ui' => '^1.0.0',
        ],
    ]));

    expect(Npm::packagesDirectories())->toBe([]);
});

it('returns package directories that exist in node_modules', function (): void {
    file_put_contents(testAppPath('package.json'), json_encode([
        'dependencies' => [
            '@crustum/monitor-ui' => '^1.0.0',
            'nonexistent-pkg' => '^1.0.0',
        ],
    ]));

    $dir = testAppPath('node_modules' . DS . '@crustum' . DS . 'monitor-ui');
    ensureDirectoryExists($dir);

    $directories = Npm::packagesDirectories();

    expect($directories)
        ->toHaveKey('@crustum/monitor-ui')
        ->not->toHaveKey('nonexistent-pkg');
});

it('returns packages directories with ignis guidelines', function (): void {
    file_put_contents(testAppPath('package.json'), json_encode([
        'dependencies' => [
            '@crustum/monitor-ui' => '^1.0.0',
            'axios' => '^1.0.0',
        ],
    ]));

    $withGuidelines = testAppPath(implode(DS, [
        'node_modules', '@crustum', 'monitor-ui', 'resources', 'ignis', 'guidelines',
    ]));
    ensureDirectoryExists($withGuidelines);

    $withoutGuidelines = testAppPath(implode(DS, [
        'node_modules', 'axios',
    ]));
    ensureDirectoryExists($withoutGuidelines);

    $result = Npm::packagesDirectoriesWithIgnisGuidelines();

    expect($result)
        ->toHaveKey('@crustum/monitor-ui')
        ->not->toHaveKey('axios');
});

it('returns packages directories with ignis skills', function (): void {
    file_put_contents(testAppPath('package.json'), json_encode([
        'dependencies' => [
            '@crustum/monitor-ui' => '^1.0.0',
        ],
    ]));

    $withSkills = testAppPath(implode(DS, [
        'node_modules', '@crustum', 'monitor-ui', 'resources', 'ignis', 'skills',
    ]));
    ensureDirectoryExists($withSkills);

    $result = Npm::packagesDirectoriesWithIgnisSkills();

    expect($result)->toHaveKey('@crustum/monitor-ui');
});

it('handles package.json with no dependencies', function (): void {
    file_put_contents(testAppPath('package.json'), json_encode([
        'name' => 'test-app',
    ]));

    expect(Npm::packages())->toBe([]);
});

it('returns non-scoped package directories with ignis guidelines', function (): void {
    file_put_contents(testAppPath('package.json'), json_encode([
        'dependencies' => [
            'tailwindcss' => '^4.0.0',
            'axios' => '^1.0.0',
        ],
    ]));

    $withGuidelines = testAppPath(implode(DS, [
        'node_modules', 'tailwindcss', 'resources', 'ignis', 'guidelines',
    ]));
    ensureDirectoryExists($withGuidelines);

    $withoutGuidelines = testAppPath(implode(DS, [
        'node_modules', 'axios',
    ]));
    ensureDirectoryExists($withoutGuidelines);

    $result = Npm::packagesDirectoriesWithIgnisGuidelines();

    expect($result)
        ->toHaveKey('tailwindcss')
        ->not->toHaveKey('axios');
});

it('returns non-scoped package directories with ignis skills', function (): void {
    file_put_contents(testAppPath('package.json'), json_encode([
        'dependencies' => [
            'tailwindcss' => '^4.0.0',
        ],
    ]));

    $withSkills = testAppPath(implode(DS, [
        'node_modules', 'tailwindcss', 'resources', 'ignis', 'skills',
    ]));
    ensureDirectoryExists($withSkills);

    $result = Npm::packagesDirectoriesWithIgnisSkills();

    expect($result)->toHaveKey('tailwindcss');
});

it('identifies scoped first party packages', function (): void {
    expect(Npm::isFirstPartyPackage('@crustum/monitor-ui'))->toBeTrue();
});

it('identifies non-scoped first party packages', function (): void {
    expect(Npm::isFirstPartyPackage('tailwindcss'))->toBeTrue()
        ->and(Npm::isFirstPartyPackage('vite'))->toBeTrue()
        ->and(Npm::isFirstPartyPackage('alpinejs'))->toBeTrue();
});

it('does not identify unknown packages as first party', function (): void {
    expect(Npm::isFirstPartyPackage('axios'))->toBeFalse()
        ->and(Npm::isFirstPartyPackage('lodash'))->toBeFalse()
        ->and(Npm::isFirstPartyPackage('unknown-package'))->toBeFalse();
});

it('refuses to delete the real plugin node_modules directory', function (): void {
    expect(fn (): null => deleteDirectory(ROOT . DS . 'node_modules'))
        ->toThrow(InvalidArgumentException::class, 'protected plugin path');
});
