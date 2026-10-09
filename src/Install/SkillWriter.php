<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Cake\Collection\Collection;
use Crustum\Ignis\Contracts\SupportsSkills;
use Crustum\Ignis\Support\DirectoryLink;
use Crustum\Ignis\Support\Filesystem;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Ignis\Trait\RendersTwigGuidelinesTrait;
use Crustum\Inspector\ProjectManager;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Installs discovered skills into an agent-specific skills directory.
 */
class SkillWriter
{
    use RendersTwigGuidelinesTrait;

    public const SUCCESS = 0;

    public const UPDATED = 1;

    public const FAILED = 2;

    /**
     * Constructor.
     *
     * @param \Crustum\Ignis\Contracts\SupportsSkills $agent Target agent
     */
    public function __construct(protected SupportsSkills $agent)
    {
    }

    /**
     * Install a skill into the agent skills directory.
     *
     * @param \Crustum\Ignis\Install\Skill $skill Skill to install
     * @return self::SUCCESS|self::UPDATED|self::FAILED
     */
    public function write(Skill $skill): int
    {
        if (!self::isValidSkillName($skill->name)) {
            throw new RuntimeException("Invalid skill name: {$skill->name}");
        }

        $targetPath = $this->resolveProjectPath($this->agent->skillsPath(), $skill->name);
        $canonicalPath = $this->resolveProjectPath('.ai', 'skills', $skill->name);
        $existed = $this->pathExists($targetPath);

        if (!$skill->custom) {
            return $this->writeNonCustomSkill($skill, $targetPath, $canonicalPath, $existed);
        }

        return $this->writeCustomSkill($skill, $targetPath, $canonicalPath, $existed);
    }

    /**
     * Install a bundled or inspector skill.
     *
     * @param \Crustum\Ignis\Install\Skill $skill Skill to install
     * @param string $targetPath Agent skill target path
     * @param string $canonicalPath Canonical `.ai/skills` path
     * @param bool $existed Whether the target path already existed
     * @return self::SUCCESS|self::UPDATED|self::FAILED
     */
    protected function writeNonCustomSkill(Skill $skill, string $targetPath, string $canonicalPath, bool $existed): int
    {
        $canonicalExists = $this->pathExists($canonicalPath);
        $needsCanonicalUpdate = $canonicalExists && !$this->pathsMatch($skill->path, $canonicalPath);

        if ($needsCanonicalUpdate && !$this->copyDirectory($skill->path, $canonicalPath)) {
            return self::FAILED;
        }

        if (!$this->copyDirectory($skill->path, $targetPath)) {
            return self::FAILED;
        }

        return $existed ? self::UPDATED : self::SUCCESS;
    }

    /**
     * Install a user-authored skill.
     *
     * @param \Crustum\Ignis\Install\Skill $skill Skill to install
     * @param string $targetPath Agent skill target path
     * @param string $canonicalPath Canonical `.ai/skills` path
     * @param bool $existed Whether the target path already existed
     * @return self::SUCCESS|self::UPDATED|self::FAILED
     */
    protected function writeCustomSkill(Skill $skill, string $targetPath, string $canonicalPath, bool $existed): int
    {
        if (!$this->pathsMatch($skill->path, $canonicalPath) && !$this->copyDirectory($skill->path, $canonicalPath)) {
            return self::FAILED;
        }

        if (!$this->ensureDirectoryExists(dirname($targetPath))) {
            return self::FAILED;
        }

        if ($this->directoryContainsTwigFiles($canonicalPath)) {
            if (!$this->copyDirectory($canonicalPath, $targetPath)) {
                return self::FAILED;
            }

            return $existed ? self::UPDATED : self::SUCCESS;
        }

        if ($this->pathExists($targetPath) && !DirectoryLink::isLink($targetPath)) {
            $this->deleteDirectory($targetPath);
        }

        if (!$this->createSymlink($canonicalPath, $targetPath) && !$this->copyDirectory($skill->path, $targetPath)) {
            return self::FAILED;
        }

        return $existed ? self::UPDATED : self::SUCCESS;
    }

    /**
     * Determine whether a path exists as a directory or symlink.
     *
     * @param string $path Path to inspect
     * @return bool
     */
    protected function pathExists(string $path): bool
    {
        return is_dir($path) || is_link($path) || DirectoryLink::isLink($path);
    }

    /**
     * Install all skills and return per-skill status codes.
     *
     * @param \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill> $skills Skills to install
     * @return array<string, int>
     */
    public function writeAll(Collection $skills): array
    {
        $valid = [];
        $invalid = [];

        foreach ($skills as $name => $skill) {
            if (self::isValidSkillName($skill->name)) {
                $valid[$name] = $skill;
            } else {
                $invalid[$name] = $skill;
            }
        }

        $written = [];

        foreach ($valid as $name => $skill) {
            $written[(string)$name] = $this->write($skill);
        }

        if ($invalid !== []) {
            $badNames = implode(', ', array_map(
                static fn(Skill $skill): string => $skill->name,
                $invalid,
            ));

            throw new RuntimeException("Invalid skill name: {$badNames}");
        }

        return $written;
    }

