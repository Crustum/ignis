<?php

declare(strict_types=1);

use Cake\Collection\Collection;
use Crustum\Ignis\Install\GuidelineAssist;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Ignis\Install\Skill;
use Crustum\Ignis\Install\SkillWriter;
use Crustum\Ignis\Support\DirectoryLink;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Ignis\Test\Fixtures\FakeAgent;
use Crustum\Ignis\Test\Fixtures\StubGuidelineAssistSkillWriter;
use Crustum\Inspector\Enums\PackageSource;
use Crustum\Inspector\Ecosystems\Ecosystem;
use Crustum\Inspector\Ecosystems\JsEcosystem;
use Crustum\Inspector\Package;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;
use JMac\Testing\Double;
use Symfony\Component\Process\Process;

/**
 * Write the shipped infer-conventions skill using a fixed GuidelineAssist.
 *
 * @param string $relativeTarget Relative agent skills path
 * @param \Crustum\Ignis\Install\GuidelineAssist $assist Guideline assist for Twig rendering
 * @return int SkillWriter result code
 */
function writeInferConventions(string $relativeTarget, GuidelineAssist $assist): int
{
    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: 'infer-conventions',
        package: 'ignis',
        path: dirname(__DIR__, 3) . '/.ai/ignis/skill/infer-conventions',
        description: 'Infer and record application conventions',
    );

    return (new StubGuidelineAssistSkillWriter($agent, $assist))->write($skill);
}

/**
 * Build a ProjectManager mock with the given PHP packages for GuidelineAssist.
 *
 * @param list<\Crustum\Inspector\Package> $packages PHP packages
 * @return \Crustum\Inspector\ProjectManager
 */
function mockSkillWriterProject(array $packages): ProjectManager
{
    $project = Double::for(ProjectManager::class, override: true);
    $php = Double::for(Ecosystem::class);
    $js = Double::for(JsEcosystem::class);

    $project->allows('php')->returns($php);
    $project->allows('js')->returns($js);
    $php->allows('packages')->returns(new PackageCollection($packages));
    $js->allows('packages')->returns(new PackageCollection([]));
    $php->allows('uses')->resolves(
        fn(string $name, ?string $constraint = null): bool => array_any(
            $packages,
            fn(Package $package): bool => $package->name() === $name,
        ),
    );
    $js->allows('uses')->returns(false);

    return $project->instance();
}

it('installs the shipped infer-conventions skill with its references', function (): void {
    $sourceDir = dirname(__DIR__, 3) . '/.ai/ignis/skill/infer-conventions';
    $relativeTarget = '.ignis-test-skills-' . uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: 'infer-conventions',
        package: 'ignis',
        path: $sourceDir,
        description: 'Infer and record application conventions',
    );

    $result = (new SkillWriter($agent))->write($skill);

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and($absoluteTarget . '/infer-conventions/SKILL.md')->toBeFile()
        ->and($absoluteTarget . '/infer-conventions/references/checklist.md')->toBeFile();

    cleanupSkillDirectory($absoluteTarget);
});

it('renders the infer-conventions HTMX dimensions only when cake-htmx is installed', function (): void {
    $project = mockSkillWriterProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package('zunnu/cake-htmx', '1.0.0', PackageSource::Composer, direct: true),
    ]);
    $assist = new GuidelineAssist($project, new GuidelineConfig());

    $relativeTarget = '.ignis-test-skills-' . uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    writeInferConventions($relativeTarget, $assist);

    $skill = (string)file_get_contents($absoluteTarget . '/infer-conventions/SKILL.md');
    $checklist = (string)file_get_contents($absoluteTarget . '/infer-conventions/references/checklist.md');

    expect($skill)
        ->toContain('This app ships a frontend UI stack package')
        ->and($checklist)->toContain('HTMX: partial swaps');

    cleanupSkillDirectory($absoluteTarget);
});

it('drops the infer-conventions HTMX dimensions when cake-htmx is absent', function (): void {
    $project = mockSkillWriterProject([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
    ]);
    $assist = new GuidelineAssist($project, new GuidelineConfig());

    $relativeTarget = '.ignis-test-skills-' . uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    writeInferConventions($relativeTarget, $assist);

    $skill = (string)file_get_contents($absoluteTarget . '/infer-conventions/SKILL.md');
    $checklist = (string)file_get_contents($absoluteTarget . '/infer-conventions/references/checklist.md');

    expect($skill)
        ->not->toContain('| HTMX |')
        ->toContain('has no HTMX/Bootstrap-UI packages installed')
        ->and($checklist)
        ->not->toContain('HTMX: partial swaps')
        ->not->toContain('UI kit (Bootstrap UI)')
        ->and($skill)->not->toContain('{% if')
        ->and($checklist)->not->toContain('{% if');

    cleanupSkillDirectory($absoluteTarget);
});

