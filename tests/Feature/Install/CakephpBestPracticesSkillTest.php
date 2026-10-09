<?php

declare(strict_types=1);

use Crustum\Ignis\Install\SkillComposer;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\Enums\PackageSource;
use Crustum\Inspector\Ecosystems\Ecosystem;
use Crustum\Inspector\Ecosystems\JsEcosystem;
use Crustum\Inspector\Package;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;
use JMac\Testing\Double;

test('discovers cakephp-best-practices skill for cakephp package', function (): void {
    $project = Double::for(ProjectManager::class, override: true);
    $php = Double::for(Ecosystem::class);
    $js = Double::for(JsEcosystem::class);
    $packages = [new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer)];

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

    $skills = (new SkillComposer($project->instance()))->skills()->toArray();

    expect($skills)->toHaveKey('cakephp-best-practices')
        ->and($skills['cakephp-best-practices']->name)->toBe('cakephp-best-practices')
        ->and(is_dir($skills['cakephp-best-practices']->path . DIRECTORY_SEPARATOR . 'rules'))->toBeTrue();
});

test('points testing guidance at the testing-best-practices skill instead of a local rule', function (): void {
    $skillDir = dirname(__DIR__, 3) . '/.ai/cakephp/skill/cakephp-best-practices';

    expect($skillDir . '/rules/testing.md')->not->toBeFile()
        ->and(file_get_contents($skillDir . '/SKILL.md'))->toContain('testing-best-practices');
});
