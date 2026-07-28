<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Cake\Collection\Collection;
use Crustum\Ignis\Support\Composer;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\ProjectManager;

/**
 * Discovers multi-target Ignis packs (provider ≠ target package).
 *
 * Example: `crustum/cakephp-skills` ships assets for `cakephp/queue` under
 * `resources/ignis/pack/cakephp/queue/skills/...` and
 * `resources/ignis/pack/cakephp/queue/guidelines/...`, plus optional major
 * trees `…/queue/2/skills` and `…/queue/2/guidelines` gated by the installed
 * package major.
 */
class SkillPackDiscovery
{
    /**
     * Constructor.
     *
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     * @param array<string, string>|null $packageDirectoryMap Optional package roots keyed by name
     */
    public function __construct(
        protected ProjectManager $project,
        protected ?array $packageDirectoryMap = null,
    ) {
    }

    /**
     * Discover installed pack providers.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\SkillPack>
     */
    public function packs(): Collection
    {
        $packs = [];

        foreach ($this->packageDirectories() as $name => $path) {
            if (!$this->isSkillPack($path)) {
                continue;
            }

            $packPath = $this->resolvePackRoot($path);

            if ($packPath === null) {
                continue;
            }

            $packs[$name] = new SkillPack($name, $packPath);
        }

        return new Collection($packs);
    }

    /**
     * Discover skill directories for installed target packages.
     *
     * Emits shared base skills first, then major-specific skills (`{target}/{major}/skills`)
     * so later merges override by skill name (parity with bundled `.ai/{pkg}/{major}/skill`).
     *
     * @return \Cake\Collection\Collection<int<0, max>, array{target: string, skillPath: string, pack: string, major: int|null}>
     */
    public function discover(): Collection
    {
        $entries = [];

        foreach ($this->packs() as $pack) {
            $targets = $this->readPackTargets($this->packageRootForPack($pack));

            foreach ($this->discoverTargetDirectories($pack->path) as $targetDir) {
                $relative = $this->relativeSkillPackPath($pack->path, $targetDir);
                $target = PackageRegistry::composerNameFromSkillPackFolder($relative, $targets);
                if ($target === null) {
                    continue;
                }

                if (!$this->targetIsInstalled($target)) {
                    continue;
                }

                $major = $this->targetMajor($target);

                $baseSkills = $this->resolveSkillsDirectory($targetDir);
                if ($baseSkills !== null) {
                    $entries[] = [
                        'target' => $target,
                        'skillPath' => $baseSkills,
                        'pack' => $pack->name,
                        'major' => null,
                    ];
                }

                if ($major !== null) {
                    $versionSkills = $this->resolveSkillsDirectory($targetDir . DS . $major);
                    if ($versionSkills !== null) {
                        $entries[] = [
                            'target' => $target,
                            'skillPath' => $versionSkills,
                            'pack' => $pack->name,
                            'major' => $major,
                        ];
                    }
                }
            }
        }

        // @phpstan-ignore return.type
        return new Collection($entries);
    }

    /**
     * Discover guideline directories for installed target packages.
     *
     * Emits shared base guidelines, then major-specific (`{target}/{major}/guidelines`).
     *
     * @return \Cake\Collection\Collection<int<0, max>, array{target: string, guidelinesPath: string, pack: string, major: int|null}>
     */
    public function discoverGuidelines(): Collection
    {
        $entries = [];

        foreach ($this->packs() as $pack) {
            $targets = $this->readPackTargets($this->packageRootForPack($pack));

            foreach ($this->discoverTargetDirectories($pack->path) as $targetDir) {
                $relative = $this->relativeSkillPackPath($pack->path, $targetDir);
                $target = PackageRegistry::composerNameFromSkillPackFolder($relative, $targets);
                if ($target === null) {
                    continue;
                }

                if (!$this->targetIsInstalled($target)) {
                    continue;
                }

                $major = $this->targetMajor($target);

                $baseGuidelines = $targetDir . DS . 'guidelines';
                if (is_dir($baseGuidelines)) {
                    $entries[] = [
                        'target' => $target,
                        'guidelinesPath' => $baseGuidelines,
                        'pack' => $pack->name,
                        'major' => null,
                    ];
                }

                if ($major !== null) {
                    $versionGuidelines = $targetDir . DS . $major . DS . 'guidelines';
                    if (is_dir($versionGuidelines)) {
                        $entries[] = [
                            'target' => $target,
                            'guidelinesPath' => $versionGuidelines,
                            'pack' => $pack->name,
                            'major' => $major,
                        ];
                    }
                }
            }
        }

        // @phpstan-ignore return.type
        return new Collection($entries);
    }

    /**
     * Whether a vendor package directory declares an Ignis pack.
     *
     * @param string $packagePath Absolute package root
     * @return bool
     */
    public function isSkillPack(string $packagePath): bool
    {
        if ($this->resolvePackRoot($packagePath) !== null) {
            return true;
        }

        $extra = $this->readComposerIgnisExtra($packagePath);

        return ($extra['pack'] ?? false) === true;
    }

    /**
     * Absolute `resources/ignis/pack` path, if present.
     *
     * @param string $packagePath Package root
     * @return string|null
     */
    protected function resolvePackRoot(string $packagePath): ?string
    {
        $path = $packagePath . DS . 'resources' . DS . 'ignis' . DS . 'pack';

        if (is_dir($path)) {
            return $path;
        }

        return null;
    }

    /**
     * Resolve the `skills/` directory under a pack target (or major folder).
     *
     * @param string $targetDir Target package directory under the pack
     * @return string|null
     */
    protected function resolveSkillsDirectory(string $targetDir): ?string
    {
        $path = $targetDir . DS . 'skills';

        if (is_dir($path)) {
            return $path;
        }

        return null;
    }

