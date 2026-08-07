<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\Enums\JsPackageManager;
use Crustum\Inspector\Package;
use Crustum\Inspector\ProjectManager;
use Symfony\Component\Finder\Finder;

/**
 * Template assist helpers exposed to Ignis Twig guidelines.
 */
class GuidelineAssist
{
    /**
     * Discovered enum class paths keyed by class name.
     *
     * @var array<string, string>
     */
    protected array $enumPaths = [];

    /**
     * Constructor.
     *
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     * @param \Crustum\Ignis\Install\GuidelineConfig $config Guideline configuration
     * @param \Cake\Collection\Collection<int, mixed>|null $skills Optional discovered skills
     */
    public function __construct(
        public ProjectManager $project,
        public GuidelineConfig $config,
        public ?Collection $skills = null,
    ) {
        $this->skills ??= new Collection([]);
        $this->enumPaths = $this->discover();
    }

    /**
     * Return discovered enum class paths keyed by class name.
     *
     * @return array<string, string>
     */
    public function enums(): array
    {
        return $this->enumPaths;
    }

    /**
     * Discover enum classes in the application source directory.
     *
     * @return array<string, string>
     */
    protected function discover(): array
    {
        $appPath = ROOT . DS . 'src';

        if (!is_dir($appPath)) {
            return [];
        }

        $enums = [];
        $namespace = (string)Configure::read('App.namespace', 'App');
        $finder = Finder::create()
            ->in($appPath)
            ->files()
            ->name('/[A-Z].*\.php$/');

        foreach ($finder as $file) {
            $path = $file->getRealPath();

            if ($path === false) {
                continue;
            }

            $code = file_get_contents($path);
            if ($code === false) {
                continue;
            }

            if (stripos($code, 'enum') === false) {
                continue;
            }

            foreach (token_get_all($code) as $token) {
                if (is_array($token) && $token[0] === T_ENUM) {
                    $className = $namespace . '\\' . str_replace(
                        ['/', '.php'],
                        ['\\', ''],
                        $file->getRelativePathname(),
                    );
                    $enums[$className] = $path;

                    break;
                }
            }
        }

        return $enums;
    }

    /**
     * Return concatenated enum source code for guideline templates.
     *
     * @return string
     */
    public function enumContents(): string
    {
        $enumPaths = $this->enumPaths;
        ksort($enumPaths);

        return (new Collection($enumPaths))
            ->map(fn(string $path): string => is_file($path) ? (file_get_contents($path) ?: '') : '')
            ->filter(fn(string $contents): bool => $contents !== '')
            ->reduce(fn(string $carry, string $contents): string => $carry === '' ? $contents : $carry . PHP_EOL . $contents, '');
    }

    /**
     * Determine whether the Pint formatter is available at the required version.
     *
     * @return bool
     */
    public function supportsPintAgentFormatter(): bool
    {
        return false;
    }

    /**
     * Determine whether the project includes a package by composer/npm name.
     *
     * @param string $package Package name
     * @return bool
     */
    public function hasPackage(string $package): bool
    {
        if ($this->project->php()->uses($package)) {
            return true;
        }

        return $this->project->js()->uses($package);
    }

    /**
     * Determine whether the inspector includes a package by composer/npm name.
     *
     * @param string $package Package name
     * @return bool
     */
    public function inspectorUses(string $package): bool
    {
        return $this->hasPackage($package);
    }

    /**
     * Whether an installed package satisfies a Composer semver constraint.
     *
     * Thin Twig-facing wrap around Inspector `Ecosystem::uses($package, $constraint)`.
     * Accepts any Composer constraint (`>=5.4`, `^5.3`, `>=5.0 <6`, …).
     *
     * @param string $package Composer or npm package name
     * @param string|null $constraint Semver constraint; null checks presence only
     * @return bool
     */
    public function uses(string $package, ?string $constraint = null): bool
    {
        if ($this->project->php()->uses($package, $constraint)) {
            return true;
        }

        return $this->project->js()->uses($package, $constraint);
    }

    /**
     * Return the resolved installed version for a package when present.
     *
     * @param string $package Composer or npm package name
     * @return string|null
     */
    public function packageVersion(string $package): ?string
    {
        $resolved = $this->project->php()->package($package)
            ?? $this->project->js()->package($package);

        if (!$resolved instanceof Package) {
            return null;
        }

        $version = $resolved->version();

        return $version !== '' ? $version : null;
    }

    /**
     * Whether an installed package version is greater than or equal to a version.
     *
     * @param string $package Composer or npm package name
     * @param string $version Version floor (for example `5.4`)
     * @return bool
     */
    public function versionGte(string $package, string $version): bool
    {
        return $this->uses($package, '>=' . $version);
    }

    /**
     * Whether an installed package version is less than a version.
     *
     * @param string $package Composer or npm package name
     * @param string $version Version ceiling (for example `5.4`)
     * @return bool
     */
    public function versionLt(string $package, string $version): bool
    {
        return $this->uses($package, '<' . $version);
    }

    /**
     * Return the detected node package manager command name.
     *
     * @return string
     */
    public function nodePackageManager(): string
    {
        return ($this->project->js()->packageManager() ?? JsPackageManager::Npm)->value;
    }

    /**
     * Return the detected node package manager command name.
     *
     * @return string
     */
    protected function detectedNodePackageManager(): string
    {
        return $this->nodePackageManager();
    }

