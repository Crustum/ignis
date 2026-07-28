<?php

declare(strict_types=1);

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Ignis\Install\GuidelineComposer;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Ignis\Support\Composer;
use Crustum\Ignis\Support\DirectoryLink;
use Crustum\Ignis\Support\Npm;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\Enums\JsPackageManager;
use Crustum\Inspector\Package;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;

/**
 * Partial GuidelineComposer that reads user guidelines from a fixture tree.
 *
 * @param \Crustum\Inspector\ProjectManager $project Project manager
 * @param string $fixture Fixture directory under tests/Fixtures
 * @return \Crustum\Ignis\Install\GuidelineComposer
 */
function guidelineComposerWithFixture(ProjectManager $project, string $fixture): GuidelineComposer
{
    $dir = fixture($fixture);

    $composer = Mockery::mock(GuidelineComposer::class, [$project])->makePartial();
    $composer
        ->shouldReceive('customGuidelinePath')
        ->andReturnUsing(fn(string $path = ''): string => $dir . ($path !== '' ? DS . ltrim($path, '/\\') : ''));

    return $composer;
}

/**
 * Partial GuidelineComposer that can stub first-party vendor guideline paths.
 *
 * @param \Crustum\Inspector\ProjectManager $project Project manager
 * @return \Crustum\Ignis\Install\GuidelineComposer
 */
function guidelineComposerAllowingVendorPath(ProjectManager $project): GuidelineComposer
{
    return Mockery::mock(GuidelineComposer::class, [$project])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
}

beforeEach(function (): void {
    useTestApp();
    Configure::write('Ignis.rules.enabled', true);
    Configure::delete('Ignis.rules.scoped_guidelines');
    Configure::delete('Ignis.guidelines.exclude');
    Configure::delete('Ignis.executable_paths.npm');

    $this->project = Mockery::mock(ProjectManager::class);
    $this->composer = new GuidelineComposer($this->project);
});

afterEach(function (): void {
    Configure::delete('Ignis.guidelines.exclude');
    Configure::delete('Ignis.rules.enabled');
    Configure::delete('Ignis.rules.scoped_guidelines');
    resetTestApp();
    Mockery::close();
});

test('includes package guidelines only for installed packages', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $guidelines = $this->composer->compose();

    expect($guidelines)
        ->toContain('=== pest/core rules ===')
        ->toContain('=== cakephp/core rules ===')
        ->not->toContain('=== bake/core rules ===');
});

test('excludes scoped block content from the composed blob when scoped guidelines are enabled', function (): void {
    Configure::write('Ignis.rules.scoped_guidelines', true);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $guidelines = $this->composer->compose();

    expect(Configure::read('Ignis.rules.scoped_guidelines'))->toBeTrue()
        ->and($guidelines)
        ->not->toContain('=== pest/core rules ===')
        ->toContain('=== foundation rules ===');
});

test('inlines scoped block content by default since scoped guidelines are opt-in', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $guidelines = $this->composer->compose();

    expect(Configure::read('Ignis.rules.scoped_guidelines'))->toBeNull()
        ->and((bool)Configure::read('Ignis.rules.scoped_guidelines', false))->toBeFalse()
        ->and($guidelines)
        ->toContain('=== cakephp/core rules ===')
        ->toContain('URL Generation')
        ->toContain('Tables, Entities, and Fixtures');
});

test('strips only the scoped portion of a partially-scoped guideline, keeping the rest inline', function (): void {
    Configure::write('Ignis.rules.scoped_guidelines', true);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $guidelines = $this->composer->compose();

    expect(Configure::read('Ignis.rules.scoped_guidelines'))->toBeTrue()
        ->and($guidelines)
        ->toContain('=== cakephp/core rules ===')
        ->toContain('URL Generation')
        ->not->toContain('Tables, Entities, and Fixtures');
});

test('excludes conditional guidelines when config is false', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $config = new GuidelineConfig();
    $config->cakeStyle = false;
    $config->hasAnApi = false;
    $config->caresAboutLocalization = false;
    $config->enforceTests = false;

    $guidelines = $this->composer
        ->config($config)
        ->compose();

    expect($guidelines)
        ->not->toContain('=== cakephp/style rules ===')
        ->not->toContain('=== cakephp/api rules ===')
        ->not->toContain('=== cakephp/localization rules ===')
        ->not->toContain('=== tests rules ===');
});