    /**
     * Whether the target package is present in the project.
     *
     * @param string $target Composer package name
     * @return bool
     */
    protected function targetIsInstalled(string $target): bool
    {
        if ($this->project->php()->uses($target)) {
            return true;
        }

        return $this->project->js()->uses($target);
    }

    /**
     * Installed package directories keyed by composer name.
     *
     * @return array<string, string>
     */
    protected function packageDirectories(): array
    {
        return $this->packageDirectoryMap ?? Composer::installedPackagesDirectories();
    }

    /**
     * Resolve the pack package root from its pack path.
     *
     * @param \Crustum\Ignis\Install\SkillPack $pack Skill pack
     * @return string
     */
    protected function packageRootForPack(SkillPack $pack): string
    {
        return dirname($pack->path, 3);
    }

    /**
     * Find directories under the pack root that contain `skills/` / `skill/` / `guidelines/`.
     *
     * @param string $packRoot Absolute path to resources/ignis/pack
     * @return list<string>
     */
    protected function discoverTargetDirectories(string $packRoot): array
    {
        $found = [];
        $this->walkSkillPackTargets($packRoot, $packRoot, $found);

        return array_values(array_unique($found));
    }

    /**
     * Recursively collect target package directories.
     *
     * Numeric major folders (`16`, `4`, …) are not walked as separate composer targets;
     * they hang under the package target root.
     *
     * @param string $packRoot Pack root
     * @param string $directory Current directory
     * @param list<string> $found Accumulator
     * @return void
     */
    protected function walkSkillPackTargets(string $packRoot, string $directory, array &$found): void
    {
        if ($directory !== $packRoot && $this->isTargetPackageDirectory($directory)) {
            $found[] = $directory;
        }

        foreach (glob($directory . DS . '*', GLOB_ONLYDIR) ?: [] as $child) {
            $base = basename($child);
            if (in_array($base, ['skills', 'guidelines'], true)) {
                continue;
            }

            if ($this->isMajorVersionDirectory($base)) {
                continue;
            }

            $this->walkSkillPackTargets($packRoot, $child, $found);
        }
    }

    /**
     * Whether a directory is a pack target root.
     *
     * True when it has shared `skills`/`guidelines`, or at least one numeric major child
     * that contains skills/guidelines (version-only layout).
     *
     * @param string $directory Directory path
     * @return bool
     */
    protected function isTargetPackageDirectory(string $directory): bool
    {
        if ($this->hasSkillsOrGuidelines($directory)) {
            return true;
        }

        return array_any(glob($directory . DS . '*', GLOB_ONLYDIR) ?: [], fn(string $child): bool => $this->isMajorVersionDirectory(basename($child)) && $this->hasSkillsOrGuidelines($child));
    }

    /**
     * Whether the directory has a skills or guidelines folder.
     *
     * @param string $directory Directory path
     * @return bool
     */
    protected function hasSkillsOrGuidelines(string $directory): bool
    {
        return is_dir($directory . DS . 'skills')
            || is_dir($directory . DS . 'guidelines');
    }

    /**
     * Whether a folder name is a pack major version segment (`4`, `16`, …).
     *
     * @param string $name Directory basename
     * @return bool
     */
    protected function isMajorVersionDirectory(string $name): bool
    {
        return (bool)preg_match('/^\d+$/', $name);
    }

    /**
     * Installed major version for a composer package, if known.
     *
     * @param string $target Composer package name
     * @return int|null
     */
    protected function targetMajor(string $target): ?int
    {
        foreach ($this->project->php()->packages() as $package) {
            if ($package->name() === $target) {
                return $package->major();
            }
        }

        foreach ($this->project->js()->packages() as $package) {
            if ($package->name() === $target) {
                return $package->major();
            }
        }

        return null;
    }

    /**
     * Relative path from pack root to a target directory (forward slashes).
     *
     * @param string $packRoot Pack root
     * @param string $targetDir Target package directory
     * @return string
     */
    protected function relativeSkillPackPath(string $packRoot, string $targetDir): string
    {
        $root = rtrim(str_replace(['/', '\\'], DS, $packRoot), DS);
        $dir = rtrim(str_replace(['/', '\\'], DS, $targetDir), DS);

        if (!str_starts_with($dir, $root)) {
            return '';
        }

        $relative = ltrim(substr($dir, strlen($root)), DS);

        return str_replace('\\', '/', $relative);
    }

    /**
     * Read optional folder → composer target map from pack composer.json.
     *
     * @param string $packagePath Pack package root
     * @return array<string, string>
     */
    protected function readPackTargets(string $packagePath): array
    {
        $extra = $this->readComposerIgnisExtra($packagePath);
        $targets = $extra['targets'] ?? [];

        if (!is_array($targets)) {
            return [];
        }

        $resolved = [];

        foreach ($targets as $folder => $composerName) {
            if (is_string($folder) && is_string($composerName) && $folder !== '' && $composerName !== '') {
                $resolved[strtolower(str_replace('\\', '/', $folder))] = $composerName;
            }
        }

        return $resolved;
    }

    /**
     * Read `extra.ignis` from a package composer.json.
     *
     * @param string $packagePath Package root
     * @return array<string, mixed>
     */
    protected function readComposerIgnisExtra(string $packagePath): array
    {
        $composerJson = $packagePath . DS . 'composer.json';

        if (!is_file($composerJson)) {
            return [];
        }

        $contents = file_get_contents($composerJson);

        if ($contents === false) {
            return [];
        }

        $data = json_decode($contents, true);

        if (!is_array($data)) {
            return [];
        }

        $extra = $data['extra']['ignis'] ?? [];

        return is_array($extra) ? $extra : [];
    }
}
