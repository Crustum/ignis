<?php
declare(strict_types=1);

use Cake\Core\Container;
use Crustum\Ignis\IgnisManager;
use Crustum\Ignis\Install\Detection\CommandDetectionStrategy;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Crustum\Ignis\Install\Detection\DirectoryDetectionStrategy;
use Crustum\Ignis\Install\Detection\FileDetectionStrategy;
use Psr\Container\ContainerInterface;

/**
 * Create a new Cake DI container for an isolated test.
 *
 * @return \Cake\Core\Container
 */
function freshTestContainer(): Container
{
    return new Container();
}

/**
 * Register Ignis detection strategy services on a container.
 *
 * @param \Cake\Core\Container $container DI container
 * @return void
 */
function registerDetectionStrategies(Container $container): void
{
    $container->add(DirectoryDetectionStrategy::class, fn (): DirectoryDetectionStrategy => new DirectoryDetectionStrategy());
    $container->add(FileDetectionStrategy::class, fn (): FileDetectionStrategy => new FileDetectionStrategy());
    $container->add(CommandDetectionStrategy::class, fn (): CommandDetectionStrategy => new CommandDetectionStrategy());
}

/**
 * Register agent installers and detection services for install command tests.
 *
 * @param \Cake\Core\Container $container DI container
 * @return void
 */
function registerTestAgents(Container $container): void
{
    registerDetectionStrategies($container);

    $container->addShared(IgnisManager::class);
    $container->addShared(ContainerInterface::class, $container, true);
    $container->addShared(DetectionStrategyFactory::class)
        ->addArgument(ContainerInterface::class);

    foreach ((new IgnisManager())->getAgents() as $agentClass) {
        $container->add($agentClass)->addArgument(DetectionStrategyFactory::class);
    }
}

/**
 * Bind a shared instance into the container, replacing any existing binding.
 *
 * @param \Cake\Core\Container $container DI container
 * @param string $abstract Service identifier
 * @param mixed $instance Instance to bind
 * @return void
 */
function bindInstance(Container $container, string $abstract, mixed $instance): void
{
    $container->addShared($abstract, $instance, true);
}