test('includes the project rules pointer when rules are enabled and MCP is on', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $config = new GuidelineConfig();
    $config->hasMcp = true;

    $guidelines = $this->composer->config($config)->compose();

    expect($guidelines)
        ->toContain('## Project Rules')
        ->toContain('record-rule');
});

test('omits the project rules pointer when rules are disabled', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $config = new GuidelineConfig();
    $config->hasMcp = true;

    $guidelines = $this->composer->config($config)->compose();

    expect($guidelines)->not->toContain('## Project Rules');
});

test('excludes PHPUnit guidelines when Pest is present due to package priority', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
        inspectorPackage(PackageRegistry::PHPUNIT, '10.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $guidelines = $this->composer->compose();

    expect($guidelines)
        ->toContain('=== pest/core rules ===')
        ->not->toContain('=== phpunit/core rules ===');
});

test('excludes crustum/mcp guidelines when indirectly required', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::MCP, '0.2.2')->setDirect(false),
    ]);

    mockProjectPackages($this->project, $packages);

    expect($this->composer->compose())->not->toContain('=== mcp/core rules ===');
});

test('includes PHPUnit guidelines when Pest is not present', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PHPUNIT, '10.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $guidelines = $this->composer->compose();

    expect($guidelines)
        ->toContain('=== phpunit/core rules ===')
        ->not->toContain('=== pest/core rules ===');
});

test('includes correct package manager commands in guidelines based on lockfile', function (JsPackageManager $packageManager, string $expectedCommand): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);
    mockProjectPackages($this->project, $packages, $packageManager);

    $guidelines = $this->composer->compose();

    expect($guidelines)
        ->toContain("{$expectedCommand} run build")
        ->toContain("{$expectedCommand} run dev");
})->with([
    'npm' => [JsPackageManager::Npm, 'npm'],
    'yarn' => [JsPackageManager::Yarn, 'yarn'],
    'pnpm' => [JsPackageManager::Pnpm, 'pnpm'],
    'bun' => [JsPackageManager::Bun, 'bun'],
]);

test('excludes Skills Activation section when skills are disabled', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $config = new GuidelineConfig();
    $config->hasSkills = false;

    expect($this->composer->config($config)->compose())
        ->not->toContain('## Skills Activation');
});

test('includes Skills Activation section when skills are enabled', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $config = new GuidelineConfig();
    $config->hasSkills = true;

    expect($this->composer->config($config)->compose())
        ->toContain('## Skills Activation');
});

test('excludes guidelines listed in config exclude list', function (): void {
    Configure::write('Ignis.rules.enabled', false);
    Configure::write('Ignis.guidelines.exclude', ['pest/core', 'tests']);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $config = new GuidelineConfig();
    $config->enforceTests = true;

    $guidelines = $this->composer->config($config)->compose();

    expect($guidelines)
        ->not->toContain('=== pest/core rules ===')
        ->not->toContain('=== tests rules ===')
        ->toContain('=== foundation rules ===');
});

test('excludes core guidelines when listed in exclude config', function (): void {
    Configure::write('Ignis.guidelines.exclude', ['php']);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    expect($this->composer->compose())
        ->not->toContain('=== php rules ===')
        ->toContain('=== foundation rules ===');
});

test('excludes package guidelines when listed in exclude config', function (): void {
    Configure::write('Ignis.rules.enabled', false);
    Configure::write('Ignis.guidelines.exclude', ['pest/core']);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    expect($this->composer->compose())
        ->not->toContain('=== pest/core rules ===')
        ->toContain('=== cakephp/core rules ===');
});

test('does not exclude user guidelines via config', function (): void {
    Configure::write('Ignis.guidelines.exclude', ['.ai/custom-rule']);

    ensureDirectoryExists(base_path('.ai/guidelines'));
    file_put_contents(base_path('.ai/guidelines/custom-rule.md'), "# Custom Rule\n\nUser guidance.\n");

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    expect($this->composer->compose())
        ->toContain('=== .ai/custom-rule rules ===')
        ->toContain('User guidance');
});

