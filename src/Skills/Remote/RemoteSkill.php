<?php
declare(strict_types=1);

namespace Crustum\Ignis\Skills\Remote;

/**
 * A skill discovered in a remote GitHub repository.
 */
class RemoteSkill
{
    /**
     * Create a remote skill descriptor.
     *
     * @param string $name Skill name
     * @param string $repo GitHub owner/repository name
     * @param string $path Repository-relative skill path
     */
    public function __construct(
        public string $name,
        public string $repo,
        public string $path,
    ) {
    }
}
