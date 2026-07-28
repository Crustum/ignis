<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Cake\Collection\Collection;
use Cake\Utility\Inflector;
use Cake\Utility\Text;

/**
 * Composes path-scoped guideline blocks into managed rule files.
 */
class RuleComposer
{
    /**
     * Cached scoped rules keyed by guideline key and block index.
     *
     * @var \Cake\Collection\Collection<string, array{paths: array<int, string>, content: string}>|null
     */
    protected ?Collection $rules = null;

    /**
     * Constructor.
     *
     * @param \Crustum\Ignis\Install\GuidelineComposer $guidelines Guideline composer
     */
    public function __construct(protected GuidelineComposer $guidelines)
    {
    }

    /**
     * One entry per rendered `@scoped` block, keyed by guideline key and block index.
     *
     * @return \Cake\Collection\Collection<string, array{paths: array<int, string>, content: string}>
     */
    public function rules(): Collection
    {
        if ($this->rules instanceof Collection) {
            return $this->rules;
        }

        $rules = [];

        foreach ($this->guidelines->resolvedGuidelines() as $key => $guideline) {
            foreach ($guideline['scoped'] as $index => $block) {
                if ($block['paths'] === []) {
                    continue;
                }

                if ($block['body'] === '') {
                    continue;
                }

                $rules[$key . '#' . $index] = [
                    'paths' => $block['paths'],
                    'content' => $block['body'],
                ];
            }
        }

        /** @var \Cake\Collection\Collection<string, array{paths: array<int, string>, content: string}> $collection */
        $collection = new Collection($rules);

        return $this->rules = $collection;
    }

    /**
     * Merge rules that apply to the exact same globs into a single managed rule file.
     *
     * @return \Cake\Collection\Collection<string, array{paths: array<int, string>, title: string, content: string}>
     */
    public function composeManaged(): Collection
    {
        $slugs = [];
        $grouped = [];

        foreach ($this->rules() as $rule) {
            $key = $this->pathsKey($rule['paths']);
            $grouped[$key][] = $rule;
        }

        $composed = [];

        foreach ($grouped as $group) {
            $paths = $this->normalizePaths($group[0]['paths']);
            $contentParts = [];

            foreach ($group as $rule) {
                $trimmed = trim($rule['content']);
                if ($trimmed !== '') {
                    $contentParts[] = $trimmed;
                }
            }

            $content = MarkdownFormatter::format(implode("\n\n", $contentParts));
            $slug = $this->uniqueSlug($paths, $slugs);
            $slugs[] = $slug;

            $composed[$slug] = [
                'paths' => $paths,
                'title' => $this->titleFor($content, $slug),
                'content' => $content,
            ];
        }

        return new Collection($composed);
    }

    /**
     * Build a stable key for a set of path globs.
     *
     * @param array<int, string> $paths Path globs
     * @return string
     */
    protected function pathsKey(array $paths): string
    {
        return implode('|', $this->normalizePaths($paths));
    }

    /**
     * Normalize path globs for grouping and output.
     *
     * @param array<int, string> $paths Path globs
     * @return array<int, string>
     */
    protected function normalizePaths(array $paths): array
    {
        $normalized = array_values(array_unique(array_map(
            trim(...),
            $paths,
        )));
        sort($normalized);

        return $normalized;
    }

    /**
     * Derive a rule title from markdown content or a fallback slug.
     *
     * @param string $content Rule markdown content
     * @param string $fallbackSlug Fallback slug
     * @return string
     */
    protected function titleFor(string $content, string $fallbackSlug): string
    {
        $firstLine = trim(strtok(trim($content), "\n") ?: '');

        if (preg_match('/^#+\s+(?<heading>.+)$/', $firstLine, $matches) === 1) {
            return trim($matches['heading']);
        }

        return Inflector::humanize(str_replace('-', ' ', $fallbackSlug));
    }

    /**
     * Build a unique filename slug from path globs.
     *
     * @param array<int, string> $paths Path globs
     * @param array<int, string> $taken Already used slugs
     * @return string
     */
    protected function uniqueSlug(array $paths, array $taken): string
    {
        $lastSegments = [];

        foreach ($paths as $glob) {
            $segment = null;
            foreach (explode('/', $glob) as $part) {
                if ($part === '') {
                    continue;
                }

                if (str_contains($part, '*')) {
                    continue;
                }

                if (str_contains($part, '.')) {
                    continue;
                }

                $segment = $part;
            }

            if ($segment !== null) {
                $lastSegments[] = $segment;
            }
        }

        $lastSegments = array_values(array_unique($lastSegments));
        sort($lastSegments);

        $base = $lastSegments === []
            ? 'rules'
            : Text::slug(Inflector::underscore(implode(' ', $lastSegments)));

        if ($base === '') {
            $base = 'rules';
        }

        if (!in_array($base, $taken, true)) {
            return $base;
        }

        $suffix = 2;

        while (in_array($base . '-' . $suffix, $taken, true)) {
            $suffix++;
        }

        return $base . '-' . $suffix;
    }
}