test('ignores non-existent keys in guidelines exclude list', function (): void {
    Configure::write('Ignis.guidelines.exclude', ['nonexistent']);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    expect($this->composer->compose())->toContain('=== foundation rules ===');
});

test('excludes guidelines from used() list', function (): void {
    Configure::write('Ignis.rules.enabled', false);
    Configure::write('Ignis.guidelines.exclude', ['pest/core']);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    expect($this->composer->used())->not->toContain('pest/core');
});

test('excludes MCP Tools section when hasMcp is false', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $config = new GuidelineConfig();
    $config->hasMcp = false;

    expect($this->composer->config($config)->compose())
        ->not->toContain('## Tools');
});

test('includes MCP Tools section when hasMcp is true', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $config = new GuidelineConfig();
    $config->hasMcp = true;

    expect($this->composer->config($config)->compose())
        ->toContain('## Tools')
        ->toContain('database-query');
});

test('includes user custom guidelines from .ai/guidelines directory', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $composer = guidelineComposerWithFixture($this->project, '.ai/guidelines');

    expect($composer->compose())
        ->toContain('=== .ai/custom-rule rules ===')
        ->toContain('=== .ai/project-specific rules ===')
        ->toContain('This is a custom project-specific guideline')
        ->toContain('Project-specific coding standards')
        ->toContain('Database tables must use `snake_case` naming')
        ->and($composer->used())
        ->toContain('.ai/custom-rule')
        ->toContain('.ai/project-specific');
});

test('a user override still applies for a package whose bundled core.twig no longer exists', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $customDir = fixture('.ai/pest-core-override-guidelines');
    ensureDirectoryExists($customDir . DS . 'pest');
    file_put_contents($customDir . DS . 'pest' . DS . 'core.twig', "# Custom Pest Override\n\nAlways use this project's own Pest conventions.\n");

    try {
        $composer = Mockery::mock(GuidelineComposer::class, [$this->project])->makePartial();
        $composer
            ->shouldReceive('customGuidelinePath')
            ->andReturnUsing(fn(string $path = ''): string => $customDir . ($path !== '' ? DS . ltrim($path, '/\\') : ''));

        $guidelines = $composer->guidelines()->toArray();

        expect($guidelines['pest/core']['content'] ?? null)
            ->toContain('Custom Pest Override');
    } finally {
        @unlink($customDir . DS . 'pest' . DS . 'core.twig');
        @rmdir($customDir . DS . 'pest');
        @rmdir($customDir);
    }
});

test('non-empty custom guidelines override Ignis guidelines', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $composer = guidelineComposerWithFixture($this->project, '.ai/guidelines');
    $guidelines = $composer->compose();
    $overrideStringCount = substr_count($guidelines, 'Thanks though, appreciate you');

    expect($overrideStringCount)->toBe(1)
        ->and($guidelines)
        ->toContain('Thanks though, appreciate you')
        ->not->toContain('## CakePHP 5 Core')
        ->toContain('=== cakephp/v5 rules ===')
        ->not->toContain('=== .ai/core rules ===')
        ->and($composer->used())
        ->toContain('.ai/custom-rule')
        ->toContain('.ai/project-specific');
});

test('renderContent handles twig and markdown files correctly', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);
    $composer = guidelineComposerWithFixture($this->project, '.ai/guidelines');

    $guidelines = $composer->compose();

    expect($guidelines)
        ->toContain('=== .ai/test-twig-with-backticks rules ===')
        ->not->toContain('=== .ai/test-twig-with-backticks.md rules ===')
        ->toContain('`bin/cake bake model`')
        ->toContain('`php bin/cake.php migrations migrate`')
        ->toContain('`$this->fetchTable(\'Users\')`')
        ->toContain("`Router::url(['_name' => 'home'])`")
        ->toContain("`Configure::read('App.name')`")
        ->toContain('=== .ai/test-twig-with-php-tags rules ===')
        ->not->toContain('=== .ai/test-twig-with-backticks.twig rules ===')
        ->toContain('<?php')
        ->toContain('namespace App\Model\Entity;')
        ->toContain('class User extends Entity')
        ->toContain('=== .ai/test-markdown rules ===')
        ->toContain('# Markdown File Test')
        ->toContain('This is a plain markdown file')
        ->toContain('Use `code` in backticks')
        ->toContain('echo "Hello World";')
        ->toContain('=== .ai/test-twig-with-assist rules ===')
        ->toContain('Run `npm install` to install dependencies')
        ->toContain('Package manager: npm install');
});

