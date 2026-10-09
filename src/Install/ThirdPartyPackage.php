<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Cake\Collection\Collection;
use Crustum\Ignis\Support\Composer;
use Crustum\Ignis\Support\Npm;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\Package;
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
        $withGuidelines = self::guidelineDirectories($project);
        $withSkills = self::skillDirectories($project);

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
            if (self::isFirstPartyName($name)) {
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
     * Return package directories containing Ignis guidelines.
     *
     * Merges inspector discovery (Composer and npm) with manifest discovery so
     * packages remain visible when the inspector scan is unavailable.
     *
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     * @return array<string, string>
     */
    public static function guidelineDirectories(ProjectManager $project): array
    {
        return self::ignisDirectories($project, 'guidelines');
    }

    /**
     * Return package directories containing Ignis skills.
     *
     * Merges inspector discovery (Composer and npm) with manifest discovery so
     * packages remain visible when the inspector scan is unavailable.
     *
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     * @return array<string, string>
     */
    public static function skillDirectories(ProjectManager $project): array
    {
        return self::ignisDirectories($project, 'skills');
    }

    /**
     * Resolve third-party Ignis directories from inspector packages and manifests.
     *
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     * @param string $subpath Ignis resources subpath
     * @return array<string, string>
     */
    private static function ignisDirectories(ProjectManager $project, string $subpath): array
    {
        /** @var \Cake\Collection\CollectionInterface<int, \Crustum\Inspector\Package> $packages */
        $packages = new Collection([...$project->php()->packages()->all(), ...$project->js()->packages()->all()]);

        /** @var array<string, string> $directories */
        $directories = $packages
            ->filter(fn(Package $package): bool => $package->isDirect() && !PackageRegistry::isFirstParty($package) && PackageRegistry::ignisPath($package, $subpath) !== null)
            ->indexBy(fn(Package $package): string => $package->name())
            ->map(fn(Package $package): string => (string)PackageRegistry::ignisPath($package, $subpath))
            ->toArray();

        foreach ($subpath === 'guidelines' ? Composer::packagesDirectoriesWithIgnisGuidelines() : Composer::packagesDirectoriesWithIgnisSkills() as $name => $path) {
            $directories[$name] ??= $path;
        }

        foreach ($subpath === 'guidelines' ? Npm::packagesDirectoriesWithIgnisGuidelines() : Npm::packagesDirectoriesWithIgnisSkills() as $name => $path) {
            $directories[$name] ??= $path;
        }

        return array_filter($directories, self::isThirdPartyName(...), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Whether a package name is first-party for Ignis in either ecosystem.
     *
     * @param string $name Composer or npm package name
     * @return bool
     */
    private static function isFirstPartyName(string $name): bool
    {
        if (Composer::isFirstPartyPackage($name)) {
            return true;
        }

        return Npm::isFirstPartyPackage($name);
    }

    /**
     * Whether a package directory key is third-party for Ignis.
     *
     * @param string $path Package Ignis directory path
     * @param string $name Composer or npm package name
     * @return bool
     */
    private static function isThirdPartyName(string $path, string $name): bool
    {
        return !self::isFirstPartyName($name);
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
