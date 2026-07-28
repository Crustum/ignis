<?php

declare(strict_types=1);

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Ignis\Install\GuidelineComposer;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Ignis\Install\RuleComposer;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;

beforeEach(function (): void {
    useTestApp();
    Configure::write('Ignis.rules.enabled', true);
    Configure::write('Ignis.rules.scoped_guidelines', true);
    Configure::delete('Ignis.guidelines.exclude');

    $this->project = Mockery::mock(ProjectManager::class);
    $this->guidelines = new GuidelineComposer($this->project);
});

afterEach(function (): void {
    Configure::delete('Ignis.guidelines.exclude');
    Configure::delete('Ignis.rules.scoped_guidelines');
    resetTestApp();
    Mockery::close();
});

/**
 * Partial GuidelineComposer that reads user guidelines from a fixture tree.
 *
 * @param \Crustum\Inspector\ProjectManager $project Project manager
 * @param string $fixture Fixture directory under tests/Fixtures
 * @return \Crustum\Ignis\Install\GuidelineComposer
 */
function composerWithFixtureGuidelines(ProjectManager $project, string $fixture): GuidelineComposer
{
    $dir = fixture($fixture);

    $guidelines = Mockery::mock(GuidelineComposer::class, [$project])->makePartial();
    $guidelines
        ->shouldReceive('customGuidelinePath')
        ->andReturnUsing(fn(string $path = ''): string => $dir . ($path !== '' ? DS . ltrim($path, '/\\') : ''));

    return $guidelines;
}

/**
 * Scaffold third-party package guideline files under vendor/, require them in composer.json,
 * run the assertions, then remove everything the scaffold created.
 *
 * @param array<string, array<string, string>> $packages Package name => [relative guideline file => contents]
 * @param \Closure $assert Assertion callback
 * @return void
 */
function withThirdPartyPackages(array $packages, Closure $assert): void
{
    $requires = [];

    foreach ($packages as $name => $files) {
        $requires[$name] = '^1.0';
        $guidelineDir = base_path('vendor/' . $name . '/resources/ignis/guidelines');

        foreach ($files as $relativePath => $contents) {
            $path = $guidelineDir . '/' . $relativePath;
            ensureDirectoryExists(dirname($path));
            file_put_contents($path, $contents);
        }
    }

    file_put_contents(base_path('composer.json'), (string)json_encode(['require' => $requires]));

    try {
        $assert();
    } finally {
        foreach (array_keys($packages) as $name) {
            $vendorRoot = base_path('vendor/' . explode('/', $name)[0]);
            if (is_dir($vendorRoot)) {
                deleteDirectory($vendorRoot);
            }
        }

        @unlink(base_path('composer.json'));
    }
}

/**
 * Find the first rule matching a predicate.
 *
 * @param \Cake\Collection\Collection<string, array{paths: array<int, string>, content: string}> $rules Rules
 * @param callable(array{paths: array<int, string>, content: string}, string): bool $predicate Matcher
 * @return array{paths: array<int, string>, content: string}|null
 */
function findRule(Collection $rules, callable $predicate): ?array
{
    foreach ($rules as $key => $rule) {
        if ($predicate($rule, (string)$key)) {
            return $rule;
        }
    }

    return null;
}

/**
 * Whether any rule matches a predicate.
 *
 * @param \Cake\Collection\Collection<string, array{paths: array<int, string>, content: string}> $rules Rules
 * @param callable(array{paths: array<int, string>, content: string}, string): bool $predicate Matcher
 * @return bool
 */
function ruleExists(Collection $rules, callable $predicate): bool
{
    return findRule($rules, $predicate) !== null;
}

/**
 * Collect rules matching a predicate.
 *
 * @param \Cake\Collection\Collection<string, array{paths: array<int, string>, content: string}> $rules Rules
 * @param callable(array{paths: array<int, string>, content: string}, string): bool $predicate Matcher
 * @return array<string, array{paths: array<int, string>, content: string}>
 */
function filterRules(Collection $rules, callable $predicate): array
{
    $matched = [];

    foreach ($rules as $key => $rule) {
        if ($predicate($rule, (string)$key)) {
            $matched[(string)$key] = $rule;
        }
    }

    return $matched;
}