it('writes skill to a target directory', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $canonicalSkillPath = skillRootPath('.ai/skills/markdown-skill');

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: 'markdown-skill',
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and($absoluteTarget.'/markdown-skill')->toBeDirectory()
        ->and($absoluteTarget.'/markdown-skill/SKILL.md')->toBeFile()
        ->and($absoluteTarget.'/markdown-skill/references/example.md')->toBeFile()
        ->and($canonicalSkillPath)->not->toBeDirectory();

    cleanupSkillDirectory($absoluteTarget);
});

it('updates existing canonical skills when installing non-custom skills', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillName = 'test-skill-'.uniqid();
    $canonicalSkillPath = skillRootPath('.ai/skills/'.$skillName);

    if (! is_dir($canonicalSkillPath)) {
        mkdir($canonicalSkillPath, 0755, true);
    }

    file_put_contents($canonicalSkillPath.'/SKILL.md', 'old content');

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    $content = file_get_contents($canonicalSkillPath.'/SKILL.md');

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and($content)->toContain('name: markdown-skill');

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($canonicalSkillPath);
});

it('symlinks skills to the canonical directory', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $canonicalBase = skillRootPath('.ai/skills');
    $skillName = 'test-skill-'.uniqid();
    $canonicalSkillPath = $canonicalBase.'/'.$skillName;

    if (! is_dir($canonicalSkillPath)) {
        mkdir($canonicalSkillPath, 0755, true);
    }

    copy(fixture('skills/markdown-skill/SKILL.md'), $canonicalSkillPath.'/SKILL.md');

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $canonicalSkillPath,
        description: 'Test skill',
        custom: true,
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    $linkedPath = $absoluteTarget.'/'.$skillName;

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and($canonicalSkillPath)->toBeDirectory()
        ->and(DirectoryLink::isLink($linkedPath))->toBeTrue()
        ->and(DirectoryLink::pointsTo($linkedPath, $canonicalSkillPath))->toBeTrue();

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($canonicalSkillPath);
});

it('does not delete canonical skills when removing symlink', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $canonicalBase = skillRootPath('.ai/skills');
    $skillName = 'test-skill-'.uniqid();
    $canonicalSkillPath = $canonicalBase.'/'.$skillName;

    if (! is_dir($canonicalSkillPath)) {
        mkdir($canonicalSkillPath, 0755, true);
    }

    copy(fixture('skills/markdown-skill/SKILL.md'), $canonicalSkillPath.'/SKILL.md');

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $canonicalSkillPath,
        description: 'Test skill',
        custom: true,
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    $linkedPath = $absoluteTarget.'/'.$skillName;

    $removed = $writer->remove($skillName);

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and($removed)->toBeTrue()
        ->and(DirectoryLink::isLink($linkedPath))->toBeFalse()
        ->and($linkedPath)->not->toBeDirectory()
        ->and($canonicalSkillPath)->toBeDirectory()
        ->and($canonicalSkillPath.'/SKILL.md')->toBeFile();

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($canonicalSkillPath);
});

it('returns UPDATED when skill directory already exists', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $targetSkill = $absoluteTarget.'/markdown-skill';
    mkdir($targetSkill, 0755, true);
    file_put_contents($targetSkill.'/SKILL.md', 'old content');

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: 'markdown-skill',
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::UPDATED);

    $content = file_get_contents($targetSkill.'/SKILL.md');
    expect($content)->toContain('name: markdown-skill');

    cleanupSkillDirectory($absoluteTarget);
});

it('returns FAILED when source directory does not exist', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: 'missing-skill',
        package: 'ignis',
        path: '/nonexistent/path/'.uniqid(),
        description: 'Missing skill',
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::FAILED);
});

it('writes all skills', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $agent = new FakeAgent($relativeTarget);

    $skills = new Collection([
        'skill-one' => new Skill('skill-one', 'ignis', $sourceDir, 'First skill'),
        'skill-two' => new Skill('skill-two', 'ignis', $sourceDir, 'Second skill'),
    ]);

    $writer = new SkillWriter($agent);
    $results = $writer->writeAll($skills);

    expect($results)->toHaveCount(2)
        ->and($results['skill-one'])->toBe(SkillWriter::SUCCESS)
        ->and($results['skill-two'])->toBe(SkillWriter::SUCCESS);

    cleanupSkillDirectory($absoluteTarget);
});

it('copies nested directory structure', function (): void {
    $sourceDir = fixture('skills/markdown-nested-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: 'markdown-nested-skill',
        package: 'ignis',
        path: $sourceDir,
        description: 'Nested skill',
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and($absoluteTarget.'/markdown-nested-skill/SKILL.md')->toBeFile()
        ->and($absoluteTarget.'/markdown-nested-skill/references/ref.md')->toBeFile()
        ->and($absoluteTarget.'/markdown-nested-skill/references/deep/nested/file.md')->toBeFile();

    cleanupSkillDirectory($absoluteTarget);
});

it('throws an exception for path traversal in skill name', function (string $maliciousName): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: $maliciousName,
        package: 'ignis',
        path: $sourceDir,
        description: 'Malicious skill',
    );

    $writer = new SkillWriter($agent);

    expect(fn (): int => $writer->write($skill))
        ->toThrow(RuntimeException::class, 'Invalid skill name');
})->with([
    '../../../etc/passwd',
    '../../.bashrc',
    'skill/with/slash',
    'skill\\with\\backslash',
    '../parent',
]);

