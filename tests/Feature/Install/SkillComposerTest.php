<?php

declare(strict_types=1);

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Ignis\Install\Skill;
use Crustum\Ignis\Install\SkillComposer;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\Enums\PackageSource;
use Crustum\Inspector\Ecosystems\Ecosystem;
use Crustum\Inspector\Ecosystems\JsEcosystem;
use Crustum\Inspector\Package;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;

/**
 * @param \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill> $skills Skills collection
 * @param string $name Skill name
 * @return \Crustum\Ignis\Install\Skill|null
 */
function skillNamed(Collection $skills, string $name): ?Skill
{
    $items = $skills->toArray();

    $skill = $items[$name] ?? null;

    return $skill instanceof Skill ? $skill : null;
}

/**
 * @param list<\Crustum\Inspector\Package> $packages PHP packages
 * @return \Crustum\Inspector\ProjectManager
 */
function mockSkillProject(array $packages): ProjectManager
{
    $project = Mockery::mock(ProjectManager::class);
    $php = Mockery::mock(Ecosystem::class);
    $js = Mockery::mock(JsEcosystem::class);

    $project->shouldReceive('php')->andReturn($php);
    $project->shouldReceive('js')->andReturn($js);
    $php->shouldReceive('packages')->andReturn(new PackageCollection($packages));
    $js->shouldReceive('packages')->andReturn(new PackageCollection([]));
    $php->shouldReceive('uses')->andReturnUsing(
        fn(string $name): bool => array_any($packages, fn(\Crustum\Inspector\Package $package): bool => $package->name() === $name),
    );
    $js->shouldReceive('uses')->andReturn(false);

    return $project;
}

beforeEach(function (): void {
    useTestApp();
    $this->project = mockSkillProject([]);
});

afterEach(function (): void {
    resetTestApp();
});

test('skills return a collection keyed by skill name', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package(PackageRegistry::MCP, '1.0.0', PackageSource::Composer, direct: true),
    ]);

    $vendorFixture = realpath(testDirectory('Fixtures/vendor-skills'));
    expect($vendorFixture)->not->toBeFalse();

    $composer = Mockery::mock(SkillComposer::class, [$project])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $composer->shouldReceive('resolveFirstPartyIgnisPath')
        ->andReturnUsing(fn(Package $package, string $subpath): ?string => $package->name() === PackageRegistry::MCP ? $vendorFixture : null);

    $skills = $composer->skills();

    expect($skills)
        ->toBeInstanceOf(Collection::class)
        ->and(skillNamed($skills, 'mcp-development'))->toBeInstanceOf(Skill::class);
});

test('ships the infer-conventions core skill regardless of installed packages', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]);

    $skills = (new SkillComposer($project))->skills();
    $skill = skillNamed($skills, 'infer-conventions');

    expect($skill)
        ->not->toBeNull()
        ->and($skill->package)->toBe('ignis')
        ->and($skill->description)->not->toBeEmpty()
        ->and($skill->path)->toBeDirectory()
        ->and($skill->path . DIRECTORY_SEPARATOR . 'references' . DIRECTORY_SEPARATOR . 'checklist.twig')->toBeFile();
});

test('the infer-conventions core skill can be excluded via config', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]);

    Configure::write('Ignis.skills.exclude', ['infer-conventions']);

    $skills = (new SkillComposer($project))->skills();

    expect(skillNamed($skills, 'infer-conventions'))->toBeNull();

    Configure::delete('Ignis.skills.exclude');
});

test('skills only includes skills for installed packages', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]);

    $skills = (new SkillComposer($project))->skills();

    expect(skillNamed($skills, 'mcp-development'))->toBeNull();
});

test('skills result is cached', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]);

    $composer = new SkillComposer($project);

    expect($composer->skills())->toBe($composer->skills());
});

test('config change clears skills cache', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]);

    $composer = new SkillComposer($project);
    $first = $composer->skills();

    $composer->config(new GuidelineConfig());

    expect($composer->skills())->not->toBe($first);
});

test('excludes package skills when indirectly required', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package(PackageRegistry::MCP, '1.0.0', PackageSource::Composer, direct: false),
    ]);

    $composer = Mockery::mock(SkillComposer::class, [$project])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $composer->shouldReceive('resolveFirstPartyIgnisPath')->andReturn(null);

    $skills = $composer->skills();

    expect(skillNamed($skills, 'mcp-development'))->toBeNull();
});