test('the guidelines are in correct order', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $composer = guidelineComposerWithFixture($this->project, '.ai/guidelines');

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);
    mockProjectPackages($this->project, $packages);

    $config = new GuidelineConfig();
    $config->enforceTests = true;

    $composer->config($config);

    $keys = array_keys($composer->guidelines()->toArray());

    $firstUserGuidelinePos = false;
    $pestPos = false;

    foreach ($keys as $index => $key) {
        if ($firstUserGuidelinePos === false && str_starts_with((string)$key, '.ai/')) {
            $firstUserGuidelinePos = $index;
        }

        if ($pestPos === false && str_starts_with((string)$key, 'pest/')) {
            $pestPos = $index;
        }
    }

    $foundationPos = array_search('foundation', $keys, true);
    $testsPos = array_search('tests', $keys, true);

    expect($firstUserGuidelinePos)->not->toBeFalse()
        ->and($foundationPos)->not->toBeFalse()
        ->and($testsPos)->not->toBeFalse()
        ->and($pestPos)->not->toBeFalse()
        ->and($firstUserGuidelinePos)->toBeLessThan($foundationPos)
        ->and($foundationPos)->toBeLessThan($testsPos)
        ->and($testsPos)->toBeLessThan($pestPos);
});

test('composeGuidelines filters out empty guidelines', function (): void {
    $guidelines = new Collection([
        'test/empty' => [
            'content' => '   ',
            'name' => 'empty',
            'path' => '/path/to/empty.md',
            'custom' => false,
        ],
        'test/valid' => [
            'content' => 'Valid content',
            'name' => 'valid',
            'path' => '/path/to/valid.md',
            'custom' => false,
        ],
    ]);

    $composed = GuidelineComposer::composeGuidelines($guidelines);

    expect($composed)
        ->toContain('=== test/valid rules ===')
        ->toContain('Valid content')
        ->not->toContain('=== test/empty rules ===');
});

test('user guidelines are sorted by filename for predictable ordering', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $composer = guidelineComposerWithFixture($this->project, '.ai/sorted-guidelines');
    $keys = array_keys($composer->guidelines()->toArray());
    $userGuidelineKeys = array_values(array_filter(
        $keys,
        static fn(string $key): bool => str_starts_with($key, '.ai/'),
    ));

    expect($userGuidelineKeys)->toBe(['.ai/00-first', '.ai/10-middle', '.ai/20-second']);
});

test('excludes ignis package from Inspector discovery to prevent duplicate core guidelines', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::IGNIS, '1.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $keys = $this->composer->used();

    expect($keys)->toContain('ignis')
        ->and($keys)->not->toContain('ignis/core');
});

test('does not exclude user guidelines via config when using fixture pack', function (): void {
    Configure::write('Ignis.guidelines.exclude', ['.ai/custom-rule']);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $composer = guidelineComposerWithFixture($this->project, '.ai/guidelines');

    expect($composer->compose())
        ->toContain('=== .ai/custom-rule rules ===')
        ->toContain('This is a custom project-specific guideline');
});

test('loads vendor core guideline when available', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $vendorFixture = realpath(fixture('vendor-guidelines/core-only'));

    $composer = guidelineComposerAllowingVendorPath($this->project);
    $composer->shouldReceive('resolveFirstPartyIgnisPath')
        ->andReturnUsing(fn(Package $package, string $subpath): ?string => $package->name() === PackageRegistry::PEST ? $vendorFixture : null);

    $guidelines = $composer->compose();

    expect($guidelines)
        ->toContain('Vendor Core Guideline')
        ->toContain('loaded from the vendor directory');
});

test('falls back to .ai/ when vendor guideline path does not exist', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $composer = guidelineComposerAllowingVendorPath($this->project);
    $composer->shouldReceive('resolveFirstPartyIgnisPath')->andReturn(null);

    expect($composer->compose())->toContain('=== cakephp/core rules ===');
});

