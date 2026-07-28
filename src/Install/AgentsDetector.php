<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Cake\Collection\Collection;
use Crustum\Ignis\IgnisManager;
use Crustum\Ignis\Install\Agents\Agent;
use Crustum\Ignis\Install\Enums\Platform;
use Psr\Container\ContainerInterface;

/**
 * Detects installed AI coding agents on the system and in a project.
 */
class AgentsDetector
{
    /**
     * Constructor.
     *
     * @param \Psr\Container\ContainerInterface $container Service container
     * @param \Crustum\Ignis\IgnisManager $ignisManager Agent registry
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly IgnisManager $ignisManager,
    ) {
    }

    /**
     * Detect agents installed on the current platform.
     *
     * @return array<int, string>
     */
    public function discoverSystemInstalledAgents(): array
    {
        $platform = Platform::current();
        $names = [];

        foreach ($this->getAgents() as $program) {
            if ($program->detectOnSystem($platform)) {
                $names[] = $program->name();
            }
        }

        return $names;
    }

    /**
     * Detect agents configured in the current project.
     *
     * @param string $basePath Project root path
     * @return array<int, string>
     */
    public function discoverProjectInstalledAgents(string $basePath): array
    {
        $names = [];

        foreach ($this->getAgents() as $program) {
            if ($program->detectInProject($basePath)) {
                $names[] = $program->name();
            }
        }

        return $names;
    }

    /**
     * Return all registered agent instances.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Agents\Agent>
     */
    public function getAgents(): Collection
    {
        return (new Collection($this->ignisManager->getAgents()))
            ->map(fn(string $className): Agent => $this->container->get($className));
    }
}
