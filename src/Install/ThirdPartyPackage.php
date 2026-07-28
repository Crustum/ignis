<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Cake\Collection\Collection;
use Crustum\Ignis\Support\Composer;
use Crustum\Inspector\ProjectManager;

/**
 * Represents a third-party package that ships Ignis guidelines or skills.
 */
class ThirdPartyPackage
{
    /**
     * Constructor.
     *
     * @param string $name Composer package name
     * @param bool $hasGuidelines Whether Ignis guidelines are present
     * @param bool $hasSkills Whether Ignis skills are present
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $hasGuidelines,
        public readonly bool $hasSkills,
    ) {
    }

    /**
     * Discover all third-party packages with Ignis features.
     *
     * Includes pack targets (e.g. `cakephp/queue` from `crustum/cakephp-skills`) when
     * the target package is installed and the pack ships skills and/or guidelines.
     *
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     * @return \Cake\Collection\Collection<string, self>
     */
    public static function discover(ProjectManager $project): Collection
    {
        $withGuidelines = Composer::packagesDirectoriesWithIgnisGuidelines();
        $withSkills = Composer::packagesDirectoriesWithIgnisSkills();

        /** @var array<string, array{guidelines: bool, skills: bool}> $features */
        $features = [];

        foreach (array_keys($withGuidelines) as $name) {
            $features[$name] = ['guidelines' => true, 'skills' => false];
        }

        foreach (array_keys($withSkills) as $name) {
            $features[$name] = [
                'guidelines' => $features[$name]['guidelines'] ?? false,
                'skills' => true,
            ];
        }

        $discovery = new SkillPackDiscovery($project);

        foreach ($discovery->discover() as $entry) {
            $name = $entry['target'];
            $features[$name] = [
                'guidelines' => $features[$name]['guidelines'] ?? false,
                'skills' => true,
            ];
        }

        foreach ($discovery->discoverGuidelines() as $entry) {
            $name = $entry['target'];
            $features[$name] = [
                'guidelines' => true,
                'skills' => $features[$name]['skills'] ?? false,
            ];
        }

        $packages = [];

        foreach ($features as $name => $flags) {
            if (Composer::isFirstPartyPackage($name)) {
                continue;
            }

            $packages[$name] = new self(
                name: $name,
                hasGuidelines: $flags['guidelines'],
                hasSkills: $flags['skills'],
            );
        }

        return new Collection($packages);
    }

    /**
     * Human-readable feature summary for display labels.
     *
     * @return string
     */
    public function featureLabel(): string
    {
        return match (true) {
            $this->hasGuidelines && $this->hasSkills => 'guidelines, skills',
            $this->hasGuidelines => 'guideline',
            $this->hasSkills => 'skills',
            default => '',
        };
    }

    /**
     * Display label for selection prompts.
     *
     * @return string
     */
    public function displayLabel(): string
    {
        return "{$this->name} ({$this->featureLabel()})";
    }
}
