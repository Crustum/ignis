<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Crustum\Ignis\Support\ProjectRoot;
use RuntimeException;

/**
 * Resolves and validates --path install targets for Ignis.
 */
class InstallPath
{
    /**
     * Resolve an absolute or application-relative install path to a real directory.
     *
     * @param string $path Absolute path or path relative to the CakePHP application ROOT
     * @return string Realpath of an existing directory
     */
    public static function resolve(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));

        if ($normalized === '') {
            throw new RuntimeException('Install path must not be empty.');
        }

        if (!self::isAbsolutePath($normalized)) {
            $normalized = rtrim(ProjectRoot::applicationPath(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . ltrim($normalized, DIRECTORY_SEPARATOR);
        }

        $resolved = realpath($normalized);

        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException(sprintf(
                'Install path [%s] does not exist or is not a directory.',
                $path,
            ));
        }

        return $resolved;
    }

    /**
     * Whether the target already has an Ignis `.ai` tree (conflict for --path installs).
     *
     * @param string $path Resolved install directory
     * @return bool
     */
    public static function hasAiConflict(string $path): bool
    {
        return is_dir($path . DIRECTORY_SEPARATOR . '.ai');
    }

    /**
     * Whether a filesystem path is absolute.
     *
     * @param string $path Path to inspect
     * @return bool
     */
    public static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return strlen($path) > 1 && $path[1] === ':';
    }
}
