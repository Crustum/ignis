<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Contracts;

use Crustum\Ignis\Install\Enums\Platform;

/**
 * Detects whether a tool or integration is present on the system or in a project.
 */
interface DetectionStrategy
{
    /**
     * Detect if the application is installed on the machine.
     *
     * @param array{command?: string, basePath?: string, files?: array<int, string>, paths?: array<int, string>} $config Detection configuration
     * @param \Crustum\Ignis\Install\Enums\Platform|null $platform Target platform
     * @return bool
     */
    public function detect(array $config, ?Platform $platform = null): bool;
}
