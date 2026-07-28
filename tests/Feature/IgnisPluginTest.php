<?php
declare(strict_types=1);

use Cake\Console\CommandCollection;
use Cake\Core\Configure;
use Cake\Core\Container;
use Cake\Core\ContainerApplicationInterface;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Core\PluginInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Event\EventManagerInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Log\Engine\FileLog;
use Cake\Log\Log;
use Cake\Routing\RouteBuilder;
use Crustum\Ignis\IgnisManager;
use Crustum\Ignis\IgnisPlugin;
use Crustum\Ignis\Support\BrowserWatcher;
use Crustum\Inspector\ProjectManager;
use Crustum\Mcp\Server\Registrar;

/**
 * Minimal host app stub for plugin bootstrap/services tests.
 */
class IgnisPluginTestApp implements PluginApplicationInterface, ContainerApplicationInterface
{
    private EventManagerInterface $eventManager;

    public function __construct(private Container $container)
    {
        $this->eventManager = new EventManager();
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function addPlugin(PluginInterface|string $name, array $config = []): static
    {
        return $this;
    }

    public function pluginBootstrap(): void
    {
    }

    public function pluginRoutes(RouteBuilder $routes): RouteBuilder
    {
        return $routes;
    }

    public function pluginMiddleware(MiddlewareQueue $middleware): MiddlewareQueue
    {
        return $middleware;
    }

    public function pluginConsole(CommandCollection $commands): CommandCollection
    {
        return $commands;
    }

    public function services(ContainerInterface $container): void
    {
    }

    public function getEventManager(): EventManagerInterface
    {
        return $this->eventManager;
    }

    public function setEventManager(EventManagerInterface $eventManager)
    {
        $this->eventManager = $eventManager;

        return $this;
    }

    public function dispatchEvent(string $name, array $data = [], ?object $subject = null): EventInterface
    {
        return $this->eventManager->dispatch($name, $data, $subject);
    }
}

/**
 * Mirror plugin `config/bootstrap.php` browser log channel setup.
 *
 * @return void
 */
function configureIgnisBrowserLogChannel(): void
{
    if (BrowserWatcher::isEnabled() && Log::getConfig('browser') === null) {
        Log::setConfig('browser', [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'browser',
            'levels' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
            'scopes' => ['browser'],
        ]);
    }
}

/**
 * Boot Ignis plugin services the way a host app would after Configure is set.
 *
 * @return array{0: \Crustum\Ignis\IgnisPlugin, 1: \Cake\Core\Container}
 */
function bootIgnisPlugin(): array
{
    Registrar::setInstance(new Registrar());

    $container = new Container();
    $app = new IgnisPluginTestApp($container);
    $plugin = new IgnisPlugin([
        'path' => ROOT . DS,
    ]);

    $plugin->bootstrap($app);
    $plugin->services($container);
    configureIgnisBrowserLogChannel();

    return [$plugin, $container];
}

beforeEach(function (): void {
    putenv('IGNIS_ENABLED');
    putenv('IGNIS_FORCE_ENABLE');
    Configure::write('Ignis.enabled', true);
    Configure::write('Ignis.force_enable', false);
    Configure::write('Ignis.browser_logs_watcher', true);
    Configure::write('debug', true);

    if (Log::getConfig('browser') !== null) {
        Log::drop('browser');
    }

    Registrar::setInstance(new Registrar());
});

afterEach(function (): void {
    putenv('IGNIS_ENABLED');
    putenv('IGNIS_FORCE_ENABLE');
    Configure::write('Ignis.enabled', true);
    Configure::write('Ignis.force_enable', false);
    Configure::write('Ignis.browser_logs_watcher', true);
    Configure::write('debug', true);

    if (Log::getConfig('browser') !== null) {
        Log::drop('browser');
    }

    Registrar::setInstance(null);
});

describe('Ignis.enabled configuration', function (): void {
    it('does not register MCP when disabled', function (): void {
        putenv('IGNIS_ENABLED=0');
        Configure::write('Ignis.enabled', false);
        Configure::write('debug', true);

        [$plugin] = bootIgnisPlugin();
        $commands = $plugin->console(new CommandCollection());

        expect(Registrar::getInstance()->getLocalServer('cake-ignis'))->toBeNull()
            ->and(Log::getConfig('browser'))->toBeNull()
            ->and($commands->has('ignis install'))->toBeTrue();
    });

    it('boots Ignis when enabled and debug is on', function (): void {
        Configure::write('Ignis.enabled', true);
        Configure::write('debug', true);

        [, $container] = bootIgnisPlugin();

        expect($container->has(ProjectManager::class))->toBeTrue()
            ->and(Registrar::getInstance()->getLocalServer('cake-ignis'))->not->toBeNull()
            ->and(Log::getConfig('browser'))->not->toBeNull();
    });

    it('does not override an existing browser log channel', function (): void {
        Configure::write('Ignis.enabled', true);
        Configure::write('debug', true);

        Log::setConfig('browser', [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'custom-browser',
            'levels' => ['error'],
            'scopes' => ['browser'],
        ]);

        bootIgnisPlugin();

        expect(Log::getConfig('browser'))->toBeArray()
            ->and(Log::getConfig('browser')['file'])->toBe('custom-browser');
    });
});

describe('environment restrictions', function (): void {
    it('does not configure browser log when debug is false even when enabled', function (): void {
        Configure::write('Ignis.enabled', true);
        Configure::write('Ignis.force_enable', false);
        Configure::write('debug', false);

        bootIgnisPlugin();

        expect(Log::getConfig('browser'))->toBeNull()
            ->and(BrowserWatcher::isEnabled())->toBeFalse()
            ->and(Registrar::getInstance()->getLocalServer('cake-ignis'))->toBeNull();
    });

    it('does not register MCP when debug is false', function (): void {
        Configure::write('Ignis.enabled', true);
        Configure::write('Ignis.force_enable', false);
        Configure::write('debug', false);

        bootIgnisPlugin();

        expect(Registrar::getInstance()->getLocalServer('cake-ignis'))->toBeNull();
    });

    it('registers MCP when debug is false but force_enable is true', function (): void {
        putenv('IGNIS_FORCE_ENABLE=1');
        Configure::write('Ignis.enabled', true);
        Configure::write('Ignis.force_enable', true);
        Configure::write('debug', false);

        bootIgnisPlugin();

        expect(Registrar::getInstance()->getLocalServer('cake-ignis'))->not->toBeNull()
            ->and(BrowserWatcher::isEnabled())->toBeTrue()
            ->and(Log::getConfig('browser'))->not->toBeNull();
    });

    it('does not register MCP when force_enable is true but enabled is false', function (): void {
        putenv('IGNIS_ENABLED=0');
        putenv('IGNIS_FORCE_ENABLE=1');
        Configure::write('Ignis.enabled', false);
        Configure::write('Ignis.force_enable', true);
        Configure::write('debug', false);

        bootIgnisPlugin();

        expect(Registrar::getInstance()->getLocalServer('cake-ignis'))->toBeNull()
            ->and(BrowserWatcher::isEnabled())->toBeFalse();
    });

    describe('debug flag', function (): void {
        it('does not configure browser log when debug is false', function (): void {
            Configure::write('Ignis.enabled', true);
            Configure::write('debug', false);

            bootIgnisPlugin();

            expect(Log::getConfig('browser'))->toBeNull();
        });

        it('configures browser log when debug is true', function (): void {
            Configure::write('Ignis.enabled', true);
            Configure::write('debug', true);

            bootIgnisPlugin();

            expect(Log::getConfig('browser'))->not->toBeNull();
        });
    });
});

describe('IgnisManager registration', function (): void {
    beforeEach(function (): void {
        Configure::write('Ignis.enabled', true);
        Configure::write('debug', true);
        [, $this->container] = bootIgnisPlugin();
    });

    it('registers IgnisManager in the container', function (): void {
        expect($this->container->has(IgnisManager::class))->toBeTrue()
            ->and($this->container->get(IgnisManager::class))->toBeInstanceOf(IgnisManager::class);
    });

    it('registers IgnisManager as a shared instance', function (): void {
        $instance1 = $this->container->get(IgnisManager::class);
        $instance2 = $this->container->get(IgnisManager::class);

        expect($instance1)->toBe($instance2);
    });
});
