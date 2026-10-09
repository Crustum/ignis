<?php
declare(strict_types=1);

namespace Crustum\Ignis\Test\Fixtures;

use Crustum\Ignis\Contracts\SupportsGuidelines;
use Crustum\Ignis\Contracts\SupportsSkills;

/**
 * Hand-written agent double for guideline and skill writer tests.
 */
class FakeAgent implements SupportsGuidelines, SupportsSkills
{
    /**
     * @param string $path Guidelines path and skills path
     * @param bool $frontmatter Whether guidelines require frontmatter
     */
    public function __construct(
        private readonly string $path = '',
        private readonly bool $frontmatter = false,
    ) {
    }

    /**
     * @return string
     */
    public function guidelinesPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function skillsPath(): string
    {
        return $this->path;
    }

    /**
     * @return bool
     */
    public function frontmatter(): bool
    {
        return $this->frontmatter;
    }

    /**
     * @param string $markdown Guidelines markdown
     * @return string
     */
    public function transformGuidelines(string $markdown): string
    {
        return $markdown;
    }
}