    /**
     * Install skills and remove stale tracked skills.
     *
     * @param \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill> $skills Skills to install
     * @param array<int, string> $previouslyTrackedSkills Previously tracked skill names
     * @return array<string, int>
     */
    public function sync(Collection $skills, array $previouslyTrackedSkills = []): array
    {
        $newSkillNames = array_keys($skills->toArray());
        $staleSkillNames = array_values(array_diff($previouslyTrackedSkills, $newSkillNames));
        $removals = $this->removeStale($staleSkillNames);
        $failedRemovals = array_fill_keys(array_keys($removals, false, true), self::FAILED);

        return [...$failedRemovals, ...$this->writeAll($skills)];
    }

    /**
     * Remove an installed skill directory.
     *
     * @param string $skillName Skill name
     * @return bool
     */
    public function remove(string $skillName): bool
    {
        if (!self::isValidSkillName($skillName)) {
            return false;
        }

        $targetPath = $this->resolveProjectPath($this->agent->skillsPath(), $skillName);

        if (!$this->pathExists($targetPath)) {
            return true;
        }

        return $this->deleteDirectory($targetPath);
    }

    /**
     * Remove multiple stale skill directories.
     *
     * @param array<int, string> $skillNames Skill names
     * @return array<string, bool>
     */
    public function removeStale(array $skillNames): array
    {
        $results = [];

        foreach ($skillNames as $name) {
            $results[$name] = $this->remove($name);
        }

        return $results;
    }

    /**
     * Recursively delete a directory or symlink.
     *
     * Windows junctions are not PHP symlinks (`is_link` is false). Prefer
     * {@see DirectoryLink} so the link is removed without deleting target contents.
     *
     * @param string $path Path to delete
     * @return bool
     */
    protected function deleteDirectory(string $path): bool
    {
        if (DirectoryLink::isLink($path)) {
            return DirectoryLink::remove($path);
        }

        if (is_link($path)) {
            if (unlink($path)) {
                return true;
            }

            if (is_dir($path) && rmdir($path)) {
                return true;
            }

            return !file_exists($path) && !is_link($path);
        }

        if (is_file($path)) {
            return unlink($path);
        }

        if (!is_dir($path)) {
            return !file_exists($path);
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            if (!$this->deleteDirectory($path . DIRECTORY_SEPARATOR . $item)) {
                $childPath = $path . DIRECTORY_SEPARATOR . $item;

                if (file_exists($childPath) || is_link($childPath) || DirectoryLink::isLink($childPath)) {
                    return false;
                }
            }
        }

        return $this->removeEmptiedDirectoryPath($path);
    }

    /**
     * Remove a path after its children were deleted.
     *
     * Re-checks {@see DirectoryLink} before `rmdir` so a Windows junction is
     * never treated as a normal directory at the final step. The `!pathIsDirectory`
     * fallback covers races where the path is already gone after a failed `rmdir`.
     *
     * @param string $path Emptied directory path
     * @return bool
     */
    protected function removeEmptiedDirectoryPath(string $path): bool
    {
        if (DirectoryLink::isLink($path)) {
            return DirectoryLink::remove($path);
        }

        if (!$this->pathIsDirectory($path)) {
            return true;
        }

        return rmdir($path) || !$this->pathIsDirectory($path);
    }

    /**
     * Whether the path is currently a directory (fresh filesystem probe).
     *
     * @param string $path Path to inspect
     * @return bool
     * @phpstan-impure
     */
    protected function pathIsDirectory(string $path): bool
    {
        clearstatcache(true, $path);

        return is_dir($path);
    }

    /**
     * Copy a skill directory into a target directory.
     *
     * @param string $source Source directory
     * @param string $target Target directory
     * @return bool
     */
    protected function copyDirectory(string $source, string $target): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        $this->deleteDirectory($target);

        if (!$this->ensureDirectoryExists($target)) {
            throw new RuntimeException("Failed to create directory: {$target}");
        }

        $finder = Finder::create()
            ->files()
            ->in($source)
            ->ignoreDotFiles(false);

