<?php

declare(strict_types=1);

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Ignis\Install\Skill;
use Crustum\Ignis\Install\SkillComposer;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Ignis\Support\SkillParseFailures;
use Crustum\Inspector\Enums\PackageSource;
use Crustum\Inspector\Ecosystems\Ecosystem;
use Crustum\Inspector\Ecosystems\JsEcosystem;
use Crustum\Inspector\Package;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;
use JMac\Testing\Double;
use JMac\Testing\OverriddenDouble;

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
 * @return \JMac\Testing\OverriddenDouble
 */
function mockSkillProject(array $packages): OverriddenDouble
{
    $project = Double::for(ProjectManager::class, override: true);
    $php = Double::for(Ecosystem::class);
    $js = Double::for(JsEcosystem::class);

    $project->allows('php')->returns($php);
    $project->allows('js')->returns($js);
    $php->allows('packages')->returns(new PackageCollection($packages));
    $js->allows('packages')->returns(new PackageCollection([]));
    $php->allows('uses')->resolves(
        fn(string $name): bool => array_any($packages, fn(Package $package): bool => $package->name() === $name),
    );
    $js->allows('uses')->returns(false);

    return $project;
}

beforeEach(function (): void {
    useTestApp();
    $this->project = mockSkillProject([]);
});

afterEach(function (): void {
    resetTestApp();
});

/**
 * Share a fresh skill parse-failures recorder through the test container.
 *
 * @return \Crustum\Ignis\Support\SkillParseFailures
 */
function sharedSkillParseFailures(): SkillParseFailures
{
    $failures = new SkillParseFailures();
    $container = freshTestContainer();
    bindInstance($container, SkillParseFailures::class, $failures);
    Configure::write('app.container', $container);

    return $failures;
}

/**
 * Stage a skill fixture into the test application skills directory.
 *
 * @param string $fixture Fixture directory name under tests/Fixtures/skills
 * @return string Staged skill directory
 */
function stageFixtureSkill(string $fixture): string
{
    $target = testAppPath('.ai/skills/' . $fixture);
    ensureDirectoryExists($target);
    copy(
        testDirectory('Fixtures/skills/' . $fixture . '/SKILL.md'),
        $target . DIRECTORY_SEPARATOR . 'SKILL.md',
    );

    return $target;
}

test('skills return a collection keyed by skill name', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        inspectorPackage(PackageRegistry::MCP, '1.0.0', path: fixture('vendor-packages/skills'))->setDirect(),
    ]);

    $skills = (new SkillComposer($project->instance()))->skills();

    expect($skills)
        ->toBeInstanceOf(Collection::class)
        ->and(skillNamed($skills, 'mcp-development'))->toBeInstanceOf(Skill::class);
});

test('ships the infer-conventions core skill regardless of installed packages', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]);

    $skills = (new SkillComposer($project->instance()))->skills();
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

    $skills = (new SkillComposer($project->instance()))->skills();

    expect(skillNamed($skills, 'infer-conventions'))->toBeNull();

    Configure::delete('Ignis.skills.exclude');
});

test('skills only includes skills for installed packages', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]);

    $skills = (new SkillComposer($project->instance()))->skills();

    expect(skillNamed($skills, 'mcp-development'))->toBeNull();
});

test('skills result is cached', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]);

    $composer = new SkillComposer($project->instance());

    expect($composer->skills())->toBe($composer->skills());
});

test('config change clears skills cache', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]);

    $composer = new SkillComposer($project->instance());
    $first = $composer->skills();

    $composer->config(new GuidelineConfig());

    expect($composer->skills())->not->toBe($first);
});

test('excludes package skills when indirectly required', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package(PackageRegistry::MCP, '1.0.0', PackageSource::Composer, direct: false),
    ]);

    $skills = (new SkillComposer($project->instance()))->skills();

    expect(skillNamed($skills, 'mcp-development'))->toBeNull();
});

test('excludes skills listed in config exclude list', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        inspectorPackage(PackageRegistry::MCP, '1.0.0', path: fixture('vendor-packages/skills'))->setDirect(),
    ]);

    Configure::write('Ignis.skills.exclude', ['mcp-development']);

    $skills = (new SkillComposer($project->instance()))->skills();

    expect(skillNamed($skills, 'mcp-development'))->toBeNull();

    Configure::delete('Ignis.skills.exclude');
});

