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

test('discovers cakephp-best-practices skill for cakephp package', function (): void {
    $project = Mockery::mock(ProjectManager::class);
    $php = Mockery::mock(Ecosystem::class);
    $js = Mockery::mock(JsEcosystem::class);
    $packages = [new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer)];

    $project->shouldReceive('php')->andReturn($php);
    $project->shouldReceive('js')->andReturn($js);
    $php->shouldReceive('packages')->andReturn(new PackageCollection($packages));
    $js->shouldReceive('packages')->andReturn(new PackageCollection([]));
    $php->shouldReceive('uses')->andReturnUsing(
        fn(string $name, ?string $constraint = null): bool => array_any(
            $packages,
            fn(Package $package): bool => $package->name() === $name,
        ),
    );
    $js->shouldReceive('uses')->andReturn(false);

    $skills = (new SkillComposer($project))->skills()->toArray();

    expect($skills)->toHaveKey('cakephp-best-practices')
        ->and($skills['cakephp-best-practices']->name)->toBe('cakephp-best-practices')
        ->and(is_dir($skills['cakephp-best-practices']->path . DIRECTORY_SEPARATOR . 'rules'))->toBeTrue();
});
