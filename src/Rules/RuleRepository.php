<?php
declare(strict_types=1);

namespace Crustum\Ignis\Rules;

use Cake\Collection\Collection;
use Cake\Collection\CollectionInterface;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use Crustum\Ignis\Support\ProjectRoot;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Stores project AI rules under .ai/rules as markdown files.
 */
class RuleRepository
{
    protected const INDEX_FILENAME = 'index.md';

    protected const MANAGED_DIRNAME = 'ignis';

    /**
     * Constructor.
     *
     * @param string $directory Rules directory path
     */
    public function __construct(protected string $directory)
    {
    }

    /**
     * Replace the Ignis-managed rule files, keyed by filename slug, and regenerate the index.
     *
     * @param \Cake\Collection\CollectionInterface<string, array{paths: array<int, string>, title: string, content: string}> $files Managed files
     * @return array<int, string> Written file paths
     */
    public function syncManaged(CollectionInterface $files): array
    {
        $dir = $this->managedDir();

        $this->removeManagedDir($dir);

        if ($files->isEmpty()) {
            $this->reconcileAfterManagedRemoval();

            return [];
        }

        $this->ensureDirectoryExists($dir);

        $written = [];

        foreach ($files as $slug => $file) {
            $path = $this->joinPaths($dir, $slug . '.md');
            $written[] = $path;
            file_put_contents($path, $this->renderManagedFile($file['paths'], $file['title'], $file['content']));
        }

        $this->writeIndex();

        return $written;
    }

    /**
     * Remove all Ignis-managed rule files.
     *
     * @return bool Whether the managed directory existed
     */
    public function clearManaged(): bool
    {
        $dir = $this->managedDir();
        $existed = is_dir($dir);

        $this->removeManagedDir($dir);

        if ($existed) {
            $this->reconcileAfterManagedRemoval();
        }

        return $existed;
    }

    /**
     * Delete the managed directory, throwing if removal is incomplete.
     *
     * @param string $dir Managed directory path
     * @return void
     */
    protected function removeManagedDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $this->deleteDirectory($dir);

