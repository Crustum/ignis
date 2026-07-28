<?php
declare(strict_types=1);

namespace Crustum\Ignis\Contracts;

/**
 * Contract for agents that support agent skills.
 */
interface SupportsSkills
{
    /**
     * Get the file path where agent skills should be written.
     *
     * @return string
     */
    public function skillsPath(): string;
}
