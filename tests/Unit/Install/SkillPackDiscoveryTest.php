<?php

declare(strict_types=1);

use Crustum\Ignis\Install\SkillPackDiscovery;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\Enums\PackageSource;
use Crustum\Inspector\Ecosystems\Ecosystem;
use Crustum\Inspector\Ecosystems\JsEcosystem;
use Crustum\Inspector\Package;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;

/**
 * @param list<\Crustum\Inspector\Package> $packages PHP packages
 * @return \Crustum\Inspector\ProjectManager
 */
function mockSkillPackProject(array $packages): ProjectManager
{
    $project = Mockery::mock(ProjectManager::class);
    $php = Mockery::mock(Ecosystem::class);
    $js = Mockery::mock(JsEcosystem::class);

    $project->shouldReceive('php')->andReturn($php);
    $project->shouldReceive('js')->andReturn($js);
    $php->shouldReceive('packages')->andReturn(new PackageCollection($packages));
    $js->shouldReceive('packages')->andReturn(new PackageCollection([]));
    $php->shouldReceive('uses')->andReturnUsing(
        fn(string $name): bool => array_any($packages, fn(Package $package): bool => $package->name() === $name),
    );
    $js->shouldReceive('uses')->andReturn(false);

    return $project;
}

test('composerNameFromSkillPackFolder maps aliases and nested paths', function (): void {
    expect(PackageRegistry::composerNameFromSkillPackFolder('cakephp/queue'))->toBe('cakephp/queue')
        ->and(PackageRegistry::composerNameFromSkillPackFolder('cakephp'))->toBe('cakephp/cakephp')
        ->and(PackageRegistry::composerNameFromSkillPackFolder('queue', ['queue' => 'cakephp/queue']))->toBe('cakephp/queue')
        ->and(PackageRegistry::skillPackFolderName('cakephp/queue'))->toBe('cakephp/queue')
        ->and(PackageRegistry::skillPackFolderName('cakephp/cakephp'))->toBe('cakephp')
        ->and(PackageRegistry::composerNameFromSkillPackFolder('cakephp-queue'))->toBeNull()
        ->and(PackageRegistry::composerNameFromSkillPackFolder('unknown'))->toBeNull();
});

test('pack discovery returns skills for installed targets', function (): void {
    $fixture = realpath(testDirectory('Fixtures/pack'));
    expect($fixture)->not->toBeFalse();

    $withQueue = mockSkillPackProject([
        new Package('cakephp/queue', '2.0.0', PackageSource::Composer, direct: true),
    ]);

    $discovery = new SkillPackDiscovery($withQueue, [
        'crustum/fixture-cakephp-skills' => $fixture,
    ]);

    $entries = $discovery->discover()->toList();
    $paths = array_column($entries, 'skillPath');

    expect($entries)->toHaveCount(2)
        ->and(array_column($entries, 'target'))->each->toBe('cakephp/queue')
        ->and(array_column($entries, 'major'))->toBe([null, 2])
        ->and($paths[0])->toContain(implode(DIRECTORY_SEPARATOR, ['cakephp', 'queue', 'skills']))
        ->and($paths[1])->toContain(implode(DIRECTORY_SEPARATOR, ['cakephp', 'queue', '2', 'skills']));
});

test('pack discovery returns guidelines for installed targets', function (): void {
    $fixture = realpath(testDirectory('Fixtures/pack'));
    expect($fixture)->not->toBeFalse();

    $discovery = new SkillPackDiscovery(mockSkillPackProject([
        new Package('cakephp/queue', '2.0.0', PackageSource::Composer, direct: true),
    ]), [
        'crustum/fixture-cakephp-skills' => $fixture,
    ]);

    $entries = $discovery->discoverGuidelines()->toList();

    expect($entries)->toHaveCount(2)
        ->and(array_column($entries, 'major'))->toBe([null, 2])
        ->and($entries[0]['guidelinesPath'])->toContain(implode(DIRECTORY_SEPARATOR, ['cakephp', 'queue', 'guidelines']))
        ->and($entries[1]['guidelinesPath'])->toContain(implode(DIRECTORY_SEPARATOR, ['cakephp', 'queue', '2', 'guidelines']));
});

