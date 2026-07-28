<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

/**
 * Creates and removes directory symlinks with a Windows junction fallback.
 *
 * OS capabilities and created link paths are resolved once per PHP process.
 */
class DirectoryLink
{
    private static ?self $instance = null;

    private readonly bool $usesJunctions;

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
        $this->usesJunctions = PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Resolve the shared directory link helper.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (!self::$instance instanceof DirectoryLink) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Create a directory link from link path to target path.
     *
     * @param string $target Link target directory
     * @param string $link Link path to create
     * @return bool
     */
    public static function create(string $target, string $link): bool
    {
        return self::instance()->createLink($target, $link);
    }

    /**
     * Determine whether a path resolves to the target directory.
     *
     * @param string $link Link path
     * @param string $target Expected target directory
     * @return bool
     */
    public static function pointsTo(string $link, string $target): bool
    {
        $resolvedLink = realpath($link);
        $resolvedTarget = realpath($target);

        if ($resolvedLink === false || $resolvedTarget === false) {
            return false;
        }

        return rtrim(str_replace('\\', '/', $resolvedLink), '/')
            === rtrim(str_replace('\\', '/', $resolvedTarget), '/');
    }

    /**
     * Determine whether a path is a symlink or directory link created here.
     *
     * @param string $path Path to inspect
     * @return bool
     */
    public static function isLink(string $path): bool
    {
        return self::instance()->pathIsLink($path);
    }

    /**
     * Remove a directory link without deleting the link target contents.
     *
     * @param string $path Link path
     * @return bool
     */
    public static function remove(string $path): bool
    {
        return self::instance()->removeLink($path);
    }

    /**
     * Create a directory link from link path to target path.
     *
     * @param string $target Link target directory
     * @param string $link Link path to create
     * @return bool
     */
    public function createLink(string $target, string $link): bool
    {
        $resolvedTarget = realpath($target) ?: $target;
        $resolvedLink = realpath($link) ?: $link;

        if (rtrim(str_replace('\\', '/', $resolvedTarget), '/') === rtrim(str_replace('\\', '/', $resolvedLink), '/')) {
            $this->rememberLink($link);

            return true;
        }

        $this->removeLink($link);

        $linkDirectory = dirname($link);

        if (!Filesystem::ensureDirectory($linkDirectory, 0777)) {
            return false;
        }

        if (!$this->usesJunctions) {
            $relativeTarget = $this->relativePath($resolvedTarget, $linkDirectory);

            if (symlink($relativeTarget, $link) && is_link($link)) {
                $this->rememberLink($link);

                return true;
            }

            return false;
        }

        if (!$this->createWindowsJunction($resolvedTarget, $link)) {
            return false;
        }

        $this->rememberLink($link);

        return true;
    }

    /**
     * Determine whether a path is a symlink or directory link created here.
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
     * Remove a directory link without deleting the link target contents.
     *
     * @param string $path Link path
     * @return bool
     */
    public function removeLink(string $path): bool
    {
        $linkKey = $this->linkKey($path);

        if (is_link($path)) {
            $removed = unlink($path);

            if ($removed || (!is_link($path) && !file_exists($path))) {
                $this->forgetLink($path);

                return true;
            }

            return false;
        }

        if (!isset($this->knownLinks[$linkKey])) {
            return false;
        }

        return $this->removeKnownDirectoryLink($path);
    }

    /**
     * Remove a Windows junction (or other known directory link) tracked by this helper.
     *
     * Junctions are directories to PHP (`is_link` is false); `rmdir` removes the link
     * without deleting the junction target.
     *
     * @param string $path Known directory link path
     * @return bool
     */
    protected function removeKnownDirectoryLink(string $path): bool
    {
        if (!file_exists($path) && !is_link($path)) {
            $this->forgetLink($path);

            return true;
        }

        $removed = is_link($path) ? unlink($path) : rmdir($path);

        if ($removed || (!file_exists($path) && !is_link($path))) {
            $this->forgetLink($path);

            return true;
        }

        return false;
    }

    /**
     * Create a Windows directory junction.
     *
     * @param string $target Junction target directory
     * @param string $link Junction path
     * @return bool
     */
    protected function createWindowsJunction(string $target, string $link): bool
    {
        $absoluteTarget = realpath($target);

        if ($absoluteTarget === false || !is_dir($absoluteTarget)) {
            return false;
        }

        if (file_exists($link)) {
            $this->removeLink($link);
        }

        $command = 'cmd /c mklink /J ' . escapeshellarg($link) . ' ' . escapeshellarg($absoluteTarget) . ' >nul 2>&1';
        exec($command, $output, $exitCode);

        return $exitCode === 0 && file_exists($link);
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
     * Normalize a link path without following junction targets.
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
     * Build a relative path from one directory to a target path.
     *
     * @param string $target Target path
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
