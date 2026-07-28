<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

/**
 * Value object representing a discovered Ignis skill.
 */
class Skill
{
    /**
     * Constructor.
     *
     * @param string $name Skill name
     * @param string $package Source package name
     * @param string $path Absolute skill directory path
     * @param string $description Skill description
     * @param bool $custom Whether the skill is user-authored
     */
    public function __construct(
        public string $name,
        public string $package,
        public string $path,
        public string $description,
        public bool $custom = false,
    ) {
    }

    /**
     * Return a copy of the skill with an updated custom flag.
     *
     * @param bool $custom Whether the skill is user-authored
     * @return self
     */
    public function withCustom(bool $custom): self
    {
        return new self(
            name: $this->name,
            package: $this->package,
            path: $this->path,
            description: $this->description,
            custom: $custom,
        );
    }

    /**
     * Return the display label for console output.
     *
     * @return string
     */
    public function displayName(): string
    {
        return $this->custom
            ? '.ai/' . $this->name . '*'
            : $this->name;
    }
}