test('pack discovery skips targets that are not installed', function (): void {
    $fixture = realpath(testDirectory('Fixtures/pack'));
    expect($fixture)->not->toBeFalse();

    $discovery = new SkillPackDiscovery(mockSkillPackProject([]), [
        'crustum/fixture-cakephp-skills' => $fixture,
    ]);

    expect($discovery->discover()->toList())->toBe([])
        ->and($discovery->discoverGuidelines()->toList())->toBe([]);
});

test('pack discovery includes migrations when installed', function (): void {
    $fixture = realpath(testDirectory('Fixtures/pack'));
    expect($fixture)->not->toBeFalse();

    $discovery = new SkillPackDiscovery(mockSkillPackProject([
        new Package('cakephp/queue', '2.0.0', PackageSource::Composer, direct: true),
        new Package('cakephp/migrations', '4.0.0', PackageSource::Composer, direct: true),
    ]), [
        'crustum/fixture-cakephp-skills' => $fixture,
    ]);

    $targets = array_values(array_unique(array_column($discovery->discover()->toList(), 'target')));
    sort($targets);

    expect($targets)->toBe(['cakephp/migrations', 'cakephp/queue']);
});

test('pack discovery finds transitive packs via installed.json', function (): void {
    $fixture = realpath(testDirectory('Fixtures/pack'));
    expect($fixture)->not->toBeFalse();

    $queueSkill = implode(DS, [
        'vendor', 'crustum', 'cakephp-skills', 'resources', 'ignis', 'pack',
        'cakephp', 'queue', 'skills', 'queue-development',
    ]);
    ensureDirectoryExists(testAppPath($queueSkill));
    copy(
        $fixture . DS . 'resources' . DS . 'ignis' . DS . 'pack' . DS . 'cakephp' . DS . 'queue' . DS . 'skills' . DS . 'queue-development' . DS . 'SKILL.md',
        testAppPath($queueSkill . DS . 'SKILL.md'),
    );
    file_put_contents(testAppPath('vendor' . DS . 'crustum' . DS . 'cakephp-skills' . DS . 'composer.json'), json_encode([
        'name' => 'crustum/cakephp-skills',
        'extra' => ['ignis' => ['pack' => true]],
    ]));

    file_put_contents(testAppPath('composer.json'), json_encode([
        'require' => ['crustum/ignis' => '@dev'],
    ]));

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

    $discovery = new SkillPackDiscovery(mockSkillPackProject([
        new Package('cakephp/queue', '2.0.0', PackageSource::Composer, direct: true),
    ]));

    $entries = $discovery->discover()->toList();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['pack'])->toBe('crustum/cakephp-skills')
        ->and($entries[0]['target'])->toBe('cakephp/queue');
});

test('pack discovery loads only matching major version tree', function (): void {
    $fixture = realpath(testDirectory('Fixtures/pack'));
    expect($fixture)->not->toBeFalse();

    $discovery = new SkillPackDiscovery(mockSkillPackProject([
        new Package('cakephp/queue', '3.0.0', PackageSource::Composer, direct: true),
    ]), [
        'crustum/fixture-cakephp-skills' => $fixture,
    ]);

    $skillMajors = array_column($discovery->discover()->toList(), 'major');
    $guidelineMajors = array_column($discovery->discoverGuidelines()->toList(), 'major');

    expect($skillMajors)->toBe([null])
        ->and($guidelineMajors)->toBe([null]);
});

test('pack discovery does not treat major folders as separate composer targets', function (): void {
    $fixture = realpath(testDirectory('Fixtures/pack'));
    expect($fixture)->not->toBeFalse();

    $discovery = new SkillPackDiscovery(mockSkillPackProject([
        new Package('cakephp/queue', '2.0.0', PackageSource::Composer, direct: true),
    ]), [
        'crustum/fixture-cakephp-skills' => $fixture,
    ]);

    $targets = array_unique(array_column($discovery->discover()->toList(), 'target'));

    expect($targets)->toBe(['cakephp/queue']);
});
