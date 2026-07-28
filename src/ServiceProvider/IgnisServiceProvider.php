<?php
declare(strict_types=1);

namespace Crustum\Ignis\ServiceProvider;

use Cake\Console\CommandFactoryInterface;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\ServiceProvider;
use Crustum\Ignis\Command\AddSkillCommand;
use Crustum\Ignis\Command\ExecuteToolCommand;
use Crustum\Ignis\Command\InspectorCommand;
use Crustum\Ignis\Command\InstallCommand;
use Crustum\Ignis\Command\ListSkillCommand;
use Crustum\Ignis\Command\StartCommand;
use Crustum\Ignis\Command\UpdateCommand;
use Crustum\Ignis\IgnisManager;
use Crustum\Ignis\Install\AgentsDetector;
use Crustum\Ignis\Install\Detection\CommandDetectionStrategy;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Crustum\Ignis\Install\Detection\DirectoryDetectionStrategy;
use Crustum\Ignis\Install\Detection\FileDetectionStrategy;
use Crustum\Ignis\Install\GuidelineAssist;
use Crustum\Ignis\Install\GuidelineComposer;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Ignis\Install\SkillComposer;
use Crustum\Ignis\Mcp\Methods\CallToolWithExecutor;
use Crustum\Ignis\Mcp\ToolExecutor;
use Crustum\Ignis\Mcp\ToolRegistry;
use Crustum\Ignis\Mcp\Tools\ApplicationInfo;
use Crustum\Ignis\Mcp\Tools\RecordRule;
use Crustum\Ignis\Mcp\Tools\Tinker;
use Crustum\Ignis\Rules\RuleRepository;
use Crustum\Ignis\Support\Config;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Ignis\Tinker\TinkerExecutor;
use Crustum\Inspector\ProjectManager;

/**
 * Registers Ignis managers, composers, MCP tools, and console commands for method DI.
 */
class IgnisServiceProvider extends ServiceProvider
{
    /**
     * @var list<string>
     */
    protected array $provides = [
        IgnisManager::class,
        Config::class,
        RuleRepository::class,
        DetectionStrategyFactory::class,
        AgentsDetector::class,
        ProjectManager::class,
        GuidelineConfig::class,
        GuidelineComposer::class,
        SkillComposer::class,
        GuidelineAssist::class,
        ToolExecutor::class,
        CallToolWithExecutor::class,
        TinkerExecutor::class,
        InstallCommand::class,
        UpdateCommand::class,
        StartCommand::class,
        InspectorCommand::class,
        ExecuteToolCommand::class,
        ListSkillCommand::class,
        AddSkillCommand::class,
    ];

    /**
     * @inheritDoc
     */
    public function services(ContainerInterface $container): void
    {
        Configure::write('app.container', $container);

        $container->addShared(ContainerInterface::class, $container);
        $container->addShared(IgnisManager::class);
        $container->addShared(Config::class);
        $container->addShared(RuleRepository::class, fn(): RuleRepository => new RuleRepository(
            ProjectRoot::path() . DS . '.ai' . DS . 'rules',
        ));

        $container->add(DirectoryDetectionStrategy::class);
        $container->add(FileDetectionStrategy::class);
        $container->add(CommandDetectionStrategy::class);

        $container->addShared(DetectionStrategyFactory::class)
            ->addArgument(ContainerInterface::class);

        $container->addShared(AgentsDetector::class)
            ->addArgument(ContainerInterface::class)
            ->addArgument(IgnisManager::class);

        $container->addShared(ProjectManager::class, fn(): ProjectManager => new ProjectManager());
        $container->addShared(GuidelineConfig::class, fn(): GuidelineConfig => new GuidelineConfig());
        $container->addShared(GuidelineComposer::class)->addArgument(ProjectManager::class);
        $container->addShared(SkillComposer::class)
            ->addArgument(ProjectManager::class)
            ->addArgument(GuidelineConfig::class);
        $container->addShared(GuidelineAssist::class)
            ->addArgument(ProjectManager::class)
            ->addArgument(GuidelineConfig::class);

        foreach ((new IgnisManager())->getAgents() as $agentClass) {
            $container->add($agentClass)->addArgument(DetectionStrategyFactory::class);
        }

        self::registerMcpTools($container);
        self::registerMcpRuntimeServices($container);
        $this->registerCommands($container);

        $container->addShared(TinkerExecutor::class);
    }

    /**
     * Register MCP tools with required constructor dependencies.
     *
     * Called from {@see \Crustum\Ignis\IgnisPlugin::bootstrap()} so `tools/list`
     * does not fall back to `new Tool()` before this deferred provider loads.
     *
     * @param \Cake\Core\ContainerInterface $container Application container
     * @return void
     */
    public static function registerMcpTools(ContainerInterface $container): void
    {
        $container->add(ApplicationInfo::class)->addArgument(ProjectManager::class);
        $container->add(RecordRule::class)->addArgument(RuleRepository::class);
        $container->add(Tinker::class)->addArgument(TinkerExecutor::class);

        foreach (ToolRegistry::getAvailableTools() as $toolClass) {
            if ($toolClass === ApplicationInfo::class) {
                continue;
            }

            if ($toolClass === RecordRule::class) {
                continue;
            }

            if ($toolClass === Tinker::class) {
                continue;
            }

            $container->add($toolClass);
        }
    }

    /**
     * Register MCP runtime services used by the server method handlers.
     *
     * @param \Cake\Core\ContainerInterface $container Application container
     * @return void
     */
    public static function registerMcpRuntimeServices(ContainerInterface $container): void
    {
        $container->addShared(ToolExecutor::class);
        $container->addShared(CallToolWithExecutor::class)->addArgument(ToolExecutor::class);
    }

    /**
     * Register Ignis console command classes.
     *
     * @param \Cake\Core\ContainerInterface $container Application container
     * @return void
     */
    protected function registerCommands(ContainerInterface $container): void
    {
        $container->add(InstallCommand::class)
            ->addArgument(AgentsDetector::class)
            ->addArgument(Config::class)
            ->addArgument(GuidelineComposer::class)
            ->addArgument(SkillComposer::class)
            ->addArgument(RuleRepository::class)
            ->addArgument(ProjectManager::class)
            ->addArgument(CommandFactoryInterface::class);

        $container->add(UpdateCommand::class)
            ->addArgument(Config::class)
            ->addArgument(ProjectManager::class)
            ->addArgument(CommandFactoryInterface::class);

        $container->add(StartCommand::class)
            ->addArgument(CommandFactoryInterface::class);

        $container->add(InspectorCommand::class)
            ->addArgument(CommandFactoryInterface::class);

        $container->add(ExecuteToolCommand::class)
            ->addArgument(ContainerInterface::class);

        $container->add(ListSkillCommand::class)
            ->addArgument(SkillComposer::class);

        $container->add(AddSkillCommand::class)
            ->addArgument(CommandFactoryInterface::class);
    }
}