test('guideline key is unchanged regardless of vendor or .ai/ source', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $vendorFixture = realpath(fixture('vendor-guidelines/core-only'));

    $composer = guidelineComposerAllowingVendorPath($this->project);
    $composer->shouldReceive('resolveFirstPartyIgnisPath')
        ->andReturnUsing(fn(Package $package, string $subpath): ?string => $package->name() === PackageRegistry::PEST ? $vendorFixture : null);

    expect($composer->used())->toContain('pest/core');
});

test('user override works with vendor-sourced guideline', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $vendorFixture = realpath(fixture('vendor-guidelines/core-only'));

    $composer = guidelineComposerAllowingVendorPath($this->project);
    $composer->shouldReceive('customGuidelinePath')
        ->andReturnUsing(fn(string $path = ''): string => fixture('.ai/guidelines') . ($path !== '' ? DS . ltrim($path, '/\\') : ''));
    $composer->shouldReceive('resolveFirstPartyIgnisPath')
        ->andReturnUsing(fn(Package $package, string $subpath): ?string => $package->name() === PackageRegistry::CAKEPHP ? $vendorFixture : null);

    $guidelines = $composer->guidelines()->toArray();
    $cakephpCore = $guidelines['cakephp/core'] ?? null;

    expect($cakephpCore)->not->toBeNull()
        ->and($cakephpCore['content'])->toContain('User Override CakePHP Core')
        ->and($cakephpCore['content'])->not->toContain('Vendor Core Guideline');
});

test('isFirstPartyPackage identifies known packages', function (): void {
    expect(Composer::isFirstPartyPackage('cakephp/cakephp'))->toBeTrue()
        ->and(Composer::isFirstPartyPackage('crustum/mcp'))->toBeTrue()
        ->and(Composer::isFirstPartyPackage('pestphp/pest'))->toBeTrue()
        ->and(Composer::isFirstPartyPackage('some/third-party'))->toBeFalse();
});

test('isFirstPartyPackage identifies scoped npm packages', function (): void {
    expect(Npm::isFirstPartyPackage('@crustum/ui'))->toBeTrue()
        ->and(Npm::isFirstPartyPackage('vite'))->toBeTrue()
        ->and(Npm::isFirstPartyPackage('@other/package'))->toBeFalse();
});

test('loads node_modules core guideline for npm first-party packages', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage('vite', '6.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $vendorFixture = realpath(fixture('vendor-guidelines/core-only'));

    $composer = guidelineComposerAllowingVendorPath($this->project);
    $composer->shouldReceive('resolveFirstPartyIgnisPath')
        ->andReturnUsing(fn(Package $package, string $subpath): ?string => $package->name() === 'vite' ? $vendorFixture : null);

    $guidelines = $composer->compose();

    expect($guidelines)
        ->toContain('Vendor Core Guideline')
        ->toContain('loaded from the vendor directory');
});

test('falls back to .ai/ when node_modules guideline path does not exist for npm package', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage('vite', '6.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $composer = guidelineComposerAllowingVendorPath($this->project);
    $composer->shouldReceive('resolveFirstPartyIgnisPath')->andReturn(null);

    expect($composer->compose())->toContain('=== cakephp/core rules ===');
});

test('user override resolves .md files for vendor-sourced guidelines', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $vendorFixture = realpath(fixture('vendor-guidelines/core-only'));
    $mdOverrideDir = fixture('.ai/guidelines-md-override');
    ensureDirectoryExists($mdOverrideDir . DS . 'pest');
    file_put_contents($mdOverrideDir . DS . 'pest' . DS . 'core.md', '# Pest Markdown Override');

    try {
        $composer = guidelineComposerAllowingVendorPath($this->project);
        $composer->shouldReceive('resolveFirstPartyIgnisPath')
            ->andReturnUsing(fn(Package $package, string $subpath): ?string => $package->name() === PackageRegistry::PEST ? $vendorFixture : null);
        $composer->shouldReceive('customGuidelinePath')
            ->andReturnUsing(fn(string $path = ''): string => $mdOverrideDir . ($path !== '' ? DS . ltrim($path, '/\\') : ''));

        $guidelines = $composer->guidelines()->toArray();
        $pestCore = $guidelines['pest/core'] ?? null;

        expect($pestCore)->not->toBeNull()
            ->and($pestCore['content'])->toContain('Pest Markdown Override')
            ->and($pestCore['content'])->not->toContain('Vendor Core Guideline');
    } finally {
        @unlink($mdOverrideDir . DS . 'pest' . DS . 'core.md');
        @rmdir($mdOverrideDir . DS . 'pest');
        @rmdir($mdOverrideDir);
    }
});