test('excludes skills listed in config exclude list', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package(PackageRegistry::MCP, '1.0.0', PackageSource::Composer, direct: true),
    ]);

    Configure::write('Ignis.skills.exclude', ['mcp-development']);

    $vendorFixture = realpath(testDirectory('Fixtures/vendor-skills'));
    expect($vendorFixture)->not->toBeFalse();

    $composer = Mockery::mock(SkillComposer::class, [$project])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $composer->shouldReceive('resolveFirstPartyIgnisPath')
        ->andReturnUsing(fn(Package $package, string $subpath): ?string => $package->name() === PackageRegistry::MCP ? $vendorFixture : null);

    $skills = $composer->skills();

    expect(skillNamed($skills, 'mcp-development'))->toBeNull();

    Configure::delete('Ignis.skills.exclude');
});

test('vendor skills override bundled skills with the same name', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package(PackageRegistry::MCP, '1.0.0', PackageSource::Composer, direct: true),
    ]);

    $vendorFixture = realpath(testDirectory('Fixtures/vendor-skills'));
    expect($vendorFixture)->not->toBeFalse();

    $composer = Mockery::mock(SkillComposer::class, [$project])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $composer->shouldReceive('resolveFirstPartyIgnisPath')
        ->andReturnUsing(fn(Package $package, string $subpath): ?string => $package->name() === PackageRegistry::MCP ? $vendorFixture : null);

    $skills = $composer->skills();

    expect(skillNamed($skills, 'mcp-development'))->not->toBeNull()
        ->and(skillNamed($skills, 'mcp-development')->description)->toBe('Vendor-overridden MCP skill');
});

test('returns all third-party skills when aiGuidelines is uninitialized', function (): void {
    useTestApp();

    $project = mockSkillProject([]);

    $skillDir = base_path('vendor/some/third-party/resources/ignis/skills/third-party-skill');
    ensureDirectoryExists($skillDir);
    file_put_contents($skillDir . '/SKILL.md', "---\nname: third-party-skill\ndescription: A vendor-provided skill\n---\n\n# Content\n");
    file_put_contents(base_path('composer.json'), json_encode(['require' => ['some/third-party' => '^1.0']]));

    try {
        $skills = (new SkillComposer($project))->skills();

        expect(skillNamed($skills, 'third-party-skill'))->not->toBeNull();
    } finally {
        @unlink($skillDir . '/SKILL.md');
        @rmdir($skillDir);
        @rmdir(dirname($skillDir));
        @rmdir(dirname($skillDir, 2));
        @rmdir(dirname($skillDir, 3));
        @rmdir(dirname($skillDir, 4));
        @rmdir(dirname($skillDir, 5));
        @unlink(base_path('composer.json'));
        resetTestApp();
    }
});

test('frontmatter parsing ignores HTML comments injected by third-party packages', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]);

    $composer = new SkillComposer($project);
    $method = new ReflectionMethod($composer, 'parseSkillFrontmatter');

    $content = <<<'HTML'
        <!-- Start compiled view: 'storage/framework/views/bf9245cd.twig' -->
        ---
        name: pest-testing
        description: "Write and run tests with Pest"
        ---

        # Content
        HTML;

    $result = $method->invoke($composer, $content);

    expect($result)
        ->toHaveKey('name', 'pest-testing')
        ->toHaveKey('description', 'Write and run tests with Pest');
});

test('skill packs contribute skills for installed target packages', function (): void {
    $fixture = realpath(testDirectory('Fixtures/pack'));
    expect($fixture)->not->toBeFalse();

    $project = mockSkillProject([
        new Package('cakephp/queue', '2.0.0', PackageSource::Composer, direct: true),
    ]);

    $composer = new class ($project, $fixture) extends SkillComposer {
        /**
         * @param \Crustum\Inspector\ProjectManager $project Project manager
         * @param string $fixture Pack fixture root
         */
        public function __construct(ProjectManager $project, private string $fixture)
        {
            parent::__construct($project);
        }

        protected function getSkillPackSkills(): Collection
        {
            $skills = [];
            $discovery = new \Crustum\Ignis\Install\SkillPackDiscovery($this->project, [
                'crustum/fixture-cakephp-skills' => $this->fixture,
            ]);

            foreach ($discovery->discover() as $entry) {
                foreach ($this->discoverSkillsFromDirectory($entry['skillPath'], $entry['target']) as $key => $skill) {
                    $skills[$key] = $skill;
                }
            }

            return new Collection($skills);
        }
    };

    $skills = $composer->skills();

    expect(skillNamed($skills, 'queue-development'))
        ->not->toBeNull()
        ->and(skillNamed($skills, 'queue-development')?->package)->toBe('cakephp/queue')
        ->and(skillNamed($skills, 'migrations-development'))->toBeNull();
});
