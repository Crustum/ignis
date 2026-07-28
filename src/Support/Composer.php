<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

/**
 * Composer package discovery helpers for Ignis assets.
 */
class Composer
{
    /**
     * First-party vendor scopes for Ignis discovery.
     *
     * @var array<int, string>
     */
    public const FIRST_PARTY_SCOPES = [
        'cakephp',
        'crustum',
    ];

    /**
     * First-party package names for Ignis discovery.
     *
     * @var array<int, string>
     */
    public const FIRST_PARTY_PACKAGES = [
        'cakephp/cakephp',
        'cakephp/bake',
        'cakephp/debug_kit',
        'cakephp/authentication',
        'cakephp/authorization',
        'cakephp/queue',
        'cakephp/migrations',
        'crustum/mcp',
        'crustum/inspector',
        'crustum/ignis',
        'pestphp/pest',
        'phpunit/phpunit',
    ];

    /**
     * Whether a composer package is first-party for Ignis.
     *
     * @param string $composerName Package name
     * @return bool
     */
    public static function isFirstPartyPackage(string $composerName): bool
    {
        foreach (self::FIRST_PARTY_SCOPES as $scope) {
            if (str_starts_with($composerName, $scope . '/')) {
                return true;
            }
        }

        return in_array($composerName, self::FIRST_PARTY_PACKAGES, true);
    }

    /**
     * Return package directories for root composer.json require / require-dev.
     *
     * @return array<string, string>
     */
    public static function packagesDirectories(): array
    {
        $directories = [];

        foreach (array_keys(self::packages()) as $package) {
            $path = implode(DS, [
                ProjectRoot::path(),
                'vendor',
                ...explode('/', $package),
            ]);

            if (is_dir($path)) {
                $directories[$package] = $path;
            }
        }

        return $directories;
    }

    /**
     * Return package directories for all Composer-installed packages (including transitive).
     *
     * Merges root requires with `vendor/composer/installed.json` so dependencies of
     * Ignis (for example `crustum/cakephp-skills`) are discoverable without a host require.
     *
     * @return array<string, string>
     */
    public static function installedPackagesDirectories(): array
    {
        $directories = self::packagesDirectories();

        foreach (self::installedPackagePaths() as $package => $path) {
            if (is_dir($path)) {
                $directories[$package] = $path;
            }
        }

        return $directories;
    }

    /**
     * Read package install paths from Composer's installed.json under the project root.
     *
     * @return array<string, string>
     */
    public static function installedPackagePaths(): array
    {
        $installedJson = ProjectRoot::path() . DS . 'vendor' . DS . 'composer' . DS . 'installed.json';

        if (!is_file($installedJson)) {
            return [];
        }

        $contents = file_get_contents($installedJson);

        if ($contents === false) {
            return [];
        }

        $data = json_decode($contents, true);

        if (!is_array($data)) {
            return [];
        }

        $packages = $data['packages'] ?? $data;

        if (!is_array($packages)) {
            return [];
        }

        $composerDir = dirname($installedJson);
        $paths = [];

        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }

            $name = $package['name'] ?? null;
            $installPath = $package['install-path'] ?? null;
            if (!is_string($name)) {
                continue;
            }

            if (!str_contains($name, '/')) {
                continue;
            }

            if (!is_string($installPath)) {
                continue;
            }

            if ($installPath === '') {
                continue;
            }

            $resolved = $composerDir . DS . str_replace(['/', '\\'], DS, $installPath);
            $real = realpath($resolved);

            $paths[$name] = $real !== false ? $real : $resolved;
        }

        return $paths;
    }

    /**
     * Return composer require and require-dev packages.
     *
     * @return array<string, string>
     */
    public static function packages(): array
    {
        $composerJsonPath = ProjectRoot::path() . DS . 'composer.json';

        if (!is_file($composerJsonPath)) {
            return [];
        }

        $contents = file_get_contents($composerJsonPath);

        if ($contents === false) {
            return [];
        }

        $composerData = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($composerData)) {
            return [];
        }

        $packages = array_merge(
            $composerData['require'] ?? [],
            $composerData['require-dev'] ?? [],
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
     * Return vendor package directories containing Ignis guidelines.
     *
     * @return array<string, string>
     */
    public static function packagesDirectoriesWithIgnisGuidelines(): array
    {
        return self::packagesDirectoriesWithIgnisSubpath('guidelines');
    }

    /**
     * Return vendor package directories containing Ignis skills.
     *
     * @return array<string, string>
     */
    public static function packagesDirectoriesWithIgnisSkills(): array
    {
        return self::packagesDirectoriesWithIgnisSubpath('skills');
    }

    /**
     * Return package directories containing a Ignis subpath.
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