it('renders twig templates to markdown', function (): void {
    $sourceDir = fixture('skills/twig-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: 'twig-skill',
        package: 'ignis',
        path: $sourceDir,
        description: 'Twig skill',
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and($absoluteTarget.'/twig-skill/SKILL.md')->toBeFile()
        ->and($absoluteTarget.'/twig-skill/references/ref.md')->toBeFile();

    $content = file_get_contents($absoluteTarget.'/twig-skill/SKILL.md');
    expect($content)->toContain('The answer is 2')
        ->not->toContain('{{ 1 + 1 }}');

    cleanupSkillDirectory($absoluteTarget);
});

it('writes skills that combine markdown and twig source files', function (): void {
    $sourceDir = fixture('skills/mixed-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: 'mixed-skill',
        package: 'ignis',
        path: $sourceDir,
        description: 'Mixed markdown and twig skill',
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and($absoluteTarget.'/mixed-skill/SKILL.md')->toBeFile()
        ->and($absoluteTarget.'/mixed-skill/references/ref.md')->toBeFile()
        ->and($absoluteTarget.'/mixed-skill/references/ref.twig')->not->toBeFile();

    $skillContent = file_get_contents($absoluteTarget.'/mixed-skill/SKILL.md');
    $refContent = file_get_contents($absoluteTarget.'/mixed-skill/references/ref.md');

    expect($skillContent)->toContain('name: mixed-skill')
        ->and($refContent)->toContain('Compiled value: 15')
        ->and($refContent)->not->toContain('{{ 10 + 5 }}');

    cleanupSkillDirectory($absoluteTarget);
});

it('preserves vue template syntax in verbatim blocks when rendering twig skills', function (): void {
    $sourceDir = fixture('skills/twig-verbatim-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: 'twig-verbatim-skill',
        package: 'ignis',
        path: $sourceDir,
        description: 'Vue syntax test skill',
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and($absoluteTarget.'/twig-verbatim-skill/SKILL.md')->toBeFile();

    $content = file_get_contents($absoluteTarget.'/twig-verbatim-skill/SKILL.md');

    expect($content)
        ->toContain('{{ user.name }}')
        ->toContain('{{ errors.email }}')
        ->not->toContain('@{{ user.name }}')
        ->not->toContain('@{{ errors.email }}');

    cleanupSkillDirectory($absoluteTarget);
});

it('removes a skill directory', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillDir = $absoluteTarget.'/markdown-skill';

    mkdir($skillDir, 0755, true);
    file_put_contents($skillDir.'/SKILL.md', 'test content');

    $agent = new FakeAgent($relativeTarget);

    $writer = new SkillWriter($agent);
    $result = $writer->remove('markdown-skill');

    expect($result)->toBeTrue()
        ->and($skillDir)->not->toBeDirectory();

    cleanupSkillDirectory($absoluteTarget);
});

it('returns true when removing a non-existent skill', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();

    $agent = new FakeAgent($relativeTarget);

    $writer = new SkillWriter($agent);
    $result = $writer->remove('nonexistent-skill');

    expect($result)->toBeTrue();
});

it('returns false when removing skill with invalid name', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();

    $agent = new FakeAgent($relativeTarget);

    $writer = new SkillWriter($agent);

    expect($writer->remove('../malicious'))->toBeFalse()
        ->and($writer->remove('skill/with/slash'))->toBeFalse()
        ->and($writer->remove('.'))->toBeFalse()
        ->and($writer->remove('. .'))->toBeFalse();
});

it('removes multiple stale skills', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $skillOneDir = $absoluteTarget.'/skill-one';
    $skillTwoDir = $absoluteTarget.'/skill-two';
    $skillThreeDir = $absoluteTarget.'/skill-three';

    mkdir($skillOneDir, 0755, true);
    mkdir($skillTwoDir, 0755, true);
    mkdir($skillThreeDir, 0755, true);

    file_put_contents($skillOneDir.'/SKILL.md', 'skill one');
    file_put_contents($skillTwoDir.'/SKILL.md', 'skill two');
    file_put_contents($skillThreeDir.'/SKILL.md', 'skill three');

    $agent = new FakeAgent($relativeTarget);

    $writer = new SkillWriter($agent);
    $results = $writer->removeStale(['skill-one', 'skill-two']);

    expect($results)->toHaveCount(2)
        ->and($results['skill-one'])->toBeTrue()
        ->and($results['skill-two'])->toBeTrue()
        ->and($skillOneDir)->not->toBeDirectory()
        ->and($skillTwoDir)->not->toBeDirectory()
        ->and($skillThreeDir)->toBeDirectory();

    cleanupSkillDirectory($absoluteTarget);
});

