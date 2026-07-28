<?php
declare(strict_types=1);

namespace Crustum\Ignis\Contracts;

/**
 * Contract for AI coding assistants that receive guidelines.
 */
interface SupportsGuidelines
{
    /**
     * Get the file path where AI guidelines should be written.
     *
     * @return string
     */
    public function guidelinesPath(): string;

    /**
     * Determine if the guideline file requires frontmatter.
     *
     * @return bool
     */
    public function frontmatter(): bool;

    /**
     * Transform the generated guidelines markdown.
     *
     * @param string $markdown Generated markdown
     * @return string
     */
    public function transformGuidelines(string $markdown): string;
}