        if (file_exists($dir)) {
            throw new RuntimeException('Unable to remove managed rules directory: ' . $dir);
        }
    }

    /**
     * Reconcile the rules index after managed files are removed.
     *
     * @return void
     */
    protected function reconcileAfterManagedRemoval(): void
    {
        if ($this->parsedFiles() !== []) {
            $this->writeIndex();

            return;
        }

        $indexPath = $this->indexPath();

        if (is_file($indexPath)) {
            unlink($indexPath);
        }

        if (is_dir($this->directory) && $this->isEmptyDirectory($this->directory)) {
            rmdir($this->directory);
        }
    }

    /**
     * Record a rule and return the location it was stored at.
     *
     * @param string $glob Target glob
     * @param string $title Rule title
     * @param string $note Rule note body
     * @return string
     */
    public function write(string $glob, string $title, string $note): string
    {
        $glob = $this->normalizeGlob($glob);
        $title = trim((string)preg_replace('/\R/', ' ', $title));
        $note = trim($note);

        $target = $this->resolveTargetFile($glob);

        if (!is_file($target['path'])) {
            $this->createFile($target['path'], $target['heading'], [$glob]);
        } else {
            $this->ensureGlobApplied($target['path'], $glob, $target['parsed']);
        }

        $this->appendEntry($target['path'], $title, $note);
        $this->writeIndex();

        return $target['path'];
    }

    /**
     * Write the rules index markdown file.
     *
     * @return string
     */
    public function writeIndex(): string
    {
        $rows = (new Collection(array_merge(
            $this->parsedFiles(),
            $this->parsedManagedFiles()->toList(),
        )))
            ->filter(fn(array $parsed): bool => $parsed['paths'] !== [])
            ->map(fn(array $parsed): string => '| ' . implode(', ', $parsed['paths']) . ' | ' . $this->relativePath($parsed['file']) . ' |')
            ->reduce(fn(string $carry, string $row): string => $carry === '' ? $row : $carry . "\n" . $row, '');

        $table = $rows === ''
            ? 'No rules recorded yet.'
            : "| Applies to | Rule file |\n| --- | --- |\n" . $rows;

        $body = "# Project Rules Index\n\n"
            . "Before planning or editing, find the row whose globs match the file's path and read that rule file.\n\n"
            . $table . "\n";

        $path = $this->indexPath();

        $this->ensureDirectoryExists($this->directory);
        file_put_contents($path, $body);

        return $path;
    }

    /**
     * Normalize a glob to a project-relative path.
     *
     * @param string $glob Glob pattern
     * @return string
     */
    public function normalizeGlob(string $glob): string
    {
        return $this->relativePath(trim($glob));
    }

    /**
     * Convert an absolute path to a project-relative path.
     *
     * @param string $path File path
     * @return string
     */
    public function relativePath(string $path): string
    {
        $path = str_replace(DS, '/', $path);
        $base = rtrim(str_replace(DS, '/', ProjectRoot::path()), '/') . '/';

        if (str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/');
        }

        return ltrim($path, '/');
    }

    /**
     * Resolve the target markdown file for a glob.
     *
     * @param string $glob Target glob
     * @return array{path: string, heading: string, parsed: array<string, mixed>|null}
     */
    protected function resolveTargetFile(string $glob): array
    {
        $allParsed = new Collection($this->parsedFiles());
        $area = $this->areaKey($glob);

        $existing = null;

        foreach ($allParsed as $parsed) {
            if (in_array($glob, $parsed['paths'], true)) {
                $existing = $parsed;
                break;
            }

            foreach ($parsed['paths'] as $path) {
                if ($this->areaKey($path) === $area) {
                    $existing = $parsed;
                    break 2;
                }
            }
        }

        if ($existing !== null) {
            return ['path' => $existing['file'], 'heading' => '', 'parsed' => $existing];
        }

        $path = $this->uniqueFilePath($glob, $allParsed);

        return [
            'path' => $path,
            'heading' => Inflector::humanize(basename($path, '.md')),
            'parsed' => null,
        ];
    }

    /**
     * Resolve a unique markdown file path for a glob.
     *
     * @param string $glob Target glob
     * @param \Cake\Collection\Collection $allParsed Parsed files
     * @return string
     */
    protected function uniqueFilePath(string $glob, Collection $allParsed): string
    {
        $segments = $this->meaningfulSegments($glob);
        $taken = $allParsed->map(fn(array $parsed): string => $parsed['file'])->toList();
        $reserved = $this->joinPaths($this->directory, self::INDEX_FILENAME);

        $candidates = [];
        $counter = count($segments);

        for ($take = 1; $take <= $counter; $take++) {
            $slug = $this->slugForSegments(array_slice($segments, -$take));

            if ($slug !== '') {
                $candidates[] = $slug;
            }
        }

        if ($candidates === []) {
            $candidates[] = 'general';
        }

        foreach ($candidates as $candidate) {
            $path = $this->joinPaths($this->directory, $candidate . '.md');

            if ($path !== $reserved && !in_array($path, $taken, true) && !is_file($path)) {
                return $path;
            }
        }

        $base = end($candidates);
        $suffix = 2;

        do {
            $path = $this->joinPaths($this->directory, $base . '-' . $suffix . '.md');
            $suffix++;
        } while (in_array($path, $taken, true) || is_file($path));

        return $path;
    }

    /**
     * Build an area key from a glob.
     *
     * @param string $glob Target glob
     * @return string
     */
    protected function areaKey(string $glob): string
    {
        return implode('/', $this->meaningfulSegments($glob));
    }

    /**
     * Extract meaningful path segments from a glob.
     *
     * @param string $glob Target glob
     * @return array<int, string>
     */
    protected function meaningfulSegments(string $glob): array
    {
        $segments = [];

        foreach (explode('/', $glob) as $segment) {
            if ($segment === '') {
                continue;
            }

            if (str_contains($segment, '*')) {
                continue;
            }

            if (str_contains($segment, '.')) {
                continue;
            }

            $segments[] = $segment;
        }

        return $segments;
    }

    /**
     * Build a slug from path segments.
     *
     * @param array<int, string> $segments Path segments
     * @return string
     */
    protected function slugForSegments(array $segments): string
    {
        return Text::slug(Inflector::underscore(implode(' ', $segments)));
    }

    /**
     * Create a new rule markdown file.
     *
     * @param string $path File path
     * @param string $heading File heading
     * @param array<int, string> $paths Frontmatter paths
     * @return void
     */
    protected function createFile(string $path, string $heading, array $paths): void
    {
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $this->renderFrontmatter($paths) . '# ' . $heading . "\n");
    }

    /**
     * Ensure a glob is present in a rule file frontmatter.
     *
     * @param string $path File path
     * @param string $glob Target glob
     * @param array<string, mixed>|null $parsed Parsed file data
     * @return void
     */
    protected function ensureGlobApplied(string $path, string $glob, ?array $parsed = null): void
    {
        if ($parsed === null) {
            try {
                $parsed = $this->parse($path);
            } catch (Throwable) {
                $raw = (string)preg_replace('/\R/', "\n", (string)file_get_contents($path));
                $parsed = ['paths' => [], 'body' => $raw];
            }
        }

        if (in_array($glob, $parsed['paths'], true)) {
            return;
        }

        $paths = [...$parsed['paths'], $glob];
        $frontmatter = $this->renderFrontmatter($paths);
        $body = (string)preg_replace('/^---\r?\n.*?\r?\n---\r?\n?/s', '', (string)$parsed['body']);

        file_put_contents($path, $frontmatter . ltrim($body, "\n"));
    }

    /**
     * Append a rule entry to a markdown file.
     *
     * @param string $path File path
     * @param string $title Entry title
     * @param string $note Entry note
     * @return void
     */
    protected function appendEntry(string $path, string $title, string $note): void
    {
        $contents = rtrim((string)file_get_contents($path), "\n");
        file_put_contents($path, $contents . "\n\n## " . $title . "\n" . $note . "\n");
    }

    /**
     * Render YAML frontmatter for rule paths.
     *
     * @param array<int, string> $paths Frontmatter paths
     * @return string
     */
    protected function renderFrontmatter(array $paths): string
    {
        $yaml = Yaml::dump(['paths' => array_values($paths)], 2, 2);

        return "---\n" . $yaml . "---\n\n";
    }

    /**
     * Parse a markdown rule file into frontmatter and body.
     *
     * @param string $path File path
     * @return array{paths: array<int, string>, body: string}
     */
    protected function parse(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return ['paths' => [], 'body' => ''];
        }

        return RuleFrontmatter::parse($contents);
    }

    /**
     * Return markdown rule files in the rules directory.
     *
     * @return array<int, string>
     */
    protected function files(): array
    {
        return $this->markdownFilesIn($this->directory, excludeIndex: true);
    }

    /**
     * Return parsed markdown rule files.
     *
     * @return array<int, array{file: string, paths: array<int, string>, body: string}>
     */
    protected function parsedFiles(): array
    {
        return $this->parseAll($this->files())->toList();
    }

    /**
     * Return the managed rules directory path.
     *
     * @return string
     */
    protected function managedDir(): string
    {
        return $this->joinPaths($this->directory, self::MANAGED_DIRNAME);
    }

    /**
     * Return the rules index path.
     *
     * @return string
     */
    protected function indexPath(): string
    {
        return $this->joinPaths($this->directory, self::INDEX_FILENAME);
    }

    /**
     * Return managed markdown rule files.
     *
     * @return array<int, string>
     */
    protected function managedFiles(): array
    {
        return $this->markdownFilesIn($this->managedDir());
    }

    /**
     * Return parsed managed markdown rule files.
     *
     * @return \Cake\Collection\CollectionInterface<int, array{file: string, paths: array<int, string>, body: string}>
     */
    protected function parsedManagedFiles(): CollectionInterface
    {
        return $this->parseAll($this->managedFiles());
    }

    /**
     * Return markdown files in a directory.
     *
     * @param string $dir Directory path
     * @param bool $excludeIndex Whether to exclude index.md
     * @return array<int, string>
     */
    protected function markdownFilesIn(string $dir, bool $excludeIndex = false): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($this->joinPaths($dir, '*.md')) ?: [];

        if (!$excludeIndex) {
            return $files;
        }

        $resolved = [];

        foreach ($files as $file) {
            if (basename($file) !== self::INDEX_FILENAME) {
                $resolved[] = $file;
            }
        }

        return $resolved;
    }

    /**
     * Parse a list of markdown rule files.
     *
     * @param array<int, string> $files File paths
     * @return \Cake\Collection\CollectionInterface<int, array{file: string, paths: array<int, string>, body: string}>
     */
    protected function parseAll(array $files): CollectionInterface
    {
        $parsed = [];

        foreach ($files as $file) {
            try {
                $parsed[] = ['file' => $file, ...$this->parse($file)];
            } catch (Throwable) {
                continue;
            }
        }

        /** @var \Cake\Collection\CollectionInterface<int, array{file: string, paths: array<int, string>, body: string}> $collection */
        $collection = new Collection($parsed);

        return $collection;
    }

    /**
     * Render a managed rule file body.
     *
     * @param array<int, string> $paths Path globs
     * @param string $title Rule title
     * @param string $content Rule content
     * @return string
     */
    protected function renderManagedFile(array $paths, string $title, string $content): string
    {
        $content = trim($content);
        $heading = preg_match('/^#+\s/', $content) === 1 ? '' : '# ' . $title . "\n\n";

        return $this->renderFrontmatter($paths) . $heading . $content . "\n";
    }

    /**
     * Ensure a directory exists.
     *
     * @param string $directory Directory path
     * @return void
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $directory Directory path
     * @return void
     */
    protected function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $this->joinPaths($directory, $item);

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    /**
     * Whether a directory has no children.
     *
     * @param string $directory Directory path
     * @return bool
     */
    protected function isEmptyDirectory(string $directory): bool
    {
        $items = scandir($directory);

        return $items !== false && count($items) <= 2;
    }

    /**
     * Join path segments using the platform directory separator.
     *
     * @param string ...$paths Path segments
     * @return string
     */
    protected function joinPaths(string ...$paths): string
    {
        return implode(DS, $paths);
    }
}
