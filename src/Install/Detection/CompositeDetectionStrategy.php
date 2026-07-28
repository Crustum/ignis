<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Detection;

use Crustum\Ignis\Install\Contracts\DetectionStrategy;
use Crustum\Ignis\Install\Enums\Platform;

/**
 * Combines multiple detection strategies with logical OR semantics.
 */
class CompositeDetectionStrategy implements DetectionStrategy
{
    /**
     * Constructor.
     *
     * @param array<int, \Crustum\Ignis\Install\Contracts\DetectionStrategy> $strategies Nested strategies
     */
    public function __construct(private readonly array $strategies)
    {
    }

    /**
     * Detect if any nested strategy matches.
     *
     * @param array{command?: string, basePath?: string, files?: array<int, string>, paths?: array<int, string>} $config Detection configuration
     * @param \Crustum\Ignis\Install\Enums\Platform|null $platform Target platform
     * @return bool
     */
    public function detect(array $config, ?Platform $platform = null): bool
    {
        return array_any($this->strategies, fn(DetectionStrategy $strategy): bool => $strategy->detect($config, $platform));
    }
}