test('symlinked custom guidelines directory does not produce duplicates', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $realGuidelinesDir = realpath(fixture('.ai/guidelines'));
    $symlinkDir = fixture('.ai/symlinked-guidelines');

    if ((is_link($symlinkDir) || DirectoryLink::isLink($symlinkDir)) && !DirectoryLink::remove($symlinkDir)) {
        @unlink($symlinkDir);
    }

    $linked = DirectoryLink::create($realGuidelinesDir, $symlinkDir) || @symlink($realGuidelinesDir, $symlinkDir);

    if (!$linked && !is_dir($symlinkDir) && !is_link($symlinkDir)) {
        $this->markTestSkipped('Unable to create directory link for guidelines fixture');
    }

    try {
        $composer = Mockery::mock(GuidelineComposer::class, [$this->project])->makePartial();
        $composer
            ->shouldReceive('customGuidelinePath')
            ->andReturnUsing(fn(string $path = ''): string => $symlinkDir . ($path !== '' ? DS . ltrim($path, '/\\') : ''));

        $composed = $composer->compose();
        $overrideCount = substr_count((string)$composed, 'User Override CakePHP Core');

        expect($overrideCount)->toBe(1);
    } finally {
        if (DirectoryLink::isLink($symlinkDir)) {
            DirectoryLink::remove($symlinkDir);
        } elseif (is_link($symlinkDir)) {
            @unlink($symlinkDir);
        }
    }
});

test('symlinked custom guideline file does not produce duplicates', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $customDir = fixture('.ai/symlinked-file-guidelines');
    $externalFile = realpath(fixture('.ai/guidelines/cakephp/core.twig'));
    $linkPath = $customDir . DS . 'cakephp' . DS . 'core.twig';

    ensureDirectoryExists($customDir . DS . 'cakephp');

    if (is_link($linkPath)) {
        @unlink($linkPath);
    }

    if (!@symlink($externalFile, $linkPath)) {
        $this->markTestSkipped('Unable to create file symlink for guidelines fixture');
    }

    try {
        $composer = Mockery::mock(GuidelineComposer::class, [$this->project])->makePartial();
        $composer
            ->shouldReceive('customGuidelinePath')
            ->andReturnUsing(fn(string $path = ''): string => $customDir . ($path !== '' ? DS . ltrim($path, '/\\') : ''));

        $composed = $composer->compose();
        $overrideCount = substr_count((string)$composed, 'User Override CakePHP Core');

        expect($overrideCount)->toBe(1);
    } finally {
        @unlink($linkPath);
        @rmdir($customDir . DS . 'cakephp');
        @rmdir($customDir);
    }
});

test('cakephp v5 guidelines include 5.4 open and scoped deltas when installed version is 5.4', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.4.0'),
    ]);
    mockProjectPackages($this->project, $packages);

    $composed = $this->composer->compose();

    expect($composed)
        ->toContain('=== cakephp/v5 rules ===')
        ->toContain('installed: 5.4.0')
        ->toContain('$this->io')
        ->toContain('#[Configure]')
        ->toContain('#[RequestToDto]')
        ->toContain('projectAs()')
        ->toContain('subquery');
});

test('cakephp v5 guidelines omit 5.3 and 5.4 deltas when installed version is 5.2', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.2.0'),
    ]);
    mockProjectPackages($this->project, $packages);

    $composed = $this->composer->compose();

    expect($composed)
        ->toContain('=== cakephp/v5 rules ===')
        ->toContain('Version-specific guidance for CakePHP 5.x')
        ->not->toContain('#[RequestToDto]')
        ->not->toContain('projectAs()')
        ->not->toContain('$this->io')
        ->not->toContain('#[Configure]');
});

