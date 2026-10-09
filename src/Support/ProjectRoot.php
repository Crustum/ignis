<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

/**
 * Resolves the active project root for Ignis support helpers.
 */
class ProjectRoot
{
    /**
     * Optional test or alternate project root override (install write target).
     *
     * @var string|null
     */
    protected static ?string $override = null;

    /**
     * Return the active project root path (write / install target).
     *
     * @return string
     */
    public static function path(): string
    {
        return self::$override ?? ROOT;
    }

    /**
     * Return the CakePHP application ROOT (bin/cake, vendor) — never the --path target.
     *
     * @return string
     */
    public static function applicationPath(): string
    {
        return ROOT;
    }

    /**
     * Return the current override, or null when using application ROOT.
     *
     * @return string|null
     */
    public static function override(): ?string
    {
        return self::$override;
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
