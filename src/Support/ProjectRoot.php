<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

/**
 * Resolves the active project root for Ignis support helpers.
 */
class ProjectRoot
{
    /**
     * Optional test or alternate project root override.
     *
     * @var string|null
     */
    protected static ?string $override = null;

    /**
     * Return the active project root path.
     *
     * @return string
     */
    public static function path(): string
    {
        return self::$override ?? ROOT;
    }

    /**
     * Override the active project root path.
     *
     * @param string|null $path Project root path
     * @return void
     */
    public static function set(?string $path): void
    {
        self::$override = $path;
    }
}