test('vendor skills override bundled skills with the same name', function (): void {
    $project = mockSkillProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        inspectorPackage(PackageRegistry::MCP, '1.0.0', path: fixture('vendor-packages/skills'))->setDirect(),
    ]);

    $skills = (new SkillComposer($project->instance()))->skills();

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
        $skills = (new SkillComposer($project->instance()))->skills();

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

    $composer = new SkillComposer($project->instance());
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

    $composer = new class ($project->instance(), $fixture) extends SkillComposer {
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

test('does not parse invalid skills from excluded third-party packages', function (): void {
    $previous = ProjectRoot::override();
    $root = testAppTmpPath('ignis-skills-' . uniqid());
    $skillDir = implode(DIRECTORY_SEPARATOR, [$root, 'vendor', 'some', 'third-party', 'resources', 'ignis', 'skills', 'third-party-skill']);
    mkdir($skillDir, 0777, true);
    copy(
        testDirectory('Fixtures/skills/broken-frontmatter/SKILL.md'),
        $skillDir . DIRECTORY_SEPARATOR . 'SKILL.md',
    );
    file_put_contents(
        $root . DIRECTORY_SEPARATOR . 'composer.json',
        (string)json_encode(['require' => ['some/third-party' => '^1.0']]),
    );
    ProjectRoot::set($root);
    $failures = sharedSkillParseFailures();

    try {
        $config = new GuidelineConfig();
        $config->aiGuidelines = ['other/package'];

        $skills = (new SkillComposer($this->project->instance()))->config($config)->skills();

        expect(skillNamed($skills, 'broken-frontmatter'))->toBeNull()
            ->and($failures->isEmpty())->toBeTrue();
    } finally {
        Configure::delete('app.container');
        ProjectRoot::set($previous);
        deleteDirectory($root);
    }
});

test('a skill with invalid YAML frontmatter is skipped and records the failure', function (): void {
    $failures = sharedSkillParseFailures();
    $skillDir = stageFixtureSkill('broken-frontmatter');

    try {
        $skills = (new SkillComposer($this->project->instance()))->skills();

        expect(skillNamed($skills, 'broken-frontmatter'))->toBeNull()
            ->and($failures->skillNames())->toBe(['broken-frontmatter'])
            ->and($failures->all()[0]['reason'])
            ->toContain('A colon cannot be used in an unquoted mapping value')
            ->toContain('description: Does a thing. Covers: the important bit.');
    } finally {
        Configure::delete('app.container');
        deleteDirectory($skillDir);
    }
});

test('a skill with unclosed frontmatter is skipped and records the failure', function (): void {
    $failures = sharedSkillParseFailures();
    $skillDir = stageFixtureSkill('unclosed-frontmatter');

    try {
        $skills = (new SkillComposer($this->project->instance()))->skills();

        expect(skillNamed($skills, 'unclosed-frontmatter'))->toBeNull()
            ->and($failures->skillNames())->toBe(['unclosed-frontmatter'])
            ->and($failures->all()[0]['reason'])->toContain('no closing delimiter');
    } finally {
        Configure::delete('app.container');
        deleteDirectory($skillDir);
    }
});

test('a skill whose frontmatter omits the name is skipped and records the failure', function (): void {
    $failures = sharedSkillParseFailures();
    $skillDir = stageFixtureSkill('incomplete-frontmatter');

    try {
        $skills = (new SkillComposer($this->project->instance()))->skills();

        expect(skillNamed($skills, 'incomplete-frontmatter'))->toBeNull()
            ->and($failures->skillNames())->toBe(['incomplete-frontmatter'])
            ->and($failures->all()[0]['reason'])->toContain('[name] and [description]');
    } finally {
        Configure::delete('app.container');
        deleteDirectory($skillDir);
    }
});

test('a skill without frontmatter is treated as absent', function (): void {
    $failures = sharedSkillParseFailures();
    $skillDir = stageFixtureSkill('no-frontmatter');

    try {
        $skills = (new SkillComposer($this->project->instance()))->skills();

        expect(skillNamed($skills, 'no-frontmatter'))->toBeNull()
            ->and($failures->isEmpty())->toBeTrue();
    } finally {
        Configure::delete('app.container');
        deleteDirectory($skillDir);
    }
});

test('discovers third-party npm skills from inspector packages', function (): void {
    $path = stageInspectorPackage('@some-scope/third-party', 'skills');
    $skillDir = $path . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'ignis'
        . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'npm-third-party-skill';

    ensureDirectoryExists($skillDir);
    file_put_contents(
        $skillDir . DIRECTORY_SEPARATOR . 'SKILL.md',
        "---\nname: npm-third-party-skill\ndescription: An npm vendor-provided skill\n---\n\n# Content\n",
    );

    try {
        $project = Double::for(ProjectManager::class, override: true);

        mockProjectPackages($project, new PackageCollection([
            inspectorPackage('@some-scope/third-party', '1.0.0', path: $path)->setDirect(),
        ]));

        $skills = (new SkillComposer($project->instance()))->skills();

        expect(skillNamed($skills, 'npm-third-party-skill'))->not->toBeNull();
    } finally {
        clearInspectorPackages();
    }
});

test('first-party npm skills load without the third-party opt-in', function (): void {
    $firstPartyPath = stageInspectorPackage('@crustum/some-package', 'skills');
    $firstPartySkillDir = $firstPartyPath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'ignis'
        . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'ignis-skill';

    ensureDirectoryExists($firstPartySkillDir);
    file_put_contents(
        $firstPartySkillDir . DIRECTORY_SEPARATOR . 'SKILL.md',
        "---\nname: ignis-skill\ndescription: A first-party skill\n---\n\n# Content\n",
    );

    $thirdPartyPath = stageInspectorPackage('@some-scope/third-party', 'skills');
    $thirdPartySkillDir = $thirdPartyPath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'ignis'
        . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'npm-third-party-skill';

    ensureDirectoryExists($thirdPartySkillDir);
    file_put_contents(
        $thirdPartySkillDir . DIRECTORY_SEPARATOR . 'SKILL.md',
        "---\nname: npm-third-party-skill\ndescription: An npm vendor-provided skill\n---\n\n# Content\n",
    );

    try {
        $project = Double::for(ProjectManager::class, override: true);

        mockProjectPackages($project, new PackageCollection([
            inspectorPackage('@crustum/some-package', '1.0.0', path: $firstPartyPath)->setDirect(),
            inspectorPackage('@some-scope/third-party', '1.0.0', path: $thirdPartyPath)->setDirect(),
        ]));

        $config = new GuidelineConfig();
        $config->aiGuidelines = [];

        $skills = (new SkillComposer($project->instance()))->config($config)->skills();

        expect(skillNamed($skills, 'ignis-skill'))->not->toBeNull()
            ->and(skillNamed($skills, 'npm-third-party-skill'))->toBeNull();
    } finally {
        clearInspectorPackages();
    }
});
