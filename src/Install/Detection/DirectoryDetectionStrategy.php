<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Detection;

use Crustum\Ignis\Install\Contracts\DetectionStrategy;
use Crustum\Ignis\Install\Enums\Platform;

/**
 * Detects installation by checking for configured directories or glob patterns.
 */
class DirectoryDetectionStrategy implements DetectionStrategy
{
    /**
     * Detect if configured directories exist.
     *
     * @param array{command?: string, basePath?: string, files?: array<int, string>, paths?: array<int, string>} $config Detection configuration
     * @param \Crustum\Ignis\Install\Enums\Platform|null $platform Target platform
     * @return bool
     */
    public function detect(array $config, ?Platform $platform = null): bool
    {
        if (!isset($config['paths'])) {
            return false;
        }

        $basePath = $config['basePath'] ?? '';

        foreach ($config['paths'] as $path) {
            $expandedPath = $this->expandPath($path, $platform);

            if ($basePath !== '' && !$this->isAbsolutePath($expandedPath)) {
                $expandedPath = $basePath . DIRECTORY_SEPARATOR . $expandedPath;
            }

            if (str_contains($expandedPath, '*')) {
                $matches = glob($expandedPath, GLOB_ONLYDIR);

                if (!empty($matches)) {
                    return true;
                }
            } elseif (is_dir($expandedPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Expand environment variables and home directory shortcuts in a path.
     *
     * @param string $path Raw path
     * @param \Crustum\Ignis\Install\Enums\Platform|null $platform Target platform
     * @return string
     */
    protected function expandPath(string $path, ?Platform $platform = null): string
    {
        if ($platform === Platform::Windows) {
            $expanded = preg_replace_callback(
                '/%([^%]+)%/',
                static fn(array $matches): string => getenv($matches[1]) ?: $matches[0],
                $path,
            );

            return is_string($expanded) ? $expanded : $path;
        }

        if (str_starts_with($path, '~')) {
            $home = getenv('HOME');

            if ($home !== false && $home !== '') {
                return str_replace('~', $home, $path);
            }
        }

        return $path;
    }

    /**
     * Whether a path is absolute on the current platform.
     *
     * @param string $path Path to inspect
     * @return bool
     */
    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || (strlen($path) > 1 && $path[1] === ':');
    }
}
