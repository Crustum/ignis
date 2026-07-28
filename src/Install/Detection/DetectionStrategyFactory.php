<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Detection;

use Cake\Collection\Collection;
use Crustum\Ignis\Install\Contracts\DetectionStrategy;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

/**
 * Builds detection strategy instances from configuration shapes.
 */
class DetectionStrategyFactory
{
    protected const TYPE_DIRECTORY = 'directory';

    protected const TYPE_COMMAND = 'command';

    protected const TYPE_FILE = 'file';

    /**
     * Constructor.
     *
     * @param \Psr\Container\ContainerInterface $container Service container
     */
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * Create a detection strategy for the given type.
     *
     * @param array<int, string|array<int, string>>|string $type Strategy type or composite definition
     * @param array{command?: string, basePath?: string, files?: array<int, string>, paths?: array<int, string>} $config Detection configuration
     * @return \Crustum\Ignis\Install\Contracts\DetectionStrategy
     */
    public function make(string|array $type, array $config = []): DetectionStrategy
    {
        if (is_array($type)) {
            return new CompositeDetectionStrategy(
                array_map(
                    fn(string|array $singleType): DetectionStrategy => $this->make($singleType, $config),
                    $type,
                ),
            );
        }

        return match ($type) {
            self::TYPE_DIRECTORY => $this->container->get(DirectoryDetectionStrategy::class),
            self::TYPE_COMMAND => $this->container->get(CommandDetectionStrategy::class),
            self::TYPE_FILE => $this->container->get(FileDetectionStrategy::class),
            default => throw new InvalidArgumentException("Unknown detection type: {$type}"),
        };
    }

    /**
     * Infer the strategy type from configuration keys and build it.
     *
     * @param array{command?: string, basePath?: string, files?: array<int, string>, paths?: array<int, string>} $config Detection configuration
     * @return \Crustum\Ignis\Install\Contracts\DetectionStrategy
     */
    public function makeFromConfig(array $config): DetectionStrategy
    {
        $type = $this->inferTypeFromConfig($config);

        return $this->make($type, $config);
    }

    /**
     * Infer strategy type from configuration keys.
     *
     * @param array{command?: string, basePath?: string, files?: array<int, string>, paths?: array<int, string>} $config Detection configuration
     * @return array<int, string>|string
     */
    protected function inferTypeFromConfig(array $config): string|array
    {
        $typeMap = [
            'files' => self::TYPE_FILE,
            'paths' => self::TYPE_DIRECTORY,
            'command' => self::TYPE_COMMAND,
        ];

        $types = (new Collection($typeMap))
            ->filter(fn(string $type, string $key): bool => array_key_exists($key, $config))
            ->toList();

        if ($types === []) {
            throw new InvalidArgumentException(
                'Cannot infer detection type from config keys. Expected one of: '
                . implode(', ', array_keys($typeMap)),
            );
        }

        return count($types) > 1 ? $types : $types[0];
    }
}
