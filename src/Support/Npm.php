<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

/**
 * NPM package discovery helpers for Ignis assets.
 */
class Npm
{
    /**
     * First-party npm scopes for Ignis discovery.
     *
     * @var array<int, string>
     */
    public const FIRST_PARTY_SCOPES = [
        '@crustum',
    ];

    /**
     * First-party npm package names for Ignis discovery.
     *
     * @var array<int, string>
     */
    public const FIRST_PARTY_PACKAGES = [
        'tailwindcss',
        'alpinejs',
        'vite',
    ];

    /**
     * Whether an npm package is first-party for Ignis.
     *
     * @param string $npmName Package name
     * @return bool
     */
    public static function isFirstPartyPackage(string $npmName): bool
    {
        foreach (self::FIRST_PARTY_SCOPES as $scope) {
            if (str_starts_with($npmName, $scope . '/')) {
                return true;
            }
        }

        return in_array($npmName, self::FIRST_PARTY_PACKAGES, true);
    }

    /**
     * Return installed npm package directories keyed by package name.
     *
     * @return array<string, string>
     */
    public static function packagesDirectories(): array
    {
        $directories = [];

        foreach (array_keys(self::packages()) as $package) {
            $path = ProjectRoot::path() . DS . 'node_modules' . DS . str_replace('/', DS, $package);

            if (is_dir($path)) {
                $directories[$package] = $path;
            }
        }

        return $directories;
    }

    /**
     * Return npm dependencies and devDependencies.
     *
     * @return array<string, string>
     */
    public static function packages(): array
    {
        $packageJsonPath = ProjectRoot::path() . DS . 'package.json';

        if (!is_file($packageJsonPath)) {
            return [];
        }

        $contents = file_get_contents($packageJsonPath);

        if ($contents === false) {
            return [];
        }

        $packageData = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($packageData)) {
            return [];
        }

        $packages = array_merge(
            $packageData['dependencies'] ?? [],
            $packageData['devDependencies'] ?? [],
        );

        $resolved = [];

        foreach ($packages as $package => $version) {
            if (is_string($package) && is_string($version)) {
                $resolved[$package] = $version;
            }
        }

        return $resolved;
    }

    /**
     * Return npm package directories containing Ignis guidelines.
     *
     * @return array<string, string>
     */
    public static function packagesDirectoriesWithIgnisGuidelines(): array
    {
        return self::packagesDirectoriesWithIgnisSubpath('guidelines');
    }

    /**
     * Return npm package directories containing Ignis skills.
     *
     * @return array<string, string>
     */
    public static function packagesDirectoriesWithIgnisSkills(): array
    {
        return self::packagesDirectoriesWithIgnisSubpath('skills');
    }

    /**
     * Return npm package directories containing a Ignis subpath.
     *
     * @param string $subpath Ignis resources subpath
     * @return array<string, string>
     */
    private static function packagesDirectoriesWithIgnisSubpath(string $subpath): array
    {
        $directories = [];

        foreach (self::packagesDirectories() as $package => $path) {
            $ignisPath = $path . DS . 'resources' . DS . 'ignis' . DS . $subpath;

            if (is_dir($ignisPath)) {
                $directories[$package] = $ignisPath;
            }
        }

        return $directories;
    }
}
