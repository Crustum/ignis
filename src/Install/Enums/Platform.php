<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Enums;

/**
 * Host operating system identifiers for agent and tool detection.
 */
enum Platform: string
{
    case Darwin = 'darwin';
    case Linux = 'linux';
    case Windows = 'windows';

    /**
     * Resolve the current host platform.
     *
     * @return self
     */
    public static function current(): self
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => self::Windows,
            'Darwin' => self::Darwin,
            default => self::Linux,
        };
    }
}
