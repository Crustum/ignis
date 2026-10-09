<?php
declare(strict_types=1);

use Cake\Collection\Collection;
use Crustum\Ignis\Command\InstallCommand;
use Crustum\Ignis\IgnisManager;
use Crustum\Ignis\Install\AgentsDetector;
use Crustum\Ignis\Install\GuidelineComposer;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Ignis\Install\SkillComposer;
use Crustum\Ignis\Rules\RuleRepository;
use Crustum\Ignis\Support\Config;
use Crustum\Inspector\ProjectManager;
use JMac\Testing\Double;

beforeEach(function (): void {
    useTestApp();
    (new Config())->flush();
});

afterEach(function (): void {
    (new Config())->flush();
    resetTestApp();
});

/**
 * Test double that exposes buildGuidelineConfig with controlled flags.
 */
class BuildGuidelineConfigInstallCommand extends InstallCommand
{
    public bool $explicitFlagMode = false;

    #[\Override]
    protected function isExplicitFlagMode(): bool
    {
        return $this->explicitFlagMode;
    }

    public function callBuildGuidelineConfig(): GuidelineConfig
    {
        return $this->buildGuidelineConfig();
    }

    public function setSelectedIgnisFeaturesForTest(Collection $features): void
    {
        $this->selectedIgnisFeatures = $features;
    }

    public function setSelectedThirdPartyPackagesForTest(Collection $packages): void
    {
        $this->selectedThirdPartyPackages = $packages;
    }
}

/**
 * Invoke InstallCommand::buildGuidelineConfig() with controlled feature selection.
 *
 * @param \Cake\Collection\Collection<int, string> $selectedIgnisFeatures Selected features
 * @param \Crustum\Ignis\Support\Config $config Install config store
 * @param bool $explicitFlagMode Whether CLI feature flags were passed
 * @return \Crustum\Ignis\Install\GuidelineConfig
 */
function buildGuidelineConfigWith(
    Collection $selectedIgnisFeatures,
    Config $config,
    bool $explicitFlagMode = false,
): GuidelineConfig {
    $container = freshTestContainer();
    registerTestAgents($container);
    $detector = new AgentsDetector($container, new IgnisManager());
    $guidelineComposer = Double::for(GuidelineComposer::class);
    $skillComposer = Double::for(SkillComposer::class);
    $ruleRepository = Double::for(RuleRepository::class);

    $command = new BuildGuidelineConfigInstallCommand(
        $detector,
        $config,
        $guidelineComposer,
        $skillComposer,
        $ruleRepository,
        Double::for(ProjectManager::class, override: true)->instance(),
    );
    $command->explicitFlagMode = $explicitFlagMode;
    $command->setSelectedIgnisFeaturesForTest($selectedIgnisFeatures);
    $command->setSelectedThirdPartyPackagesForTest(new Collection([]));

    return $command->callBuildGuidelineConfig();
}

test('hasMcp is true when mcp is in selected Ignis features', function (): void {
    $config = new Config();

    $guidelineConfig = buildGuidelineConfigWith(
        new Collection(['guidelines', 'mcp']),
        $config,
    );

    expect($guidelineConfig->hasMcp)->toBeTrue();
});

test('hasMcp is true when mcp is in stored config and running in explicit flag mode', function (): void {
    $config = new Config();
    $config->setMcp(true);

    $guidelineConfig = buildGuidelineConfigWith(
        new Collection(['guidelines']),
        $config,
        explicitFlagMode: true,
    );

    expect($guidelineConfig->hasMcp)->toBeTrue();
});

test('hasMcp is false when mcp is in stored config but running interactively without mcp selected', function (): void {
    $config = new Config();
    $config->setMcp(true);

    $guidelineConfig = buildGuidelineConfigWith(
        new Collection(['guidelines']),
        $config,
        explicitFlagMode: false,
    );

    expect($guidelineConfig->hasMcp)->toBeFalse();
});

test('hasMcp is false when mcp is neither in selected features nor stored config', function (): void {
    $config = new Config();

    $guidelineConfig = buildGuidelineConfigWith(
        new Collection(['guidelines']),
        $config,
    );

    expect($guidelineConfig->hasMcp)->toBeFalse();
});
