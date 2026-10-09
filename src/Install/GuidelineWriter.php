<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Crustum\Ignis\Contracts\SupportsGuidelines;
use Crustum\Ignis\Support\Filesystem;
use Crustum\Ignis\Support\ProjectRoot;
use RuntimeException;

/**
 * Writes composed guidelines into an agent-specific guidelines file.
 */
class GuidelineWriter
{
    public const NEW = 0;

    public const REPLACED = 1;

    public const FAILED = 2;

    public const NOOP = 3;

    /**
     * Constructor.
     *
     * @param \Crustum\Ignis\Contracts\SupportsGuidelines $agent Target agent
     */
    public function __construct(protected SupportsGuidelines $agent)
    {
    }

    /**
     * Write composed guidelines into the agent guidelines file.
     *
     * @param string $guidelines Composed markdown guidelines
     * @return self::NEW|self::REPLACED|self::FAILED|self::NOOP
     */
    public function write(string $guidelines): int
    {
        if ($guidelines === '') {
            return self::NOOP;
        }

        $guidelines = $this->agent->transformGuidelines($guidelines);
        $filePath = $this->resolveGuidelinesPath($this->agent->guidelinesPath());
        $directory = dirname($filePath);

        if (!Filesystem::ensureDirectory($directory)) {
            throw new RuntimeException("Failed to create directory: {$directory}");
        }

        $handle = fopen($filePath, 'c+');

        if ($handle === false) {
            throw new RuntimeException("Failed to open file: {$filePath}");
        }

        try {
            $this->acquireLockWithRetry($handle, $filePath);
            $content = stream_get_contents($handle);
            $content = is_string($content) ? $content : '';
            $pattern = '/<cake-ignis-guidelines>.*?<\/cake-ignis-guidelines>/s';
            $replacement = "<cake-ignis-guidelines>\n" . $guidelines . "\n\n</cake-ignis-guidelines>";
            $replaced = false;

            if (preg_match($pattern, $content)) {
                $newContent = preg_replace_callback($pattern, static fn(array $matches): string => $replacement, $content, 1);
                $replaced = true;
            } else {
                $frontMatter = '';

                if ($this->agent->frontmatter() && !str_contains($content, "\n---\n")) {
                    $frontMatter = "---\nalwaysApply: true\n---\n";
                }

                $existingContent = rtrim($content);
                $separatingNewlines = $existingContent === '' ? '' : "\n\n===\n\n";
                $newContent = $frontMatter . $existingContent . $separatingNewlines . $replacement;
            }

            if (!str_ends_with((string)$newContent, "\n")) {
                $newContent .= "\n";
            }

            if (ftruncate($handle, 0) === false || fseek($handle, 0) === -1) {
                throw new RuntimeException("Failed to reset file pointer: {$filePath}");
            }

            if (fwrite($handle, (string)$newContent) === false) {
                throw new RuntimeException("Failed to write to file: {$filePath}");
            }

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $replaced ? self::REPLACED : self::NEW;
    }

    /**
     * Acquire an exclusive file lock with retry and backoff.
     *
     * @param resource $handle Open file handle
     * @param string $filePath Absolute file path
     * @param int $maxRetries Maximum retry attempts
     * @return void
     */
    protected function acquireLockWithRetry(mixed $handle, string $filePath, int $maxRetries = 3): void
    {
        $attempts = 0;
        $delay = 100000;

        while ($attempts < $maxRetries) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return;
            }

            $attempts++;

            if ($attempts >= $maxRetries) {
                throw new RuntimeException("Failed to acquire lock on file after {$maxRetries} attempts: {$filePath}");
            }

            $jitter = random_int(0, (int)($delay * 0.1));
            usleep($delay + $jitter);
            $delay *= 2;
        }
    }

    /**
     * Resolve a guidelines file path against the active install root.
     *
     * @param string $path Absolute or project-relative guidelines path
     * @return string
     */
    protected function resolveGuidelinesPath(string $path): string
    {
        if (InstallPath::isAbsolutePath($path)) {
            return $path;
        }

        return ProjectRoot::path() . DS . ltrim(str_replace(['/', '\\'], DS, $path), DS);
    }
}