it('removes nested skill directory with deep structure', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillDir = $absoluteTarget.'/markdown-nested-skill';
    $deepDir = $skillDir.'/references/deep/nested';

    mkdir($deepDir, 0755, true);
    file_put_contents($skillDir.'/SKILL.md', 'test');
    file_put_contents($deepDir.'/file.md', 'nested content');

    $agent = new FakeAgent($relativeTarget);

    $writer = new SkillWriter($agent);
    $result = $writer->remove('markdown-nested-skill');

    expect($result)->toBeTrue()
        ->and($skillDir)->not->toBeDirectory();

    cleanupSkillDirectory($absoluteTarget);
});

it('syncs skills by writing new and removing stale', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $staleSkillDir = $absoluteTarget.'/stale-skill';
    mkdir($staleSkillDir, 0755, true);
    file_put_contents($staleSkillDir.'/SKILL.md', 'stale content');

    $agent = new FakeAgent($relativeTarget);

    $skills = new Collection([
        'new-skill' => new Skill('new-skill', 'ignis', $sourceDir, 'New skill'),
    ]);

    $writer = new SkillWriter($agent);
    $result = $writer->sync($skills, ['stale-skill']);

    expect($result)->toHaveCount(1)
        ->and($result['new-skill'])->toBe(SkillWriter::SUCCESS)
        ->and($absoluteTarget.'/new-skill')->toBeDirectory()
        ->and($staleSkillDir)->not->toBeDirectory();

    cleanupSkillDirectory($absoluteTarget);
});

it('sync preserves skills that exist in both source and target', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $existingSkillDir = $absoluteTarget.'/existing-skill';
    mkdir($existingSkillDir, 0755, true);
    file_put_contents($existingSkillDir.'/SKILL.md', 'old content');

    $agent = new FakeAgent($relativeTarget);

    $skills = new Collection([
        'existing-skill' => new Skill('existing-skill', 'ignis', $sourceDir, 'Existing skill'),
    ]);

    $writer = new SkillWriter($agent);
    $result = $writer->sync($skills);

    expect($result['existing-skill'])->toBe(SkillWriter::UPDATED)
        ->and($existingSkillDir)->toBeDirectory();

    $content = file_get_contents($existingSkillDir.'/SKILL.md');
    expect($content)->toContain('name: markdown-skill');

    cleanupSkillDirectory($absoluteTarget);
});

it('sync preserves user-created custom skills that were never tracked', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $trackedSkillDir = $absoluteTarget.'/tracked-skill';
    $customSkillDir = $absoluteTarget.'/my-custom-skill';
    mkdir($trackedSkillDir, 0755, true);
    mkdir($customSkillDir, 0755, true);
    file_put_contents($trackedSkillDir.'/SKILL.md', 'tracked content');
    file_put_contents($customSkillDir.'/SKILL.md', 'custom content');

    $agent = new FakeAgent($relativeTarget);

    $skills = new Collection([
        'new-skill' => new Skill('new-skill', 'ignis', $sourceDir, 'New skill'),
    ]);

    $writer = new SkillWriter($agent);
    $result = $writer->sync($skills, ['tracked-skill']);

    expect($result['new-skill'])->toBe(SkillWriter::SUCCESS)
        ->and($trackedSkillDir)->not->toBeDirectory()
        ->and($customSkillDir)->toBeDirectory();

    $customContent = file_get_contents($customSkillDir.'/SKILL.md');
    expect($customContent)->toBe('custom content');

    cleanupSkillDirectory($absoluteTarget);
});

it('sync only removes previously tracked skills', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $trackedOneDir = $absoluteTarget.'/tracked-one';
    $trackedTwoDir = $absoluteTarget.'/tracked-two';
    $untrackedDir = $absoluteTarget.'/untracked-skill';
    mkdir($trackedOneDir, 0755, true);
    mkdir($trackedTwoDir, 0755, true);
    mkdir($untrackedDir, 0755, true);
    file_put_contents($trackedOneDir.'/SKILL.md', 'tracked one');
    file_put_contents($trackedTwoDir.'/SKILL.md', 'tracked two');
    file_put_contents($untrackedDir.'/SKILL.md', 'untracked');

    $agent = new FakeAgent($relativeTarget);

    $skills = new Collection([
        'tracked-one' => new Skill('tracked-one', 'ignis', $sourceDir, 'Tracked one'),
    ]);

    $writer = new SkillWriter($agent);
    $result = $writer->sync($skills, ['tracked-one', 'tracked-two']);

    expect($result['tracked-one'])->toBe(SkillWriter::UPDATED)
        ->and($trackedOneDir)->toBeDirectory()
        ->and($trackedTwoDir)->not->toBeDirectory()
        ->and($untrackedDir)->toBeDirectory();

    cleanupSkillDirectory($absoluteTarget);
});