test('discovers a scoped block for an installed package', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $rules = (new RuleComposer($this->guidelines))->rules();
    $pestRule = findRule($rules, fn(array $rule, string $key): bool => str_starts_with($key, 'pest/core#'));

    expect($pestRule)->not->toBeNull()
        ->and($pestRule['paths'])->toBe(['tests/**'])
        ->and($pestRule['content'])->toContain('Pest')
        ->and($pestRule['content'])->not->toContain('@scoped')
        ->and($pestRule['content'])->not->toContain('@endscoped');
});

test('renders twig expressions inside a scoped block', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $rules = (new RuleComposer($this->guidelines))->rules();
    $pestRule = findRule($rules, fn(array $rule, string $key): bool => str_starts_with($key, 'pest/core#'));

    expect($pestRule)->not->toBeNull()
        ->and($pestRule['content'])->toContain('pest');
});

test('excludes rules for a package excluded by priority', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
        inspectorPackage(PackageRegistry::PHPUNIT, '10.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $rules = (new RuleComposer($this->guidelines))->rules();

    expect(ruleExists($rules, fn(array $rule, string $key): bool => str_starts_with($key, 'pest/core#')))->toBeTrue()
        ->and(ruleExists($rules, fn(array $rule, string $key): bool => str_starts_with($key, 'phpunit/core#')))->toBeFalse();
});

test('excludes rules for a guideline listed in ignis.guidelines.exclude', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    Configure::write('Ignis.guidelines.exclude', ['pest/core']);

    $rules = (new RuleComposer($this->guidelines))->rules();

    expect(ruleExists($rules, fn(array $rule, string $key): bool => str_starts_with($key, 'pest/core#')))->toBeFalse();
});

test('overriding a guideline via .ai/guidelines also overrides its scoped blocks', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/pest-override');

    $rules = (new RuleComposer($guidelines))->rules();
    $pestRule = findRule($rules, fn(array $rule, string $key): bool => str_starts_with($key, 'pest/core#'));

    expect($pestRule)->not->toBeNull()
        ->and($pestRule['paths'])->toBe(['tests/Feature/**'])
        ->and($pestRule['content'])->toContain("Always use this project's own Pest conventions")
        ->and($pestRule['content'])->not->toContain('This project uses Pest for testing');
});

test('a scoped block from a third-party package guideline produces a managed rule file', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    withThirdPartyPackages([
        'some/third-party' => [
            'core.md' => "# Some Third Party\n\n@scoped(['app/Widgets/**'])\n## Widgets\n\nThird-party widget rule.\n@endscoped\n",
        ],
    ], function (): void {
        $managed = (new RuleComposer($this->guidelines))->composeManaged();
        $widgetFile = findRule($managed, fn(array $file): bool => $file['paths'] === ['app/Widgets/**']);

        expect($widgetFile)->not->toBeNull()
            ->and($widgetFile['content'])->toContain('Third-party widget rule');
    });
});

