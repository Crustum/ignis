<?php
declare(strict_types=1);

namespace Crustum\Ignis;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\Plugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Log\Engine\FileLog;
use Cake\Log\Log;
use Crustum\Ignis\Command\AddSkillCommand;
use Crustum\Ignis\Command\ExecuteToolCommand;
use Crustum\Ignis\Command\InspectorCommand;
use Crustum\Ignis\Command\InstallCommand;
use Crustum\Ignis\Command\ListSkillCommand;
use Crustum\Ignis\Command\RulesIndexCommand;
use Crustum\Ignis\Command\StartCommand;
use Crustum\Ignis\Command\Themes\MultiSelectPromptRenderer;
use Crustum\Ignis\Command\UpdateCommand;
use Crustum\Ignis\Mcp\Ignis as McpIgnis;
use Crustum\Ignis\Middleware\InjectIgnis;
use Crustum\Ignis\ServiceProvider\IgnisServiceProvider;
use Crustum\Ignis\Support\BrowserWatcher;
use Crustum\Ignis\Support\IgnisRuntime;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\PluginManifest\Manifest\ManifestInterface;
use Crustum\PluginManifest\Manifest\ManifestTrait;
use Crustum\PluginManifest\Manifest\Tag;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Prompt;
use Override;

/**
 * Ignis plugin for AI guidelines, skills, and MCP tooling in CakePHP projects.
 *
 * @uses \Crustum\PluginManifest\Manifest\ManifestTrait
 */
class IgnisPlugin extends BasePlugin implements ManifestInterface
{
    use ManifestTrait;

    /**
     * Plugin name.
     *
     * @var string|null
     */
    protected ?string $name = 'Ignis';

    /**
     * Enable plugin bootstrap.
     *
     * @var bool
     */
    protected bool $bootstrapEnabled = true;

    /**
     * Enable console commands.
     *
     * @var bool
     */
    protected bool $consoleEnabled = true;

    /**
     * Enable HTTP middleware.
     *
     * @var bool
     */
    protected bool $middlewareEnabled = true;

    /**
     * Enable plugin routes.
     *
     * @var bool
     */
    protected bool $routesEnabled = true;

    /**
     * Load plugin configuration.
     *
     * @param \Cake\Core\PluginApplicationInterface $app Application instance
     * @return void
     */
    #[Override]
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        $this->loadPromptsPlugin($app);

        if (!Configure::check('Ignis')) {
            if (file_exists(CONFIG . 'ignis.php')) {
                Configure::load('ignis', 'default');
            } elseif (file_exists($this->getConfigPath() . 'ignis.php')) {
                Configure::load('Crustum/Ignis.ignis', 'default', false);
            }
        }

        if (IgnisRuntime::shouldRun()) {
            Registrar::getInstance()->local('cake-ignis', McpIgnis::class);
        }

        $this->registerBrowserLogChannel();

        $app->getEventManager()->on('Application.buildContainer', function ($event): void {
            $container = $event->getData('container');
            IgnisServiceProvider::registerMcpRuntimeServices($container);
            IgnisServiceProvider::registerMcpTools($container);
            ContainerRegistry::setInstance($container);
        });
    }

    /**
     * Hard-load Crustum/Prompts so console helpers are available without host config.
     *
     * @param \Cake\Core\PluginApplicationInterface $app Application instance
     * @return void
     */
    protected function loadPromptsPlugin(PluginApplicationInterface $app): void
    {
        if (!Plugin::isLoaded('Prompts') && !Plugin::isLoaded('Crustum/Prompts')) {
            $app->addPlugin('Crustum/Prompts');
        }

        $this->registerCompatiblePromptTheme();
    }

    /**
     * Register the browser log channel used by the browser logs MCP tool.
     *
     * Only when the watcher is active and no browser channel exists yet, so a
     * host application may keep its own browser channel configuration.
     *
     * @return void
     */
    protected function registerBrowserLogChannel(): void
    {
        if (!BrowserWatcher::isEnabled() || Log::getConfig('browser') !== null) {
            return;
        }

        Log::setConfig('browser', [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'browser',
            'levels' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
            'scopes' => ['browser'],
        ]);
    }

    /**
     * Register an Ignis Prompts theme that avoids MultiSelect square glyphs.
     *
     * @return void
     */
    protected function registerCompatiblePromptTheme(): void
    {
        Prompt::addTheme('ignis', [
            MultiSelectPrompt::class => MultiSelectPromptRenderer::class,
        ]);
        Prompt::theme('ignis');
    }

    /**
     * Register Ignis HTTP middleware.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue Middleware queue
     * @return \Cake\Http\MiddlewareQueue
     */
    #[Override]
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        if (!BrowserWatcher::isEnabled()) {
            return $middlewareQueue;
        }

        return $middlewareQueue->add(new InjectIgnis());
    }

    /**
     * Attach Ignis DI via Cake ServiceProvider.
     *
     * @param \Cake\Core\ContainerInterface $container Application container
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->addServiceProvider(new IgnisServiceProvider());
    }

    /**
     * Register plugin console commands.
     *
     * @param \Cake\Console\CommandCollection $commands Command collection
     * @return \Cake\Console\CommandCollection
     */
    #[Override]
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);

        return $commands->addMany([
            'ignis install' => InstallCommand::class,
            'ignis update' => UpdateCommand::class,
            'ignis mcp' => StartCommand::class,
            'ignis inspector' => InspectorCommand::class,
            'ignis execute-tool' => ExecuteToolCommand::class,
            'ignis add-skill' => AddSkillCommand::class,
            'ignis list-skills' => ListSkillCommand::class,
            'ignis index-rules' => RulesIndexCommand::class,
        ]);
    }

    /**
     * Get the manifest for the plugin.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__);

        return array_merge(
            static::manifestConfig(
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ignis.php',
                CONFIG . 'ignis.php',
                false,
            ),
            static::manifestBootstrapAppend(
                "if (file_exists(CONFIG . 'ignis.php')) {\n    Configure::load('ignis', 'default');\n}",
                '// Ignis Plugin Configuration',
            ),
            static::manifestDependencies([
                'Crustum/Mcp' => [
                    'required' => true,
                    'tags' => [Tag::CONFIG, Tag::BOOTSTRAP],
                    'reason' => 'MCP runtime for cake-ignis (Registrar, ContainerInvoker, mcp start)',
                ],
            ]),
            static::manifestStarRepo('Crustum/ignis'),
        );
    }
}
