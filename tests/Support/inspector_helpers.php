<?php
declare(strict_types=1);

use Crustum\Inspector\Enums\JsPackageManager;
use Crustum\Inspector\Enums\PackageSource;
use Crustum\Inspector\Ecosystems\Ecosystem;
use Crustum\Inspector\Ecosystems\JsEcosystem;
use Crustum\Inspector\Package;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;

/**
 * Build a mocked ProjectManager for ApplicationInfo tests.
 *
 * @param list<\Crustum\Inspector\Package> $phpPackages PHP packages
 * @param list<\Crustum\Inspector\Package> $jsPackages JS packages
 * @return \Crustum\Inspector\ProjectManager
 */
function mockApplicationInfoProject(array $phpPackages = [], array $jsPackages = []): ProjectManager
{
    $project = Mockery::mock(ProjectManager::class);
    $php = Mockery::mock(Ecosystem::class);
    $js = Mockery::mock(JsEcosystem::class);

    $phpCollection = new PackageCollection($phpPackages);
    $jsCollection = new PackageCollection($jsPackages);

    $project->shouldReceive('php')->andReturn($php);
    $project->shouldReceive('js')->andReturn($js);
    $php->shouldReceive('packages')->andReturn($phpCollection);
    $js->shouldReceive('packages')->andReturn($jsCollection);
    $php->shouldReceive('package')->andReturnUsing(
        function (string $name) use ($phpPackages): ?Package {
            foreach ($phpPackages as $package) {
                if ($package->name() === $name) {
                    return $package;
                }
            }

            return null;
        },
    );

    return $project;
}

/**
 * Build a inspector Package with an optional setDirect() helper for tests.
 *
 * @param string $name Composer or npm package name
 * @param string $version Package version
 * @param bool $dev Whether the package is a dev dependency
 * @param string|null $path Optional package path
 * @return \Crustum\Inspector\Package
 */
function inspectorPackage(string $name, string $version, bool $dev = false, ?string $path = null): Package
{
    $source = str_starts_with($name, '@') || !str_contains($name, '/')
        ? PackageSource::Npm
        : PackageSource::Composer;

    return new class ($name, $version, $source, $dev, false, '', $path) extends Package {
        /**
         * Mark the package as a direct dependency for tests.
         *
         * @param bool $direct Whether the package is direct
         * @return self
         */
        public function setDirect(bool $direct = true): self
        {
            $this->direct = $direct;

            return $this;
        }
    };
}

/**
 * Wire ProjectManager php/js ecosystems from a flat package collection.
 *
 * @param \Crustum\Inspector\ProjectManager $project Mocked project manager
 * @param \Crustum\Inspector\PackageCollection $packages Packages to expose
 * @param \Crustum\Inspector\Enums\JsPackageManager|null $packageManager Optional JS package manager
 * @return void
 */
function mockProjectPackages(
    ProjectManager $project,
    PackageCollection $packages,
    ?JsPackageManager $packageManager = null,
): void {
    $php = [];
    $js = [];

    foreach ($packages->all() as $package) {
        if ($package->source() === PackageSource::Npm) {
            $js[] = $package;
        } else {
            $php[] = $package;
        }
    }

    $project->shouldReceive('php')->andReturn(new Ecosystem(new PackageCollection($php)));
    $project->shouldReceive('js')->andReturn(new JsEcosystem(new PackageCollection($js), $packageManager));
}