it('removes directory containing nested symlinks', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillDir = $absoluteTarget.'/symlink-skill';
    $nestedDir = $skillDir.'/references';
    $linkTargetDir = skillRootPath('.ignis-link-target-'.uniqid());

    mkdir($nestedDir, 0755, true);
    mkdir($linkTargetDir, 0755, true);
    file_put_contents($skillDir.'/SKILL.md', 'test');
    file_put_contents($linkTargetDir.'/target.md', 'link target content');

    $symlinkPath = $nestedDir.'/linked-dir';
    expect(DirectoryLink::create($linkTargetDir, $symlinkPath))->toBeTrue();

    $agent = new FakeAgent($relativeTarget);

    $writer = new SkillWriter($agent);
    $result = $writer->remove('symlink-skill');

    expect($result)->toBeTrue()
        ->and($skillDir)->not->toBeDirectory()
        ->and(DirectoryLink::isLink($symlinkPath))->toBeFalse()
        ->and($linkTargetDir)->toBeDirectory()
        ->and($linkTargetDir.'/target.md')->toBeFile();

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($linkTargetDir);
});

it('creates canonical directory and symlinks custom skill when canonical does not exist', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillName = 'test-skill-'.uniqid();
    $canonicalSkillPath = skillRootPath('.ai/skills/'.$skillName);

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
        custom: true,
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    $linkedPath = $absoluteTarget.'/'.$skillName;

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and($canonicalSkillPath)->toBeDirectory()
        ->and($canonicalSkillPath.'/SKILL.md')->toBeFile()
        ->and(DirectoryLink::isLink($linkedPath))->toBeTrue()
        ->and(DirectoryLink::pointsTo($linkedPath, $canonicalSkillPath))->toBeTrue();

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($canonicalSkillPath);
});

it('handles dangling symlink at target path', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillName = 'test-skill-'.uniqid();
    $canonicalSkillPath = skillRootPath('.ai/skills/'.$skillName);
    $linkedPath = $absoluteTarget.'/'.$skillName;
    $danglingTarget = skillRootPath('.ignis-dangling-'.uniqid());

    mkdir($danglingTarget, 0755, true);
    mkdir(dirname($linkedPath), 0755, true);

    expect(DirectoryLink::create($danglingTarget, $linkedPath))->toBeTrue();

    cleanupSkillDirectory($danglingTarget);

    expect(DirectoryLink::isLink($linkedPath))->toBeTrue();

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
        custom: true,
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::UPDATED)
        ->and($canonicalSkillPath)->toBeDirectory()
        ->and($canonicalSkillPath.'/SKILL.md')->toBeFile();

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($canonicalSkillPath);
});

it('transitions from non-custom directory to custom symlink', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillName = 'test-skill-'.uniqid();
    $canonicalSkillPath = skillRootPath('.ai/skills/'.$skillName);
    $targetPath = $absoluteTarget.'/'.$skillName;

    $agent = new FakeAgent($relativeTarget);

    $nonCustomSkill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
    );

    $writer = new SkillWriter($agent);
    $writer->write($nonCustomSkill);

    expect($targetPath)->toBeDirectory()
        ->and(DirectoryLink::isLink($targetPath))->toBeFalse();

    $customSkill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
        custom: true,
    );

    $result = $writer->write($customSkill);

    expect($result)->toBe(SkillWriter::UPDATED)
        ->and($canonicalSkillPath)->toBeDirectory()
        ->and($canonicalSkillPath.'/SKILL.md')->toBeFile()
        ->and(DirectoryLink::isLink($targetPath))->toBeTrue()
        ->and(DirectoryLink::pointsTo($targetPath, $canonicalSkillPath))->toBeTrue();

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($canonicalSkillPath);
});

it('transitions from custom symlink to non-custom directory', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillName = 'test-skill-'.uniqid();
    $canonicalSkillPath = skillRootPath('.ai/skills/'.$skillName);
    $targetPath = $absoluteTarget.'/'.$skillName;

    $agent = new FakeAgent($relativeTarget);

    $customSkill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
        custom: true,
    );

    $writer = new SkillWriter($agent);
    $writer->write($customSkill);

    expect(DirectoryLink::isLink($targetPath))->toBeTrue();

    $nonCustomSkill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
    );

    $result = $writer->write($nonCustomSkill);

    expect($result)->toBe(SkillWriter::UPDATED)
        ->and($targetPath)->toBeDirectory()
        ->and(DirectoryLink::isLink($targetPath))->toBeFalse()
        ->and($targetPath.'/SKILL.md')->toBeFile();

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($canonicalSkillPath);
});

it('preserves canonical directory when removing custom skill symlink via removeStale', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillName = 'test-skill-'.uniqid();
    $canonicalSkillPath = skillRootPath('.ai/skills/'.$skillName);

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
        custom: true,
    );

    $writer = new SkillWriter($agent);
    $writer->write($skill);

    $results = $writer->removeStale([$skillName]);

    $linkedPath = $absoluteTarget.'/'.$skillName;

    expect($results[$skillName])->toBeTrue()
        ->and(DirectoryLink::isLink($linkedPath))->toBeFalse()
        ->and($linkedPath)->not->toBeDirectory()
        ->and($canonicalSkillPath)->toBeDirectory()
        ->and($canonicalSkillPath.'/SKILL.md')->toBeFile();

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($canonicalSkillPath);
});

