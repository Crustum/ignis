<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

use Cake\Collection\Collection;

/**
 * Records skill files whose frontmatter could not be parsed.
 */
class SkillParseFailures
{
    /**
     * Failure reasons keyed by skill file path.
     *
     * @var array<string, string>
     */
    protected array $failures = [];

    /**
     * Record a skill file with unusable frontmatter.
     *
     * @param string $path Skill file path
     * @param string $reason Failure reason
     * @return void
     */
    public function record(string $path, string $reason = ''): void
    {
        $this->failures[$path] = $reason;
    }

    /**
     * Whether any failures were recorded.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->failures === [];
    }

    /**
     * Recorded failures with skill names derived from directory names.
     *
     * @return array<int, array{name: string, path: string, reason: string}>
     */
    public function all(): array
    {
        /** @var \Cake\Collection\CollectionInterface<int, array{name: string, path: string, reason: string}> $entries */
        $entries = (new Collection($this->failures))
            ->map(static fn(string $reason, string $path): array => [
                'name' => basename(dirname($path)),
                'path' => $path,
                'reason' => $reason,
            ]);

        return $entries->toList();
    }

    /**
     * Distinct skill names with recorded failures.
     *
     * @return array<int, string>
     */
    public function skillNames(): array
    {
        return (new Collection($this->all()))
            ->extract('name')
            ->unique()
            ->toList();
    }

    /**
     * Discard all recorded failures.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->failures = [];
    }
}
