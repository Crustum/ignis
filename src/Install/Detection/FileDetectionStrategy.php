<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Detection;

use Crustum\Ignis\Install\Contracts\DetectionStrategy;
use Crustum\Ignis\Install\Enums\Platform;

/**
 * Detects installation by checking for configured files under a base path.
 */
class FileDetectionStrategy implements DetectionStrategy
{
    /**
     * Detect if configured files exist under the base path.
     *
     * @param array{command?: string, basePath?: string, files?: array<int, string>, paths?: array<int, string>} $config Detection configuration
     * @param \Crustum\Ignis\Install\Enums\Platform|null $platform Target platform
     * @return bool
     */
    public function detect(array $config, ?Platform $platform = null): bool
    {
        $basePath = $config['basePath'] ?? getcwd();

        if (!isset($config['files'])) {
            return false;
        }

        return array_any($config['files'], fn(string $file): bool => file_exists($basePath . DIRECTORY_SEPARATOR . $file));
    }
}
