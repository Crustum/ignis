<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Trait;

use Cake\Collection\Collection;
use Cake\Collection\CollectionInterface;
use Crustum\Ignis\Support\Composer;
use Crustum\Ignis\Support\Npm;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\Package;
use Crustum\Inspector\ProjectManager;

/**
 * Discovers Ignis guideline and skill paths from inspector packages.
 */
trait DiscoverPackagePathsTrait
{
    /**
     * Only include guidelines for these package names if they're a direct requirement.
     *
     * @var array<int, string>
     */
    protected array $mustBeDirect = [
        PackageRegistry::MCP,
    ];

    /**
     * Packages excluded from Inspector-based guideline discovery.
     *
     * @var array<int, string>
     */
    protected array $excludedPackages = [
        PackageRegistry::IGNIS,
    ];

    /**
     * Return the project manager used for package discovery.
     *
     * @return \Crustum\Inspector\ProjectManager
     */
    abstract protected function getProject(): ProjectManager;

    /**
     * Package priority system to handle conflicts between packages.
     *
     * @return array<string, array<int, string>>
     */
    protected function getPackagePriorities(): array
    {
        return [
            PackageRegistry::PEST => [PackageRegistry::PHPUNIT],
        ];
    }

    /**
     * Whether a inspector package should be excluded from guideline discovery.
     *
     * @param \Crustum\Inspector\Package $package Inspector package
     * @return bool
     */
    protected function shouldExcludePackage(Package $package): bool
    {
        if (in_array($package->name(), $this->excludedPackages, true)) {
            return true;
        }

        foreach ($this->getPackagePriorities() as $priorityPackage => $excludedPackages) {
            if (
                in_array($package->name(), $excludedPackages, true)
                && $this->usesPackage($priorityPackage)
            ) {
                return true;
            }
        }

        return !$package->isDirect() && in_array($package->name(), $this->mustBeDirect, true);
    }

    /**
     * Discover on-disk package paths for inspector packages with Ignis assets.
     *
     * @param string $basePath Project root path
     * @return \Cake\Collection\CollectionInterface<int, array{path: string, name: string, version: string}>
     */
    protected function discoverPackagePaths(string $basePath): CollectionInterface
    {
        /** @var \Cake\Collection\CollectionInterface<int, array{path: string, name: string, version: string}> $paths */
        $paths = $this->packages()
            ->filter(fn(Package $package): bool => !$this->shouldExcludePackage($package))
            ->map(function (Package $package) use ($basePath): array {
                $name = $this->normalizePackageName($package->name());

                return [
                    'path' => $basePath . DIRECTORY_SEPARATOR . $name,
                    'name' => $name,
                    'version' => (string)$package->major(),
                ];
            })
            ->filter(fn(array $package): bool => is_dir($package['path']));

        return $paths;
    }

    /**
     * Normalize a package name for filesystem lookup.
     *
     * @param string $name Raw package name
     * @return string
     */
    protected function normalizePackageName(string $name): string
    {
        return PackageRegistry::guidelineName($name);
    }

    /**
     * Return merged PHP and JS packages from the project scan.
     *
     * @return \Cake\Collection\CollectionInterface<int, \Crustum\Inspector\Package>
     */
    protected function packages(): CollectionInterface
    {
        /** @var \Cake\Collection\CollectionInterface<int, \Crustum\Inspector\Package> $packages */
        $packages = new Collection([
            ...$this->getProject()->php()->packages()->all(),
            ...$this->getProject()->js()->packages()->all(),
        ]);

        return $packages;
    }

    /**
     * Whether the project uses a package in PHP or JS ecosystems.
     *
     * @param string $package Package name
     * @param string|null $constraint Optional semver constraint
     * @return bool
     */
    protected function usesPackage(string $package, ?string $constraint = null): bool
    {
        if ($this->getProject()->php()->uses($package, $constraint)) {
            return true;
        }

        return $this->getProject()->js()->uses($package, $constraint);
    }

    /**
     * Return the bundled Ignis `.ai` directory path.
     *
     * @return string
     */
    protected function getIgnisAiPath(): string
    {
        return __DIR__ . '/../../../.ai';
    }

    /**
     * Resolve a first-party package Ignis resource path when present.
     *
     * @param \Crustum\Inspector\Package $package Inspector package
     * @param string $subpath Ignis resources subpath
     * @return string|null
     */
    protected function resolveFirstPartyIgnisPath(Package $package, string $subpath): ?string
    {
        if (!Composer::isFirstPartyPackage($package->name()) && !Npm::isFirstPartyPackage($package->name())) {
            return null;
        }

        $packagePath = $package->path();

        if ($packagePath === null) {
            return null;
        }

        $path = implode(DIRECTORY_SEPARATOR, [$packagePath, 'resources', 'ignis', $subpath]);

        return is_dir($path) ? $path : null;
    }
}