it('compiles twig to markdown instead of symlinking when custom skill source is canonical path', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillName = 'twig-skill-'.uniqid();
    $canonicalSkillPath = skillRootPath('.ai/skills/'.$skillName);

    mkdir($canonicalSkillPath.'/references', 0755, true);
    copy(fixture('skills/twig-skill/SKILL.twig'), $canonicalSkillPath.'/SKILL.twig');
    copy(fixture('skills/twig-skill/references/ref.twig'), $canonicalSkillPath.'/references/ref.twig');

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $canonicalSkillPath,
        description: 'Twig skill',
        custom: true,
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    $targetSkillPath = $absoluteTarget.'/'.$skillName;

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and($targetSkillPath)->toBeDirectory()
        ->and(DirectoryLink::isLink($targetSkillPath))->toBeFalse()
        ->and($targetSkillPath.'/SKILL.md')->toBeFile()
        ->and($targetSkillPath.'/references/ref.md')->toBeFile();

    expect(glob($targetSkillPath.'/*.twig') ?: [])->toBeEmpty();

    $content = file_get_contents($targetSkillPath.'/SKILL.md');
    expect($content)->toContain('The answer is 2')
        ->not->toContain('{{ 1 + 1 }}');

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($canonicalSkillPath);
});

it('replaces existing custom skill symlink when canonical skill contains root twig files', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillName = 'twig-skill-'.uniqid();
    $canonicalSkillPath = skillRootPath('.ai/skills/'.$skillName);
    $targetSkillPath = $absoluteTarget.'/'.$skillName;

    mkdir($canonicalSkillPath.'/references', 0755, true);
    copy(fixture('skills/twig-skill/SKILL.twig'), $canonicalSkillPath.'/SKILL.twig');
    copy(fixture('skills/twig-skill/references/ref.twig'), $canonicalSkillPath.'/references/ref.twig');
    mkdir(dirname($targetSkillPath), 0755, true);

    expect(DirectoryLink::create($canonicalSkillPath, $targetSkillPath))->toBeTrue();

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $canonicalSkillPath,
        description: 'Twig skill',
        custom: true,
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::UPDATED)
        ->and($targetSkillPath)->toBeDirectory()
        ->and(DirectoryLink::isLink($targetSkillPath))->toBeFalse()
        ->and($targetSkillPath.'/SKILL.md')->toBeFile()
        ->and($targetSkillPath.'/references/ref.md')->toBeFile()
        ->and($targetSkillPath.'/SKILL.twig')->not->toBeFile()
        ->and($targetSkillPath.'/references/ref.twig')->not->toBeFile();

    $content = file_get_contents($targetSkillPath.'/SKILL.md');
    expect($content)->toContain('The answer is 2')
        ->not->toContain('{{ 1 + 1 }}');

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($canonicalSkillPath);
});

it('replaces existing custom skill symlink when canonical skill only contains nested twig files', function (): void {
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $skillName = 'nested-twig-skill-'.uniqid();
    $canonicalSkillPath = skillRootPath('.ai/skills/'.$skillName);
    $targetSkillPath = $absoluteTarget.'/'.$skillName;

    mkdir($canonicalSkillPath.'/references', 0755, true);
    copy(fixture('skills/markdown-skill/SKILL.md'), $canonicalSkillPath.'/SKILL.md');
    file_put_contents($canonicalSkillPath.'/references/ref.twig', "# Nested reference\n\nThe answer is {{ 2 + 2 }}\n");
    mkdir(dirname($targetSkillPath), 0755, true);

    expect(DirectoryLink::create($canonicalSkillPath, $targetSkillPath))->toBeTrue();

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: $skillName,
        package: 'ignis',
        path: $canonicalSkillPath,
        description: 'Nested twig skill',
        custom: true,
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::UPDATED)
        ->and($canonicalSkillPath.'/references/ref.twig')->toBeFile()
        ->and($targetSkillPath)->toBeDirectory()
        ->and(DirectoryLink::isLink($targetSkillPath))->toBeFalse()
        ->and($targetSkillPath.'/SKILL.md')->toBeFile()
        ->and($targetSkillPath.'/references/ref.md')->toBeFile()
        ->and($targetSkillPath.'/references/ref.twig')->not->toBeFile();

    $content = file_get_contents($targetSkillPath.'/references/ref.md');
    expect($content)->toContain('The answer is 4')
        ->not->toContain('{{ 2 + 2 }}');

    cleanupSkillDirectory($absoluteTarget);
    cleanupSkillDirectory($canonicalSkillPath);
});

it('removes extra files when updating skill directory', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $targetSkill = $absoluteTarget.'/markdown-skill';

    mkdir($targetSkill.'/references/old', 0755, true);
    file_put_contents($targetSkill.'/SKILL.md', 'old content');
    file_put_contents($targetSkill.'/extra-file.md', 'should be removed');
    file_put_contents($targetSkill.'/references/old/nested.md', 'should also be removed');

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: 'markdown-skill',
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::UPDATED)
        ->and($targetSkill.'/SKILL.md')->toBeFile()
        ->and($targetSkill.'/references/example.md')->toBeFile()
        ->and($targetSkill.'/extra-file.md')->not->toBeFile()
        ->and($targetSkill.'/references/old')->not->toBeDirectory();

    cleanupSkillDirectory($absoluteTarget);
});