        foreach ($finder as $file) {
            if (!$this->copyFile($file, $target)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Copy or compile a single skill file into the target directory.
     *
     * @param \Symfony\Component\Finder\SplFileInfo $file Source file
     * @param string $targetDir Target directory
     * @return bool
     */
    protected function copyFile(SplFileInfo $file, string $targetDir): bool
    {
        $relativePath = $file->getRelativePathname();
        $targetFile = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

        if (!$this->ensureDirectoryExists(dirname($targetFile))) {
            return false;
        }

        $isTwigFile = str_ends_with($relativePath, '.twig');
        $isMarkdownFile = str_ends_with($relativePath, '.md');

        if ($isTwigFile) {
            $content = MarkdownFormatter::format(trim($this->renderTwigFile($file->getRealPath())));
            $replacedTargetFile = preg_replace('/\.twig$/', '.md', $targetFile);

            $replacedTargetFile ??= substr($targetFile, 0, -5) . '.md';

            return file_put_contents($replacedTargetFile, $this->ensureTrailingNewline($content)) !== false;
        }

        if ($isMarkdownFile) {
            $sourceContents = file_get_contents($file->getRealPath());
            $content = MarkdownFormatter::format(trim(is_string($sourceContents) ? $sourceContents : ''));

            return file_put_contents($targetFile, $this->ensureTrailingNewline($content)) !== false;
        }

        if (!Filesystem::copyFile($file->getRealPath(), $targetFile)) {
            return false;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $permissions = $file->getPerms();

            if (is_int($permissions) && is_writable($targetFile)) {
                chmod($targetFile, $permissions & 0777 & ~umask());
            }
        }

        return true;
    }

    /**
     * Ensure markdown content ends with a trailing newline.
     *
     * @param string $content Markdown content
     * @return string
     */
    protected function ensureTrailingNewline(string $content): string
    {
        return str_ends_with($content, "\n") ? $content : $content . "\n";
    }

    /**
     * Create a directory when missing.
     *
     * @param string $path Directory path
     * @return bool
     */
    protected function ensureDirectoryExists(string $path): bool
    {
        return Filesystem::ensureDirectory($path);
    }

    /**
     * Create a directory symlink or Windows junction.
     *
     * @param string $target Existing target directory
     * @param string $link Link path to create
     * @return bool Whether the link was created
     */
    protected function createSymlink(string $target, string $link): bool
    {
        return DirectoryLink::create($target, $link);
    }

    /**
     * Determine whether two paths resolve to the same location.
     *
     * @param string $left First path
     * @param string $right Second path
     * @return bool
     */
    protected function pathsMatch(string $left, string $right): bool
    {
        $resolvedLeft = realpath($left) ?: $left;
        $resolvedRight = realpath($right) ?: $right;

        return rtrim($resolvedLeft, DIRECTORY_SEPARATOR) === rtrim($resolvedRight, DIRECTORY_SEPARATOR);
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

    /**
     * Determine whether a directory contains Twig skill templates.
     *
     * @param string $path Directory path
     * @return bool
     */
    protected function directoryContainsTwigFiles(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with((string)$file->getFilename(), '.twig')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a path relative to the active project root.
     *
     * @param string ...$segments Path segments
     * @return string
     */
    protected function resolveProjectPath(string ...$segments): string
    {
        return $this->normalizeAbsolutePath(ProjectRoot::path(), ...$segments);
    }

    /**
     * Join and normalize path segments including parent references.
     *
     * @param string $basePath Base path
     * @param string ...$segments Additional segments
     * @return string
     */
    protected function normalizeAbsolutePath(string $basePath, string ...$segments): string
    {
        $normalized = str_replace('\\', '/', $basePath);
        $prefix = '';

        if (preg_match('#^([A-Za-z]:)(.*)$#', $normalized, $matches) === 1) {
            $prefix = $matches[1];
            $normalized = $matches[2];
        } elseif (str_starts_with($normalized, '/')) {
            $prefix = '/';
        }

        $parts = [];

        foreach (explode('/', trim($normalized, '/')) as $part) {
            if ($part === '') {
                continue;
            }

            if ($part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        foreach ($segments as $segment) {
            $segment = str_replace('\\', '/', $segment);

            foreach (explode('/', $segment) as $part) {
                if ($part === '') {
                    continue;
                }

                if ($part === '.') {
                    continue;
                }

                if ($part === '..') {
                    array_pop($parts);

                    continue;
                }

                $parts[] = $part;
            }
        }

        if ($parts === []) {
            return $prefix === '/' ? '/' : ($prefix !== '' ? $prefix . DS : DS);
        }

        if ($prefix === '/') {
            return '/' . implode('/', $parts);
        }

        $tail = str_replace('/', DS, implode('/', $parts));

        if ($prefix !== '') {
            return $prefix . DS . ltrim($tail, DS);
        }

        return $tail;
    }

    /**
     * Validate a skill name against path traversal and reserved names.
     *
     * @param string $name Skill name
     * @return bool
     */
    public static function isValidSkillName(string $name): bool
    {
        if (str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            return false;
        }

        return trim($name, ". \t\n\r\0\x0B") !== '';
    }

    /**
     * Return the assist instance injected into Twig templates.
     *
     * @return \Crustum\Ignis\Install\GuidelineAssist
     */
    protected function getGuidelineAssist(): GuidelineAssist
    {
        $project = new ProjectManager();
        $project->scan(ProjectRoot::path());

        return new GuidelineAssist($project, new GuidelineConfig());
    }
}
