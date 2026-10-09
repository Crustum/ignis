<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

use Crustum\Inspector\Enums\PackageSource;
use Crustum\Inspector\Package;

/**
 * Composer/npm package names and guideline directory mappings.
 */
class PackageRegistry
{
    public const IGNIS = 'crustum/ignis';

    public const CAKEPHP = 'cakephp/cakephp';

    public const MCP = 'crustum/mcp';

    public const PEST = 'pestphp/pest';

    public const PHPUNIT = 'phpunit/phpunit';

    /**
     * Package name to guideline directory name.
     *
     * @var array<string, string>
     */
    private const GUIDELINE_NAMES = [
        'cakephp/cakephp' => 'cakephp',
        'crustum/ignis' => 'ignis',
        'crustum/mcp' => 'mcp',
        'pestphp/pest' => 'pest',
        'phpunit/phpunit' => 'phpunit',
        'tailwindcss' => 'tailwindcss',
    ];

    /**
     * Resolve the guideline directory name for a package.
     *
     * @param string $package Composer or npm package name
     * @return string
     */
    public static function guidelineName(string $package): string
    {
        return self::GUIDELINE_NAMES[$package]
            ?? str_replace(['@', '/', '_'], ['', '-', '-'], strtolower($package));
    }

    /**
     * Resolve a pack relative folder path for a composer package.
     *
     * Uses known short aliases when registered; otherwise mirrors the composer
     * name as nested folders (for example `cakephp/queue` → `cakephp/queue`).
     *
     * @param string $package Composer package name
     * @return string
     */
    public static function skillPackFolderName(string $package): string
    {
        if (isset(self::GUIDELINE_NAMES[$package])) {
            return self::GUIDELINE_NAMES[$package];
        }

        return str_replace('@', '', strtolower($package));
    }

    /**
     * Resolve a composer package name from a pack relative folder path.
     *
     * Resolution order:
     * 1. Optional explicit `$targets` map from the pack's composer extra
     * 2. Known guideline aliases (`cakephp` → `cakephp/cakephp`)
     * 3. Nested path matching composer name (`cakephp/queue` → `cakephp/queue`)
     *
     * @param string $folderPath Relative path under pack/ (e.g. `cakephp/queue`)
     * @param array<string, string> $targets Optional folder → composer name map
     * @return string|null
     */
    public static function composerNameFromSkillPackFolder(string $folderPath, array $targets = []): ?string
    {
        $folderPath = strtolower(str_replace('\\', '/', trim($folderPath)));
        $folderPath = trim($folderPath, '/');

        if ($folderPath === '') {
            return null;
        }

        if (isset($targets[$folderPath]) && $targets[$folderPath] !== '') {
            return $targets[$folderPath];
        }

        $known = array_flip(self::GUIDELINE_NAMES);

        if (isset($known[$folderPath])) {
            return $known[$folderPath];
        }

        if (str_contains($folderPath, '/')) {
            return $folderPath;
        }

        return null;
    }

    /**
     * Resolve a inspector-style display name for a package.
     *
     * @param string $package Composer or npm package name
     * @return string
     */
    public static function inspectorName(string $package): string
    {
        return strtoupper(str_replace('-', '_', self::guidelineName($package)));
    }

    /**
     * Whether a inspector package is first-party for Ignis.
     *
     * @param \Crustum\Inspector\Package $package Inspector package
     * @return bool
     */
    public static function isFirstParty(Package $package): bool
    {
        return match ($package->source()) {
            PackageSource::Composer => Composer::isFirstPartyPackage($package->name()),
            PackageSource::Npm => Npm::isFirstPartyPackage($package->name()),
        };
    }

    /**
     * Resolve a inspector package Ignis resource path when present.
     *
     * @param \Crustum\Inspector\Package $package Inspector package
     * @param string $subpath Ignis resources subpath
     * @return string|null
     */
    public static function ignisPath(Package $package, string $subpath): ?string
    {
        $packagePath = $package->path();

        if ($packagePath === null) {
            return null;
        }

        $path = implode(DIRECTORY_SEPARATOR, [$packagePath, 'resources', 'ignis', $subpath]);

        return is_dir($path) ? $path : null;
    }
}