test('cakephp v5 guidelines include 5.3 deltas but not 5.4 when installed version is 5.3', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.3.1'),
    ]);
    mockProjectPackages($this->project, $packages);

    $composed = $this->composer->compose();

    expect($composed)
        ->toContain('=== cakephp/v5 rules ===')
        ->toContain('#[Configure]')
        ->toContain('projectAs()')
        ->toContain('RateLimitMiddleware')
        ->not->toContain('#[RequestToDto]')
        ->not->toContain('subquery');
});

test('cakephp v5 5.4 path-scoped deltas extract when scoped guidelines are enabled', function (): void {
    Configure::write('Ignis.rules.enabled', true);
    Configure::write('Ignis.rules.scoped_guidelines', true);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.4.0'),
    ]);
    mockProjectPackages($this->project, $packages);

    $guidelines = $this->composer->resolvedGuidelines()->toArray();
    $v5 = $guidelines['cakephp/v5'] ?? null;

    expect($v5)->not->toBeNull()
        ->and($v5['content'] ?? '')
        ->toContain('$this->io')
        ->toContain('#[Configure]')
        ->not->toContain('#[RequestToDto]')
        ->and($v5['scoped'] ?? [])
        ->not->toBeEmpty();

    $scopedBodies = array_map(
        fn(array $block): string => $block['body'],
        $v5['scoped'],
    );
    $joined = implode("\n", $scopedBodies);

    expect($joined)
        ->toContain('#[RequestToDto]')
        ->toContain('subquery')
        ->toContain('projectAs()');
});

test('cakephp v6 guidelines load for major 6 and omit cakephp v5 content', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '6.0.0'),
    ]);
    mockProjectPackages($this->project, $packages);

    $composed = $this->composer->compose();

    expect($composed)
        ->toContain('=== cakephp/v6 rules ===')
        ->toContain('installed: 6.0.0')
        ->toContain('$this->args')
        ->toContain('EventManagerInterface::on()')
        ->toContain('UUID **v7**')
        ->toContain('patchable')
        ->not->toContain('=== cakephp/v5 rules ===')
        ->not->toContain('#[RequestToDto]');
});

test('cakephp v6 path-scoped deltas extract when scoped guidelines are enabled', function (): void {
    Configure::write('Ignis.rules.enabled', true);
    Configure::write('Ignis.rules.scoped_guidelines', true);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '6.0.0'),
    ]);
    mockProjectPackages($this->project, $packages);

    $guidelines = $this->composer->resolvedGuidelines()->toArray();
    $v6 = $guidelines['cakephp/v6'] ?? null;

    expect($v6)->not->toBeNull()
        ->and($v6['content'] ?? '')
        ->toContain('$this->io')
        ->toContain('EventManagerInterface::on()')
        ->not->toContain('patchableFields')
        ->and($v6['scoped'] ?? [])
        ->not->toBeEmpty();

    $scopedBodies = array_map(
        fn(array $block): string => $block['body'],
        $v6['scoped'],
    );
    $joined = implode("\n", $scopedBodies);

    expect($joined)
        ->toContain('patchableFields')
        ->toContain('getQueryParams()')
        ->toContain('connectAttributes()')
        ->toContain('{foo}');
});

test('php version guidelines accumulate non-empty minors up to the running version', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);
    mockProjectPackages($this->project, $packages);

    $composer = Mockery::mock(GuidelineComposer::class, [$this->project])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $composer->shouldReceive('phpVersion')->andReturn('8.4');

    $composed = $composer->compose();

    expect($composed)
        ->toContain('=== php rules ===')
        ->toContain('=== php/v8.4 rules ===')
        ->toContain('array_find')
        ->not->toContain('=== php/v8.5 rules ===')
        ->not->toContain('array_first');
});

test('php 8.5 includes prior non-empty php minor guidelines', function (): void {
    Configure::write('Ignis.rules.enabled', false);

    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
    ]);
    mockProjectPackages($this->project, $packages);

    $composer = Mockery::mock(GuidelineComposer::class, [$this->project])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $composer->shouldReceive('phpVersion')->andReturn('8.5');

    $composed = $composer->compose();

    expect($composed)
        ->toContain('=== php/v8.4 rules ===')
        ->toContain('=== php/v8.5 rules ===')
        ->toContain('array_find')
        ->toContain('array_first')
        ->not->toContain('=== php/v8.2 rules ===')
        ->not->toContain('=== php/v8.3 rules ===');
});