it('computes correct relative path when target is outside project directory', function (): void {
    $nestedProjectRoot = testAppTmpPath('nested-' . uniqid());
    $outsideDir = testAppTmpPath('target-' . uniqid());

    ensureDirectoryExists($nestedProjectRoot);
    ensureDirectoryExists($outsideDir);

    $agent = new FakeAgent();
    $writer = new SkillWriter($agent);

    $reflection = new ReflectionMethod($writer, 'relativePath');

    $result = $reflection->invoke($writer, $outsideDir, $nestedProjectRoot);

    expect($result)->not->toStartWith('/');

    $resolved = realpath($nestedProjectRoot . '/' . $result);
    expect($resolved)->toBe(realpath($outsideDir));

    cleanupSkillDirectory($outsideDir);
    cleanupSkillDirectory($nestedProjectRoot);
});

it('creates relative symlink when skills path is outside the project root', function (): void {
    $outsideDirName = 'outside-' . uniqid();
    $outsideDir = testAppTmpPath($outsideDirName);
    $nestedProjectRoot = testAppTmpPath('nested-' . uniqid());
    $relativeOutsidePath = '../' . $outsideDirName;
    $previousRoot = ProjectRoot::path();

    ensureDirectoryExists($outsideDir);
    ensureDirectoryExists($nestedProjectRoot);
    ProjectRoot::set($nestedProjectRoot);

    try {
        $skillName = 'test-skill-' . uniqid();
        $canonicalSkillPath = skillRootPath('.ai/skills/' . $skillName);

        mkdir($canonicalSkillPath, 0755, true);
        copy(fixture('skills/markdown-skill/SKILL.md'), $canonicalSkillPath . '/SKILL.md');

        $agent = new FakeAgent($relativeOutsidePath);

        $skill = new Skill(
            name: $skillName,
            package: 'ignis',
            path: $canonicalSkillPath,
            description: 'Test skill',
            custom: true,
        );

        $writer = new SkillWriter($agent);
        $result = $writer->write($skill);

        $linkedPath = $outsideDir . '/' . $skillName;

        expect($result)->toBe(SkillWriter::SUCCESS)
            ->and($canonicalSkillPath)->toBeDirectory()
            ->and(DirectoryLink::isLink($linkedPath))->toBeTrue()
            ->and(DirectoryLink::pointsTo($linkedPath, $canonicalSkillPath))->toBeTrue();

        if (is_link($linkedPath)) {
            $linkTarget = readlink($linkedPath);

            expect($linkTarget)->not->toStartWith('/');
        }

        cleanupSkillDirectory($canonicalSkillPath);
    } finally {
        ProjectRoot::set($previousRoot);
        cleanupSkillDirectory($outsideDir);
        cleanupSkillDirectory($nestedProjectRoot);
    }
});

it('writes skill files with a trailing newline', function (): void {
    $sourceDir = fixture('skills/markdown-skill');
    $relativeTarget = '.ignis-test-skills-'.uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    $agent = new FakeAgent($relativeTarget);

    $skill = new Skill(
        name: 'markdown-skill',
        package: 'ignis',
        path: $sourceDir,
        description: 'Test skill',
    );

    $writer = new SkillWriter($agent);
    $result = $writer->write($skill);

    expect($result)->toBe(SkillWriter::SUCCESS)
        ->and(file_get_contents($absoluteTarget.'/markdown-skill/SKILL.md'))->toEndWith("\n");

    cleanupSkillDirectory($absoluteTarget);
});

it('preserves unix absolute project roots when resolving skill paths', function (): void {
    $agent = new FakeAgent('.ignis-test-skills');

    $writer = new class ($agent) extends SkillWriter {
        /**
         * Expose path normalization for assertions.
         *
         * @param string $basePath Base path
         * @param string ...$segments Path segments
         * @return string
         */
        public function exposeNormalizeAbsolutePath(string $basePath, string ...$segments): string
        {
            return $this->normalizeAbsolutePath($basePath, ...$segments);
        }
    };

    $resolved = $writer->exposeNormalizeAbsolutePath(
        '/home/runner/work/ignis/ignis/tests/TestAppProject',
        '.ignis-test-skills',
        'markdown-skill',
    );

    $expected = '/home/runner/work/ignis/ignis/tests/TestAppProject/.ignis-test-skills/markdown-skill';

    expect($resolved)->toBe($expected)
        ->and($resolved)->toStartWith('/');
});