test('two scoped guideline files from one third-party package each produce a rule', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    withThirdPartyPackages([
        'some/multi' => [
            'core.md' => "# Multi Core\n\n@scoped(['app/Alpha/**'])\n## Alpha\n\nAlpha rule.\n@endscoped\n",
            'extra.md' => "# Multi Extra\n\n@scoped(['app/Beta/**'])\n## Beta\n\nBeta rule.\n@endscoped\n",
        ],
    ], function (): void {
        $rules = (new RuleComposer($this->guidelines))->rules();
        $alpha = findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/Alpha/**']);
        $beta = findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/Beta/**']);

        expect($alpha)->not->toBeNull()
            ->and($alpha['content'])->toContain('Alpha rule')
            ->and($beta)->not->toBeNull()
            ->and($beta['content'])->toContain('Beta rule');
    });
});

test('composeManaged merges rules that share the exact same paths into one file', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    withThirdPartyPackages([
        'some/alpha' => [
            'core.md' => "# Alpha\n\n@scoped(['resources/js/**'])\n## Alpha JS\n\nAlpha front-end rule.\n@endscoped\n",
        ],
        'some/beta' => [
            'core.md' => "# Beta\n\n@scoped(['resources/js/**'])\n## Beta JS\n\nBeta front-end rule.\n@endscoped\n",
        ],
    ], function (): void {
        $managed = (new RuleComposer($this->guidelines))->composeManaged();
        $merged = findRule($managed, fn(array $file): bool => $file['paths'] === ['resources/js/**']);

        expect($merged)->not->toBeNull()
            ->and($merged['content'])
            ->toContain('Alpha front-end rule')
            ->toContain('Beta front-end rule');
    });
});

test('composeManaged groups rules with different paths into separate files', function (): void {
    $packages = new PackageCollection([
        inspectorPackage(PackageRegistry::CAKEPHP, '5.0.0'),
        inspectorPackage(PackageRegistry::PEST, '3.0.0'),
    ]);

    mockProjectPackages($this->project, $packages);

    withThirdPartyPackages([
        'some/widgets' => [
            'core.md' => "# Widgets\n\n@scoped(['app/Widgets/**'])\n## Widgets\n\nWidget rule.\n@endscoped\n",
        ],
    ], function (): void {
        $managed = (new RuleComposer($this->guidelines))->composeManaged();

        $widgetFile = findRule($managed, fn(array $file): bool => in_array('app/Widgets/**', $file['paths'], true));
        $testsFile = findRule(
            $managed,
            fn(array $file): bool => in_array('tests/**', $file['paths'], true) && count($file['paths']) === 1,
        );

        expect($managed->count())->toBeGreaterThan(1)
            ->and($widgetFile)->not->toBeNull()
            ->and($testsFile)->not->toBeNull()
            ->and($widgetFile['content'])->not->toContain('Pest')
            ->and($testsFile['content'])->not->toContain('Widget');
    });
});

test('a scoped block inside a false Twig conditional is not extracted as a rule', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/conditional');

    $rules = (new RuleComposer($guidelines))->rules();

    expect(findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/Never/**']))->toBeNull()
        ->and(findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/Always/**']))->not->toBeNull();
});

test('a headingless scoped block gets a slug-derived title instead of its first line', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/headingless');

    $managed = (new RuleComposer($guidelines))->composeManaged();
    $file = findRule($managed, fn(array $file): bool => $file['paths'] === ['app/Widgets/**']);

    expect($file)->not->toBeNull()
        ->and($file['title'])->toBe('Widgets')
        ->and($file['content'])->toContain('Always use widget factories');
});

test('a glob containing a bracket character class survives scoped path parsing', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/bracket-glob');

    $rules = (new RuleComposer($guidelines))->rules();
    $rule = findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/[Ff]oo/**']);

    expect($rule)->not->toBeNull()
        ->and($rule['content'])->toContain('Bracket glob rule');
});

test('a scoped block with no parseable paths keeps its content inline instead of losing it', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/empty-paths');

    $rules = (new RuleComposer($guidelines))->rules();
    $inline = $guidelines->guidelines()->toArray()['.ai/empty']['content'] ?? '';

    expect(ruleExists($rules, fn(array $rule): bool => str_contains($rule['content'], 'must not vanish')))->toBeFalse()
        ->and($inline)->toContain('Guidance that must not vanish');
});

test('a glob containing parentheses survives scoped path parsing', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/paren-glob');

    $rules = (new RuleComposer($guidelines))->rules();
    $rule = findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/(Foo)/**']);

    expect($rule)->not->toBeNull()
        ->and($rule['content'])->toContain('Paren glob rule');
});

test('nested scoped blocks never leak sentinels into rule content or inline output', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/nested');

    $rules = (new RuleComposer($guidelines))->rules();
    $inline = $guidelines->guidelines()->toArray()['.ai/nested']['content'] ?? '';

    foreach ($rules as $rule) {
        expect($rule['content'])->not->toContain('___SCOPED');
    }

    expect($inline)->not->toContain('___SCOPED');
});

test('a literal @scoped example inside a fenced code block is not extracted as a rule', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/fenced-literal');

    $rules = (new RuleComposer($guidelines))->rules();
    $inline = $guidelines->guidelines()->toArray()['.ai/fenced']['content'] ?? '';

    expect(findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/Example/**']))->toBeNull()
        ->and($inline)->toContain("@scoped(['app/Example/**'])")
        ->and($inline)->toContain('@endscoped');
});

test('a literal @scoped example inside a ignissnippet is not extracted as a rule', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/snippet-literal');

    $rules = (new RuleComposer($guidelines))->rules();
    $inline = $guidelines->guidelines()->toArray()['.ai/snippet']['content'] ?? '';

    expect(findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/Snippet/**']))->toBeNull()
        ->and($inline)->toContain("@scoped(['app/Snippet/**'])");
});

test('a literal @scoped example inside a ~~~ fenced block is not extracted as a rule', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/tilde-fenced');

    $rules = (new RuleComposer($guidelines))->rules();
    $inline = $guidelines->guidelines()->toArray()['.ai/tilde']['content'] ?? '';

    expect(findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/Example/**']))->toBeNull()
        ->and($inline)->toContain("@scoped(['app/Example/**'])")
        ->and($inline)->toContain('@endscoped');
});

test('re-inlining a scoped block preserves its indentation instead of trimming it', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    Configure::write('Ignis.rules.enabled', false);

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/indented');
    $inline = $guidelines->guidelines()->toArray()['.ai/indented']['content'] ?? '';

    expect($inline)->toContain("\n    Indented body line.");
});

test('nested scoped blocks are left inline instead of being mis-scoped', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    $guidelines = composerWithFixtureGuidelines($this->project, 'rules/nested');

    $rules = (new RuleComposer($guidelines))->rules();
    $inline = $guidelines->guidelines()->toArray()['.ai/nested']['content'] ?? '';

    expect(findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/Outer/**']))->toBeNull()
        ->and(findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/Inner/**']))->toBeNull()
        ->and($inline)->toContain('Outer rule.')
        ->and($inline)->toContain('Inner rule.')
        ->and($inline)->not->toContain('___SCOPED');
});

test('two third-party guideline files sharing a basename in different dirs are both kept', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    withThirdPartyPackages([
        'some/nested' => [
            'admin/core.md' => "# Admin\n\n@scoped(['app/Admin/**'])\n## Admin\n\nAdmin rule.\n@endscoped\n",
            'api/core.md' => "# Api\n\n@scoped(['app/Api/**'])\n## Api\n\nApi rule.\n@endscoped\n",
        ],
    ], function (): void {
        $rules = (new RuleComposer($this->guidelines))->rules();
        $admin = findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/Admin/**']);
        $api = findRule($rules, fn(array $rule): bool => $rule['paths'] === ['app/Api/**']);

        expect($admin)->not->toBeNull()
            ->and($admin['content'])->toContain('Admin rule')
            ->and($api)->not->toBeNull()
            ->and($api['content'])->toContain('Api rule');
    });
});

test('a user override in .ai/guidelines overrides a third-party guideline', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    $overrideDir = base_path('.ai/guidelines/some/ovr');
    ensureDirectoryExists($overrideDir);
    file_put_contents($overrideDir . '/core.md', "# Overridden\n\nProject-specific third-party guidance.\n");

    withThirdPartyPackages([
        'some/ovr' => ['core.md' => "# Vendor Default\n\nOriginal third-party guidance.\n"],
    ], function (): void {
        $composer = new GuidelineComposer($this->project);
        $guideline = $composer->resolvedGuidelines()->toArray()['some/ovr/core'] ?? null;

        expect($guideline)->not->toBeNull()
            ->and($guideline['content'])->toContain('Project-specific third-party guidance')
            ->and($guideline['content'])->not->toContain('Original third-party guidance');
    });
});

test('third-party package selection matches multi-segment guideline keys', function (): void {
    mockProjectPackages($this->project, new PackageCollection([]));

    withThirdPartyPackages([
        'some/sel' => ['admin/core.md' => "# Admin\n\nAdmin guidance.\n"],
    ], function (): void {
        $selected = new GuidelineConfig();
        $selected->aiGuidelines = ['some/sel'];

        $composer = (new GuidelineComposer($this->project))->config($selected);

        expect(array_keys($composer->resolvedGuidelines()->toArray()))->toContain('some/sel/admin/core');

        $other = new GuidelineConfig();
        $other->aiGuidelines = ['some/other'];

        $composer = (new GuidelineComposer($this->project))->config($other);

        expect(array_keys($composer->resolvedGuidelines()->toArray()))->not->toContain('some/sel/admin/core');
    });
});
