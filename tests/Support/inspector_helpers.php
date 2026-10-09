<?php
declare(strict_types=1);

use Crustum\Inspector\Enums\JsPackageManager;
use Crustum\Inspector\Enums\PackageSource;
use Crustum\Inspector\Ecosystems\Ecosystem;
use Crustum\Inspector\Ecosystems\JsEcosystem;
use Crustum\Inspector\Package;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;
use JMac\Testing\Double;
use JMac\Testing\OverriddenDouble;

/**
 * Build a mocked ProjectManager for ApplicationInfo tests.
 *
 * @param list<\Crustum\Inspector\Package> $phpPackages PHP packages
 * @param list<\Crustum\Inspector\Package> $jsPackages JS packages
 * @return \Crustum\Inspector\ProjectManager
 */
function mockApplicationInfoProject(array $phpPackages = [], array $jsPackages = []): ProjectManager
{
    $project = Double::for(ProjectManager::class, override: true);
    $php = Double::for(Ecosystem::class);
    $js = Double::for(JsEcosystem::class);

    $phpCollection = new PackageCollection($phpPackages);
    $jsCollection = new PackageCollection($jsPackages);

    $project->allows('php')->returns($php);
    $project->allows('js')->returns($js);
    $php->allows('packages')->returns($phpCollection);
    $js->allows('packages')->returns($jsCollection);
    $php->allows('package')->resolves(
        function (string $name) use ($phpPackages): ?Package {
            foreach ($phpPackages as $package) {
                if ($package->name() === $name) {
                    return $package;
                }
            }

            return null;
        },
    );

    return $project->instance();
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
 * Stage a inspector package directory with Ignis asset subpaths for tests.
 *
 * Discovery reads the package path directly, so staged packages live outside
 * vendor/ and node_modules/.
 *
 * @param string $name Composer or npm package name
 * @param string ...$ignisSubpaths Ignis resource subpaths (e.g. guidelines, skills)
 * @return string Staged package path
 */
function stageInspectorPackage(string $name, string ...$ignisSubpaths): string
{
    $path = testAppTmpPath('inspector-packages' . DIRECTORY_SEPARATOR . str_replace(['@', '/'], ['', '-'], $name));

    ensureDirectoryExists($path);

    foreach ($ignisSubpaths as $subpath) {
        ensureDirectoryExists(
            $path . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'ignis' . DIRECTORY_SEPARATOR . $subpath,
        );
    }

    return $path;
}

/**
 * Remove all staged inspector packages.
 *
 * @return void
 */
function clearInspectorPackages(): void
{
    deleteDirectory(testAppTmpPath('inspector-packages'));
}

/**
 * Wire ProjectManager php/js ecosystems from a flat package collection.
 *
 * @param \JMac\Testing\OverriddenDouble $project Project double to wire
 * @param \Crustum\Inspector\PackageCollection $packages Packages to expose
 * @param \Crustum\Inspector\Enums\JsPackageManager|null $packageManager Optional JS package manager
 * @return void
 */
function mockProjectPackages(
    OverriddenDouble $project,
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

    $project->allows('php')->returns(new Ecosystem(new PackageCollection($php)));
    $project->allows('js')->returns(new JsEcosystem(new PackageCollection($js), $packageManager));
}
