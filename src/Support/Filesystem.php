<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * Filesystem helper wrapping Symfony Filesystem without error-suppression operators.
 */
class Filesystem
{
    /**
     * Shared Symfony filesystem instance.
     *
     * @var \Symfony\Component\Filesystem\Filesystem|null
     */
    protected static ?SymfonyFilesystem $instance = null;

    /**
     * Create a directory recursively when it does not exist.
     *
     * @param string $path Directory path
     * @param int $mode Directory permissions
     * @return bool
     */
    public static function ensureDirectory(string $path, int $mode = 0755): bool
    {
        if (is_dir($path)) {
            return true;
        }

        try {
            self::symfony()->mkdir($path, $mode);

            return is_dir($path);
        } catch (IOException) {
            return is_dir($path);
        }
    }

    /**
     * Copy a file when the source exists.
     *
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @return bool
     */
    public static function copyFile(string $source, string $destination): bool
    {
        if (!is_file($source)) {
            return false;
        }

        try {
            self::symfony()->copy($source, $destination, true);

            return is_file($destination);
        } catch (IOException) {
            return is_file($destination);
        }
    }

    /**
     * Remove a file, symlink, or directory tree.
     *
     * For Windows directory links created by {@see DirectoryLink}, prefer
     * {@see DirectoryLink::remove()} when removing a link without deleting its target.
     *
     * @param string $path Path to remove
     * @return bool
     */
    public static function removeTree(string $path): bool
    {
        if (!file_exists($path) && !is_link($path)) {
            return true;
        }

        try {
            self::symfony()->remove($path);

            return !file_exists($path) && !is_link($path);
        } catch (IOException) {
            return !file_exists($path) && !is_link($path);
        }
    }

    /**
     * Remove a file, symlink, or directory tree.
     *
     * @param string $path Path to remove
     * @return bool
     */
    public static function removePath(string $path): bool
    {
        return self::removeTree($path);
    }

    /**
     * Remove an empty directory.
     *
     * Do not use for Windows junctions; use {@see DirectoryLink::remove()} instead.
     *
     * @param string $path Directory path
     * @return bool
     */
    public static function removeEmptyDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return !file_exists($path);
        }

        return self::removeTree($path);
    }

    /**
     * Return the shared Symfony filesystem instance.
     *
     * @return \Symfony\Component\Filesystem\Filesystem
     */
    protected static function symfony(): SymfonyFilesystem
    {
        return self::$instance ??= new SymfonyFilesystem();
    }
}