it('never deletes a skills directory when a skill is named .', function (): void {
    $relativeTarget = '.ignis-test-skills-' . uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    $canonicalTarget = skillRootPath('.ai/skills');

    mkdir($absoluteTarget . '/keep-me', 0755, true);
    file_put_contents($absoluteTarget . '/keep-me/SKILL.md', 'keep me');
    mkdir($canonicalTarget . '/keep-me-too', 0755, true);
    file_put_contents($canonicalTarget . '/keep-me-too/SKILL.md', 'keep me too');

    $agent = new FakeAgent($relativeTarget);

    $writer = new SkillWriter($agent);
    $skill = new Skill(
        name: '.',
        package: 'ignis',
        path: fixture('skills/twig-skill'),
        description: 'Malicious skill',
    );

    try {
        expect(fn (): int => $writer->write($skill))->toThrow(RuntimeException::class, 'Invalid skill name')
            ->and(file_get_contents($absoluteTarget . '/keep-me/SKILL.md'))->toBe('keep me')
            ->and(file_get_contents($canonicalTarget . '/keep-me-too/SKILL.md'))->toBe('keep me too')
            ->and($writer->removeStale(['.']))->toBe(['.' => false]);
    } finally {
        cleanupSkillDirectory($absoluteTarget);
        cleanupSkillDirectory($canonicalTarget . '/keep-me-too');
    }
});

it('still syncs the valid skills when one skill name is invalid', function (): void {
    $relativeTarget = '.ignis-test-skills-' . uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);

    mkdir($absoluteTarget . '/stale-skill', 0755, true);
    file_put_contents($absoluteTarget . '/stale-skill/SKILL.md', 'stale');

    $agent = new FakeAgent($relativeTarget);

    $skills = new Collection([
        '.' => new Skill(name: '.', package: 'ignis', path: fixture('skills/twig-skill'), description: 'Malicious skill'),
        'test-skill' => new Skill(name: 'test-skill', package: 'ignis', path: fixture('skills/twig-skill'), description: 'Test skill'),
    ]);

    try {
        expect(fn (): array => (new SkillWriter($agent))->sync($skills, ['stale-skill']))
            ->toThrow(RuntimeException::class, 'Invalid skill name: .')
            ->and($absoluteTarget . '/test-skill/SKILL.md')->toBeFile()
            ->and($absoluteTarget . '/stale-skill')->not->toBeDirectory();
    } finally {
        cleanupSkillDirectory($absoluteTarget);
    }
});

it('preserves executable scripts without making other skill files executable', function (int $mask, int $scriptMode, int $dataMode): void {
    $source = testAppTmpPath('ignis-executable-skill-' . uniqid());
    $relativeTarget = 'tmp/ignis-test-skills-' . uniqid();
    $absoluteTarget = skillRootPath($relativeTarget);
    mkdir($source . '/scripts', 0777, true);
    file_put_contents($source . '/SKILL.md', "---\nname: executable-skill\ndescription: Run a bundled script.\n---\n\nRun scripts/check.sh.\n");
    file_put_contents($source . '/scripts/check.sh', "#!/bin/sh\nprintf 'skill-script-ok\\n'\n");
    file_put_contents($source . '/scripts/data.json', '{}');
    chmod($source . '/scripts/check.sh', 0755);
    chmod($source . '/scripts/data.json', 0644);

    $agent = new FakeAgent($relativeTarget);
    $skill = new Skill(name: 'executable-skill', package: 'example/package', path: $source, description: 'Run a bundled script.');
    $writer = new SkillWriter($agent);
    $previousUmask = umask($mask);

    try {
        if (!posixPermissionsEffective($source)) {
            $this->markTestSkipped('Skipped: POSIX file permissions are not effective on this filesystem.');
        }

        expect($writer->write($skill))->toBe(SkillWriter::SUCCESS)
            ->and(is_executable($absoluteTarget . '/executable-skill/scripts/check.sh'))->toBeTrue()
            ->and(is_executable($absoluteTarget . '/executable-skill/scripts/data.json'))->toBeFalse()
            ->and(is_executable($absoluteTarget . '/executable-skill/SKILL.md'))->toBeFalse()
            ->and(fileperms($absoluteTarget . '/executable-skill/scripts/check.sh') & 0777)->toBe($scriptMode)
            ->and(fileperms($absoluteTarget . '/executable-skill/scripts/data.json') & 0777)->toBe($dataMode);

        $process = new Process([$absoluteTarget . '/executable-skill/scripts/check.sh']);
        $process->mustRun();
        expect($process->getOutput())->toBe("skill-script-ok\n");

        chmod($source . '/scripts/check.sh', 0644);
        expect($writer->write($skill))->toBe(SkillWriter::UPDATED);
        clearstatcache(true, $absoluteTarget . '/executable-skill/scripts/check.sh');
        expect(is_executable($absoluteTarget . '/executable-skill/scripts/check.sh'))->toBeFalse();
    } finally {
        umask($previousUmask);
        cleanupSkillDirectory($source);
        cleanupSkillDirectory($absoluteTarget);
    }
})->with([
    '0022 umask' => [0022, 0755, 0644],
    '0077 umask' => [0077, 0700, 0600],
])->skipOnWindows();
