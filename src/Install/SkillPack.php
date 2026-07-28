<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

/**
 * Value object for an Ignis pack provider package.
 */
class SkillPack
{
    /**
     * Constructor.
     *
     * @param string $name Composer package name of the pack
     * @param string $path Absolute path to `resources/ignis/pack`
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
    ) {
    }
}
