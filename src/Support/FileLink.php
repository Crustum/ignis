<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

/**
 * Creates and removes file links with a Windows hard-link fallback.
 *
 * Prefer a real symlink when the OS allows it. On Windows without symlink
 * privilege (or Developer Mode), fall back to a same-volume hard link.
 */
class FileLink
{
    private static ?self $instance = null;

    private readonly bool $isWindows;

    /**
     * Normalized link paths created during this PHP process.
     *
     * @var array<string, true>
     */
    private array $knownLinks = [];

    /**
     * Cached isLink() results keyed by normalized path.
     *
     * @var array<string, bool>
     */
    private array $linkStatus = [];

    /**
     * Initialize the platform-specific link implementation.
     */
    private function __construct()
    {
        $this->isWindows = PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Resolve the shared file link helper.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (!self::$instance instanceof FileLink) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Create a file link from link path to target file.
     *
     * @param string $target Link target file
     * @param string $link Link path to create
     * @return bool
     */
    public static function create(string $target, string $link): bool
    {
        return self::instance()->createLink($target, $link);
    }

    /**
     * Determine whether a path resolves to the same file as the target.
     *
     * @param string $link Link path
     * @param string $target Expected target file
     * @return bool
     */
    public static function pointsTo(string $link, string $target): bool
    {
        if (!is_file($link) || !is_file($target)) {
            return false;
        }

        $linkInode = fileinode($link);
        $targetInode = fileinode($target);

        if ($linkInode !== false && $targetInode !== false && $linkInode === $targetInode) {
            return true;
        }

        $resolvedLink = realpath($link);
        $resolvedTarget = realpath($target);

        if ($resolvedLink === false || $resolvedTarget === false) {
            return false;
        }

        return str_replace('\\', '/', $resolvedLink) === str_replace('\\', '/', $resolvedTarget);
    }

    /**
     * Determine whether a path is a symlink or file link created here.
     *
     * @param string $path Path to inspect
     * @return bool
     */
    public static function isLink(string $path): bool
    {
        return self::instance()->pathIsLink($path);
    }

    /**
     * Remove a file link without deleting the target file contents.
     *
     * @param string $path Link path
     * @return bool
     */
    public static function remove(string $path): bool
    {
        return self::instance()->removeLink($path);
    }

    /**
     * Create a file link from link path to target file.
     *
     * @param string $target Link target file
     * @param string $link Link path to create
     * @return bool
     */
    public function createLink(string $target, string $link): bool
    {
        $absoluteTarget = realpath($target);

        if ($absoluteTarget === false || !is_file($absoluteTarget)) {
            return false;
        }

        if (self::pointsTo($link, $absoluteTarget)) {
            $this->rememberLink($link);

            return true;
        }

        $this->removeLink($link);

        $linkDirectory = dirname($link);

        if (!Filesystem::ensureDirectory($linkDirectory, 0777)) {
            return false;
        }

        if ($this->isWindows) {
            if ($this->createWindowsHardLink($absoluteTarget, $link)) {
                $this->rememberLink($link);

                return true;
            }

            if ($this->createUnixStyleSymlink($absoluteTarget, $link)) {
                $this->rememberLink($link);

                return true;
            }

            return false;
        }

        if ($this->createUnixStyleSymlink($absoluteTarget, $link)) {
            $this->rememberLink($link);

            return true;
        }

        return false;
    }

    /**
     * Determine whether a path is a symlink or hard link created here.
     *
     * @param string $path Path to inspect
     * @return bool
     */
    public function pathIsLink(string $path): bool
    {
        if (is_link($path)) {
            $this->linkStatus[$this->linkKey($path)] = true;

            return true;
        }

        $linkKey = $this->linkKey($path);

        if (isset($this->knownLinks[$linkKey])) {
            $this->linkStatus[$linkKey] = true;

            return true;
        }

        if (array_key_exists($linkKey, $this->linkStatus)) {
            return $this->linkStatus[$linkKey];
        }

        $this->linkStatus[$linkKey] = false;

        return false;
    }

    /**
     * Remove a file link without deleting the target file contents.
     *
     * @param string $path Link path
     * @return bool
     */
    public function removeLink(string $path): bool
    {
        $linkKey = $this->linkKey($path);

        if (!is_link($path) && !isset($this->knownLinks[$linkKey]) && !is_file($path)) {
            $this->forgetLink($path);

            return true;
        }

        if (!is_link($path) && !isset($this->knownLinks[$linkKey])) {
            return false;
        }

        if (!file_exists($path) && !is_link($path)) {
            $this->forgetLink($path);

            return true;
        }

        $removed = unlink($path);

        if ($removed || (!file_exists($path) && !is_link($path))) {
            $this->forgetLink($path);

            return true;
        }

        return false;
    }

    /**
     * Create a POSIX-style (or Windows Developer Mode) file symlink.
     *
     * @param string $absoluteTarget Absolute target file
     * @param string $link Link path
     * @return bool
     */
    protected function createUnixStyleSymlink(string $absoluteTarget, string $link): bool
    {
        $linkDirectory = dirname($link);
        $symlinkTarget = $this->isWindows
            ? $absoluteTarget
            : $this->relativePath($absoluteTarget, $linkDirectory);

        if ($this->tryFilesystemLink(static fn(): bool => symlink($symlinkTarget, $link)) && is_link($link)) {
            return true;
        }

        return $this->isWindows
            && $this->tryFilesystemLink(static fn(): bool => symlink($absoluteTarget, $link))
            && is_link($link);
    }

    /**
     * Create a Windows same-volume hard link (no symlink privilege required).
     *
     * @param string $absoluteTarget Absolute target file
     * @param string $link Link path
     * @return bool
     */
    protected function createWindowsHardLink(string $absoluteTarget, string $link): bool
    {
        if (file_exists($link) || is_link($link)) {
            $this->removeLink($link);
        }

        if ($this->tryFilesystemLink(static fn(): bool => link($absoluteTarget, $link)) && is_file($link)) {
            return true;
        }

        $command = 'cmd /c mklink /H '
            . escapeshellarg($link)
            . ' '
            . escapeshellarg($absoluteTarget)
            . ' >nul 2>&1';
        exec($command, $output, $exitCode);

        return $exitCode === 0 && is_file($link);
    }

    /**
     * Run a filesystem link call while converting warnings into a false result.
     *
     * @param callable(): bool $callback Link creation callback
     * @return bool
     */
    protected function tryFilesystemLink(callable $callback): bool
    {
        set_error_handler(static fn(): bool => true);

        try {
            return $callback() === true;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Remember a link path created by this instance.
     *
     * @param string $path Link path
     * @return void
     */
    protected function rememberLink(string $path): void
    {
        $linkKey = $this->linkKey($path);
        $this->knownLinks[$linkKey] = true;
        $this->linkStatus[$linkKey] = true;
    }

    /**
     * Forget cached link state for a removed link path.
     *
     * @param string $path Link path
     * @return void
     */
    protected function forgetLink(string $path): void
    {
        $linkKey = $this->linkKey($path);
        unset($this->knownLinks[$linkKey]);
        $this->linkStatus[$linkKey] = false;
    }

    /**
     * Normalize a link path without following the target.
     *
     * @param string $path Link path
     * @return string
     */
    protected function linkKey(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $prefix = '';

        if (preg_match('#^([A-Za-z]:)(.*)$#', $normalized, $matches) === 1) {
            $prefix = strtolower($matches[1]);
            $normalized = $matches[2];
        }

        $resolved = [];

        foreach (explode('/', $normalized) as $part) {
            if ($part === '') {
                continue;
            }

            if ($part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($resolved);

                continue;
            }

            $resolved[] = $part;
        }

        return $prefix . implode('/', $resolved);
    }

    /**
     * Build a relative path from one directory to a target file.
     *
     * @param string $target Target file path
     * @param string $from Source directory
     * @return string
     */
    protected function relativePath(string $target, string $from): string
    {
        $resolvedTarget = str_replace('\\', '/', realpath($target) ?: $target);
        $resolvedFrom = str_replace('\\', '/', realpath($from) ?: $from);
        $targetSegments = explode('/', $resolvedTarget);
        $fromSegments = explode('/', $resolvedFrom);
        $commonDepth = 0;
        $maxSharedDepth = min(count($targetSegments), count($fromSegments));

        while ($commonDepth < $maxSharedDepth && $targetSegments[$commonDepth] === $fromSegments[$commonDepth]) {
            $commonDepth++;
        }

        if ($commonDepth === 0) {
            return $resolvedTarget;
        }

        $traversalsUp = count($fromSegments) - $commonDepth;
        $remainingTarget = array_slice($targetSegments, $commonDepth);

        return str_repeat('../', $traversalsUp) . implode('/', $remainingTarget);
    }
}
