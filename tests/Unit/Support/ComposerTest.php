<?php
declare(strict_types=1);

use Crustum\Ignis\Support\Composer;

it('reads require and require-dev from composer.json', function (): void {
    file_put_contents(testAppPath('composer.json'), json_encode([
        'require' => [
            'cakephp/cakephp' => '^5.0',
        ],
        'require-dev' => [
            'pestphp/pest' => '^3.0',
        ],
    ]));

    $packages = Composer::packages();

    expect($packages)
        ->toHaveKey('cakephp/cakephp')
        ->toHaveKey('pestphp/pest');
});

it('returns package directories that exist in vendor', function (): void {
    file_put_contents(testAppPath('composer.json'), json_encode([
        'require' => [
            'cakephp/cakephp' => '^5.0',
            'nonexistent/pkg' => '^1.0.0',
        ],
    ]));

    $dir = testAppPath('vendor' . DS . 'cakephp' . DS . 'cakephp');
    ensureDirectoryExists($dir);

    $directories = Composer::packagesDirectories();

    expect($directories)
        ->toHaveKey('cakephp/cakephp')
        ->not->toHaveKey('nonexistent/pkg');
});

it('includes transitive packages from installed.json', function (): void {
    file_put_contents(testAppPath('composer.json'), json_encode([
        'require' => [
            'crustum/ignis' => '@dev',
        ],
    ]));

    $packRoot = testAppPath('vendor' . DS . 'crustum' . DS . 'cakephp-skills');
    ensureDirectoryExists($packRoot . DS . 'resources' . DS . 'ignis' . DS . 'pack');

    $composerDir = testAppPath('vendor' . DS . 'composer');
    ensureDirectoryExists($composerDir);

    file_put_contents($composerDir . DS . 'installed.json', json_encode([
        'packages' => [
            [
                'name' => 'crustum/cakephp-skills',
                'version' => 'dev-master',
                'install-path' => '../crustum/cakephp-skills',
            ],
        ],
    ]));

    expect(Composer::packagesDirectories())->not->toHaveKey('crustum/cakephp-skills')
        ->and(Composer::installedPackagesDirectories())->toHaveKey('crustum/cakephp-skills');
});

it('returns packages directories with ignis guidelines', function (): void {
    file_put_contents(testAppPath('composer.json'), json_encode([
        'require' => [
            'cakephp/cakephp' => '^5.0',
            'crustum/mcp' => 'dev-master',
        ],
    ]));

    $withGuidelines = testAppPath(implode(DS, [
        'vendor', 'cakephp', 'cakephp', 'resources', 'ignis', 'guidelines',
    ]));
    ensureDirectoryExists($withGuidelines);

    $withoutGuidelines = testAppPath(implode(DS, [
        'vendor', 'crustum', 'mcp',
    ]));
    ensureDirectoryExists($withoutGuidelines);

    $result = Composer::packagesDirectoriesWithIgnisGuidelines();

    expect($result)
        ->toHaveKey('cakephp/cakephp')
        ->not->toHaveKey('crustum/mcp');
});

it('identifies scoped first party packages', function (): void {
    expect(Composer::isFirstPartyPackage('cakephp/cakephp'))->toBeTrue()
        ->and(Composer::isFirstPartyPackage('cakephp/bake'))->toBeTrue()
        ->and(Composer::isFirstPartyPackage('cakephp/migrations'))->toBeTrue();
});

it('identifies allowlisted first party packages', function (): void {
    expect(Composer::isFirstPartyPackage('crustum/mcp'))->toBeTrue()
        ->and(Composer::isFirstPartyPackage('crustum/inspector'))->toBeTrue()
        ->and(Composer::isFirstPartyPackage('crustum/ignis'))->toBeTrue()
        ->and(Composer::isFirstPartyPackage('pestphp/pest'))->toBeTrue()
        ->and(Composer::isFirstPartyPackage('phpunit/phpunit'))->toBeTrue();
});

it('does not identify unknown packages as first party', function (): void {
    expect(Composer::isFirstPartyPackage('skie/plum-search'))->toBeFalse()
        ->and(Composer::isFirstPartyPackage('cakedc/anything'))->toBeFalse()
        ->and(Composer::isFirstPartyPackage('cakedc/users'))->toBeFalse()
        ->and(Composer::isFirstPartyPackage('unknown/package'))->toBeFalse();
});

it('refuses to delete the real plugin vendor directory', function (): void {
    expect(fn (): null => deleteDirectory(ROOT . DS . 'vendor'))
        ->toThrow(InvalidArgumentException::class, 'protected plugin path');
});