    /**
     * Build a node package manager command string.
     *
     * @param string $command Command suffix
     * @return string
     */
    public function nodePackageManagerCommand(string $command): string
    {
        $npmExecutable = Configure::read('Ignis.executable_paths.npm');

        if (is_string($npmExecutable) && $npmExecutable !== '') {
            return "{$npmExecutable} {$command}";
        }

        return "{$this->detectedNodePackageManager()} {$command}";
    }

    /**
     * Build a Cake console command string.
     *
     * @param string $command Command suffix
     * @return string
     */
    public function cakeCommand(string $command): string
    {
        return "{$this->cake()} {$command}";
    }

    /**
     * Build a Composer command string.
     *
     * @param string $command Command suffix
     * @return string
     */
    public function composerCommand(string $command): string
    {
        $composerExecutable = Configure::read('Ignis.executable_paths.composer');

        if (is_string($composerExecutable) && $composerExecutable !== '') {
            return "{$composerExecutable} {$command}";
        }

        return "composer {$command}";
    }

    /**
     * Build a vendor binary command string.
     *
     * @param string $command Binary name
     * @return string
     */
    public function binCommand(string $command): string
    {
        $vendorBinPrefix = Configure::read('Ignis.executable_paths.vendor_bin');

        if (is_string($vendorBinPrefix) && $vendorBinPrefix !== '') {
            return "{$vendorBinPrefix}{$command}";
        }

        return "vendor/bin/{$command}";
    }

    /**
     * Return the Cake console entry command.
     *
     * @return string
     */
    public function cake(): string
    {
        $phpExecutable = Configure::read('Ignis.executable_paths.php');
        $php = is_string($phpExecutable) && $phpExecutable !== '' ? $phpExecutable : 'php';

        return "{$php} bin/cake.php";
    }

    /**
     * Return a project-relative application path.
     *
     * @param string $path Optional path suffix
     * @return string
     */
    public function appPath(string $path = ''): string
    {
        $appDirectory = ROOT . DS . 'src';

        if ($path !== '') {
            $appDirectory .= DS . ltrim(str_replace(['/', '\\'], DS, $path), DS);
        }

        $relativePath = ltrim(str_replace(ROOT . DS, '', $appDirectory), DS);

        return str_replace(DS, '/', $relativePath);
    }

    /**
     * Return the active PHP major.minor version string.
     *
     * @return string
     */
    public function phpVersion(): string
    {
        return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    }

    /**
     * Return the configured application purpose when set.
     *
     * @return string|null
     */
    public function purpose(): ?string
    {
        $purpose = Configure::read('Ignis.purpose');

        if (!is_string($purpose)) {
            return null;
        }

        $purpose = trim($purpose);

        return $purpose !== '' ? $purpose : null;
    }

    /**
     * Whether Foundational Context should include transitive packages.
     *
     * Config `Ignis.guidelines.dependencies`: `direct` (default) or `all`.
     *
     * @return bool
     */
    public function listAllDependencies(): bool
    {
        $mode = Configure::read('Ignis.guidelines.dependencies', 'direct');

        return is_string($mode) && strtolower($mode) === 'all';
    }

    /**
     * Return unique inspector packages for guideline templates.
     *
     * Default (`direct`) lists only Composer/npm direct requires. Set
     * `Ignis.guidelines.dependencies` to `all` for the previous full list.
     *
     * @return array<int, array{name: string, inspectorName: string, major: string}>
     */
    public function inspectorPackages(): array
    {
        $seen = [];
        $packages = [];
        $includeTransitive = $this->listAllDependencies();

        foreach (
            [
                ...$this->project->php()->packages()->all(),
                ...$this->project->js()->packages()->all(),
            ] as $package
        ) {
            if (!$includeTransitive && !$package->isDirect()) {
                continue;
            }

            $name = $package->name();

            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $packages[] = [
                'name' => $name,
                'inspectorName' => PackageRegistry::inspectorName($name),
                'major' => (string)($package->major() ?? ''),
            ];
        }

        return $packages;
    }

    /**
     * Determine whether project rules are enabled.
     *
     * @return bool
     */
    public function rulesEnabled(): bool
    {
        return (bool)Configure::read('Ignis.rules.enabled', true);
    }

    /**
     * Determine whether browser log capture is enabled.
     *
     * @return bool
     */
    public function browserLogsEnabled(): bool
    {
        $value = Configure::read('Ignis.browser_logs');

        return $value !== false && $value !== null;
    }

    /**
     * Determine whether the browser log watcher is enabled.
     *
     * @return bool
     */
    public function browserLogsWatcherEnabled(): bool
    {
        return (bool)Configure::read('Ignis.browser_logs_watcher', false);
    }

    /**
     * Determine whether the MCP tinker tool is enabled.
     *
     * @return bool
     */
    public function tinkerToolEnabled(): bool
    {
        return (bool)Configure::read('Ignis.tinker_tool_enabled', false);
    }

    /**
     * Determine whether skills are enabled for the current install run.
     *
     * @return bool
     */
    public function hasSkillsEnabled(): bool
    {
        return $this->config->hasSkills;
    }

    /**
     * Determine whether MCP is enabled for the current install run.
     *
     * @return bool
     */
    public function hasMcpEnabled(): bool
    {
        return $this->config->hasMcp;
    }
}
