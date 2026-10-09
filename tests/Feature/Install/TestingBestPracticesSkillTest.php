<?php

declare(strict_types=1);

use Crustum\Ignis\Install\GuidelineAssist;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Ignis\Trait\RendersTwigGuidelinesTrait;
use Crustum\Inspector\Enums\PackageSource;
use Crustum\Inspector\Package;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;
use JMac\Testing\Double;

/**
 * Build a ProjectManager mock with the given PHP packages for GuidelineAssist.
 *
 * @param list<\Crustum\Inspector\Package> $packages PHP packages
 * @return \Crustum\Inspector\ProjectManager
 */
function testingSkillProject(array $packages): ProjectManager
{
    $project = Double::for(ProjectManager::class, override: true);
    mockProjectPackages($project, new PackageCollection($packages));

    return $project->instance();
}

/**
 * Render SKILL.twig plus all rules/*.twig for the given packages.
 *
 * @param list<\Crustum\Inspector\Package> $packages PHP packages
 */
function renderTestingBestPracticesSkill(array $packages): string
{
    $assist = new GuidelineAssist(testingSkillProject($packages), new GuidelineConfig());

    $renderer = new class ($assist) {
        use RendersTwigGuidelinesTrait;

        public function __construct(private GuidelineAssist $assist)
        {
        }

        public function renderFile(string $path): string
        {
            return $this->renderTwigFile($path);
        }

        protected function getGuidelineAssist(): GuidelineAssist
        {
            return $this->assist;
        }
    };

    $skillDir = dirname(__DIR__, 3) . '/.ai/cakephp/skill/testing-best-practices';
    $paths = array_merge([$skillDir . '/SKILL.twig'], glob($skillDir . '/rules/*.twig') ?: []);

    $rendered = [];
    foreach ($paths as $path) {
        $rendered[] = $renderer->renderFile($path);
    }

    return implode("\n", $rendered);
}

/**
 * @param list<\Crustum\Inspector\Package> $extraPackages
 */
function phpunitTestingSkill(array $extraPackages = [], string $version = '11.5.3'): string
{
    return renderTestingBestPracticesSkill(array_merge([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package('phpunit/phpunit', $version, PackageSource::Composer),
    ], $extraPackages));
}

it('teaches PHPUnit and Cake TestSuite syntax to a PHPUnit project and never Pest syntax', function (): void {
    expect(phpunitTestingSkill())
        ->toContain('This project uses PHPUnit.')
        ->toContain('IntegrationTestTrait')
        ->toContain('assertResponseOk')
        ->toContain('phpunit.de/documentation.html')
        ->not->toContain('pestphp.com')
        ->not->toContain('docs.phpunit.de')
        ->not->toContain("it('");
});

it('teaches Pest syntax to a Pest project and never PHPUnit-only mandates', function (): void {
    $rendered = renderTestingBestPracticesSkill([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package('pestphp/pest', '3.8.0', PackageSource::Composer),
    ]);

    expect($rendered)
        ->toContain('This project uses Pest.')
        ->toContain('describe()')
        ->not->toContain('This project uses PHPUnit.');
});

it('names the installed PHPUnit version instead of pinning a documentation edition', function (string $version): void {
    expect(phpunitTestingSkill(version: $version))
        ->toContain("the PHPUnit {$version} documentation at `https://phpunit.de/documentation.html`")
        ->not->toContain('docs.phpunit.de');
})->with([
    'PHPUnit 11' => ['11.5.3'],
    'PHPUnit 12' => ['12.5.0'],
]);

it('gates Pest 5 features on the installed Pest version', function (): void {
    $pest4 = renderTestingBestPracticesSkill([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package('pestphp/pest', '3.8.0', PackageSource::Composer),
    ]);
    $pest5 = renderTestingBestPracticesSkill([
        new Package(PackageRegistry::CAKEPHP, '5.0.0', PackageSource::Composer),
        new Package('pestphp/pest', '5.1.0', PackageSource::Composer),
    ]);

    expect($pest4)->not->toContain('Format Expectations')
        ->and($pest5)->toContain('Format Expectations');
});

it('ships the skill index with all nine rule files', function (): void {
    $skillDir = dirname(__DIR__, 3) . '/.ai/cakephp/skill/testing-best-practices';

    expect($skillDir . '/SKILL.twig')->toBeFile()
        ->and(glob($skillDir . '/rules/*.twig') ?: [])->toHaveCount(9);
});
