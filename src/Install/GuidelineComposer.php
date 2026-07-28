<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Ignis\Install\Trait\DiscoverPackagePathsTrait;
use Crustum\Ignis\Support\Composer;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Ignis\Trait\RendersTwigGuidelinesTrait;
use Crustum\Inspector\Package;
use Crustum\Inspector\ProjectManager;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

// phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation
/**
 * Discovers and composes Ignis guideline markdown from bundled and project assets.
 *
 * @phpstan-type GuidelineScopedBlock array{paths: list<string>, body: string}
 * @phpstan-type GuidelineEntry array{
 *     content: string,
 *     name: string,
 *     description: string,
 *     path: string|null,
 *     custom: bool,
 *     third_party: bool,
 *     scoped: list<GuidelineScopedBlock>,
 *     tokens?: int
 * }
 */
// phpcs:enable
class GuidelineComposer
{
    use DiscoverPackagePathsTrait;
    use RendersTwigGuidelinesTrait;

    /**
     * Relative project directory for user-authored guidelines.
     */
    protected string $userGuidelineDir = '.ai/guidelines';

    /**
     * Cached composed guidelines keyed by guideline identifier.
     *
     * @var \Cake\Collection\Collection<string, GuidelineEntry>|null
     */
    protected ?Collection $guidelines = null;

    /**
     * Active guideline configuration.
     */
    protected GuidelineConfig $config;

    /**
     * Whether path-scoped blocks should be extracted from guidelines.
     */
    protected bool $extractRules = true;

    /**
     * Constructor.
     *
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     */
    public function __construct(protected ProjectManager $project)
    {
        $this->config = new GuidelineConfig();
    }

    /**
     * Disable path-scoped rule extraction for the next compose.
     *
     * @return self
     */
    public function withoutRuleExtraction(): self
    {
        $this->extractRules = false;
        $this->guidelines = null;

        return $this;
    }

    /**
     * Whether path-scoped rule extraction is active.
     *
     * @return bool
     */
    protected function rulesExtractionEnabled(): bool
    {
        return $this->extractRules
            && (bool)Configure::read('Ignis.rules.enabled', true)
            && (bool)Configure::read('Ignis.rules.scoped_guidelines', false);
    }

    /**
     * Return the project manager used for package discovery.
     *
     * @return \Crustum\Inspector\ProjectManager
     */
    protected function getProject(): ProjectManager
    {
        return $this->project;
    }

    /**
     * Apply guideline configuration flags.
     *
     * @param \Crustum\Ignis\Install\GuidelineConfig $config Guideline configuration
     * @return self
     */
    public function config(GuidelineConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Compose all discovered guidelines into one markdown document.
     *
     * @return string
     */
    public function compose(): string
    {
        // @phpstan-ignore argument.type
        return self::composeGuidelines($this->guidelines());
    }

    /**
     * Resolve a custom guideline path relative to the project root.
     *
     * @param string $path Optional path suffix
     * @return string
     */
    public function customGuidelinePath(string $path = ''): string
    {
        return ProjectRoot::path() . DS . str_replace('/', DS, $this->userGuidelineDir)
            . ($path !== '' ? DS . ltrim(str_replace(['/', '\\'], DS, $path), DS) : '');
    }

    /**
     * Compose guidelines from a keyed collection.
     *
     * @param \Cake\Collection\Collection<string, GuidelineEntry> $guidelines Guideline collection
     * @return string
     */
    public static function composeGuidelines(Collection $guidelines): string
    {
        $sections = [];

        foreach ($guidelines as $key => $guideline) {
            if (trim($guideline['content']) === '') {
                continue;
            }

            $sections[] = "\n=== {$key} rules ===\n\n" . trim($guideline['content']);
        }

        return MarkdownFormatter::format(trim(implode("\n\n", $sections)));
    }

    /**
     * Return the guideline keys included in the composed output.
     *
     * @return array<int, string>
     */
    public function used(): array
    {
        return array_keys($this->guidelines()->toArray());
    }

    /**
     * Discover and cache all guideline sources for the current configuration.
     *
     * @return \Cake\Collection\Collection<string, GuidelineEntry>
     */
    public function guidelines(): Collection
    {
        return $this->resolvedGuidelines()
            ->filter(fn(array $guideline): bool => trim($guideline['content']) !== '');
    }

    /**
     * All resolved guidelines before the content filter, including `@scoped`-only entries.
     *
     * @return \Cake\Collection\Collection<string, GuidelineEntry>
     */
    public function resolvedGuidelines(): Collection
    {
        if ($this->guidelines instanceof Collection) {
            // @phpstan-ignore return.type
            return $this->guidelines;
        }

        $excluded = Configure::read('Ignis.guidelines.exclude', []);

        if (!is_array($excluded)) {
            $excluded = [];
        }

        // @phpstan-ignore argument.type
        $base = $this->mergeGuidelineCollections([
            $this->getCoreGuidelines(),
            $this->getConditionalGuidelines(),
            $this->getPackageGuidelines(),
            $this->getPackGuidelines(),
            $this->getThirdPartyGuidelines(),
        ])->filter(fn(array $guideline, string $key): bool => !in_array($key, $excluded, true));

        $basePaths = $base->extract('path')->filter()->toList();
        $customGuidelines = $this->getUserGuidelines()
            ->filter(fn(array $guideline): bool => !in_array($guideline['path'], $basePaths, true));

        // @phpstan-ignore assign.propertyType
        $this->guidelines = $this->mergeGuidelineCollections([$customGuidelines, $base]);

        // @phpstan-ignore return.type
        return $this->guidelines;
    }

    /**
     * Discover user-authored guideline files.
     *
     * @return \Cake\Collection\Collection<string, GuidelineEntry>
     */
    protected function getUserGuidelines(): Collection
    {
        $guidelines = [];

        foreach ($this->guidelinesDir($this->customGuidelinePath()) as $guideline) {
            $guidelines['.ai/' . $guideline['name']] = $guideline;
        }

        // @phpstan-ignore return.type
        return new Collection($guidelines);
    }

    /**
     * Return bundled core guideline sources.
     *
     * @return \Cake\Collection\Collection<string, GuidelineEntry>
     */
    protected function getCoreGuidelines(): Collection
    {
        $guidelines = [
            'foundation' => $this->guideline('foundation'),
            'ignis' => $this->guideline('ignis/core'),
            'php' => $this->guideline('php/core'),
        ];

        foreach ($this->phpVersionGuidelinePaths() as $version => $path) {
            $guideline = $this->guideline($path);

            if (trim($guideline['content']) === '') {
                continue;
            }

            $guidelines['php/v' . $version] = $guideline;
        }

        // @phpstan-ignore return.type
        return new Collection($guidelines);
    }

    /**
     * Relative PHP minor guideline paths at or below the running PHP version.
     *
     * Discovers `.ai/php/{major.minor}/` directories, keeps those
     * `version_compare($dir, phpVersion(), '<=')`, sorted ascending.
     * Missing or empty `core` files are skipped by the caller.
     *
     * @return array<string, string> major.minor => `php/{version}/core`
     */
    protected function phpVersionGuidelinePaths(): array
    {
        $current = $this->phpVersion();
        $phpAiPath = $this->getIgnisAiPath() . DIRECTORY_SEPARATOR . 'php';

        if (!is_dir($phpAiPath)) {
            return [];
        }

        $versions = [];

        foreach (scandir($phpAiPath) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            if (!preg_match('/^\d+\.\d+$/', $entry)) {
                continue;
            }

            if (version_compare($entry, $current, '>')) {
                continue;
            }

            if (!is_dir($phpAiPath . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }

            $versions[$entry] = 'php/' . $entry . '/core';
        }

        uksort($versions, version_compare(...));

        return $versions;
    }

    /**
     * Running PHP major.minor version used for guideline selection.
     *
     * @return string
     */
    protected function phpVersion(): string
    {
        return $this->getGuidelineAssist()->phpVersion();
    }

    /**
     * Return conditional guideline sources enabled by configuration flags.
     *
     * @return \Cake\Collection\Collection<string, GuidelineEntry>
     */
    protected function getConditionalGuidelines(): Collection
    {
        $configs = [
            'cakephp/core' => [
                'condition' => $this->usesPackage(PackageRegistry::CAKEPHP),
                'path' => 'cakephp/core',
            ],
            'cakephp/style' => [
                'condition' => $this->config->cakeStyle,
                'path' => 'cakephp/style',
            ],
            'cakephp/api' => [
                'condition' => $this->config->hasAnApi,
                'path' => 'cakephp/api',
            ],
            'cakephp/localization' => [
                'condition' => $this->config->caresAboutLocalization,
                'path' => 'cakephp/localization',
            ],
            'tests' => [
                'condition' => $this->config->enforceTests,
                'path' => 'enforce-tests',
            ],
        ];

        $guidelines = [];

        foreach ($configs as $key => $config) {
            if ($config['condition']) {
                $guidelines[$key] = $this->guideline($config['path']);
            }
        }

        // @phpstan-ignore return.type
        return new Collection($guidelines);
    }

    /**
     * Return inspector package guideline sources.
     *
     * @return \Cake\Collection\Collection<string, GuidelineEntry>
     */
    protected function getPackageGuidelines(): Collection
    {
        $guidelines = [];

        foreach ($this->packages() as $package) {
            if ($this->shouldExcludePackage($package)) {
                continue;
            }

            foreach ($this->buildPackageGuidelines($package) as $key => $guideline) {
                $guidelines[$key] = $guideline;
            }
        }

        // @phpstan-ignore return.type
        return new Collection($guidelines);
    }

    /**
     * Build guideline entries for a inspector package.
     *
     * @param \Crustum\Inspector\Package $package Inspector package
     * @return array<string, GuidelineEntry>
     */
    protected function buildPackageGuidelines(Package $package): array
    {
        $guidelineDir = $this->normalizePackageName($package->name());
        $vendorPath = $this->resolveFirstPartyIgnisPath($package, 'guidelines');
        $vendorCorePath = $vendorPath !== null
            ? implode(DIRECTORY_SEPARATOR, [$vendorPath, 'core'])
            : null;

        $guidelines = [
            $guidelineDir . '/core' => $this->resolveGuideline($vendorCorePath, $guidelineDir . '/core'),
        ];

        $major = $package->major();

        if ($major !== null) {
            foreach ($this->guidelinesDir($guidelineDir . '/' . $major) as $guideline) {
                $suffix = $guideline['name'] === 'core' ? '' : '/' . $guideline['name'];
                $guidelines[$guidelineDir . '/v' . $major . $suffix] = $guideline;
            }
        }

        return $guidelines;
    }

    /**
     * Resolve a vendor or bundled guideline source.
     *
     * @param string|null $vendorPath Vendor guideline path without extension
     * @param string $guidelineKey Bundled guideline key
     * @return GuidelineEntry
     */
    private function resolveGuideline(?string $vendorPath, string $guidelineKey): array
    {
        if ($vendorPath !== null) {
            foreach (['.twig', '.md'] as $extension) {
                if (is_file($vendorPath . $extension)) {
                    return $this->guideline($vendorPath . $extension, false, $guidelineKey);
                }
            }
        }

        return $this->guideline($guidelineKey);
    }

    /**
     * Guidelines from Ignis packs for installed target packages.
     *
     * Pack assets for first-party targets (e.g. `cakephp/queue` via `crustum/cakephp-skills`)
     * always compose when the target is installed — same gating as pack skills, not the
     * third-party package picker (`cakephp/*` is first-party and never appears there).
     * Major trees (`pack/.../queue/2/guidelines`) compose only when `Package::major()` matches;
     * keys use `{pkg}/v{major}` (parity with bundled `.ai` package guidelines).
     *
     * @return \Cake\Collection\Collection<string, GuidelineEntry>
     */
    protected function getPackGuidelines(): Collection
    {
        /** @var array<string, GuidelineEntry> $guidelines */
        $guidelines = [];

        foreach ((new SkillPackDiscovery($this->project))->discoverGuidelines() as $entry) {
            $root = str_replace('\\', '/', (string)(realpath($entry['guidelinesPath']) ?: $entry['guidelinesPath']));
            $target = $entry['target'];
            $thirdParty = !Composer::isFirstPartyPackage($target);
            $major = $entry['major'];

            $keyed = $this->guidelinesDir(
                $entry['guidelinesPath'],
                $thirdParty,
                function (SplFileInfo $file) use ($target, $root, $major): string {
                    $relative = $this->relativeGuidelineKey($root, $file->getRealPath());

                    if ($major === null) {
                        return $target . '/' . $relative;
                    }

                    $suffix = $relative === 'core' ? '' : '/' . $relative;

                    return $target . '/v' . $major . $suffix;
                },
            );

            foreach ($keyed as $key => $guideline) {
                if ($thirdParty && $this->config->aiGuidelines !== null && !$this->keyBelongsToSelectedPackage((string)$key)) {
                    continue;
                }

                $guidelines[(string)$key] = $guideline;
            }
        }

        // @phpstan-ignore return.type
        return new Collection($guidelines);
    }

    /**
     * Return third-party package guideline sources.
     *
     * @return \Cake\Collection\Collection<string, GuidelineEntry>
     */
    protected function getThirdPartyGuidelines(): Collection
    {
        /** @var array<string, GuidelineEntry> $guidelines */
        $guidelines = [];

        foreach (Composer::packagesDirectoriesWithIgnisGuidelines() as $package => $path) {
            if (Composer::isFirstPartyPackage($package)) {
                continue;
            }

            $root = str_replace('\\', '/', (string)(realpath($path) ?: $path));

            $keyed = $this->guidelinesDir(
                $path,
                true,
                fn(SplFileInfo $file): string => $package . '/' . $this->relativeGuidelineKey($root, $file->getRealPath()),
            );

            foreach ($keyed as $key => $guideline) {
                $guidelines[(string)$key] = $guideline;
            }
        }

        if ($this->config->aiGuidelines === null) {
            // @phpstan-ignore return.type
            return new Collection($guidelines);
        }

        /** @var array<string, GuidelineEntry> $filtered */
        $filtered = [];

        foreach ($guidelines as $key => $guideline) {
            if ($this->keyBelongsToSelectedPackage($key)) {
                $filtered[$key] = $guideline;
            }
        }

        // @phpstan-ignore return.type
        return new Collection($filtered);
    }

    /**
     * Whether a third-party guideline key belongs to a selected package.
     *
     * @param string $key Guideline collection key
     * @return bool
     */
    private function keyBelongsToSelectedPackage(string $key): bool
    {
        return array_any($this->config->aiGuidelines ?? [], fn(string $package): bool => $key === $package || str_starts_with($key, $package . '/'));
    }

    /**
     * Relative guideline key under a third-party guidelines root.
     *
     * @param string $root Guidelines directory root
     * @param string $file Absolute guideline file path
     * @return string
     */
    private function relativeGuidelineKey(string $root, string $file): string
    {
        $file = str_replace('\\', '/', $file);

        $relative = str_starts_with($file, $root)
            ? ltrim(substr($file, strlen($root)), '/')
            : basename($file);

        return (string)preg_replace('/\.(twig|md)$/', '', $relative);
    }

    /**
     * Discover guideline files in a directory.
     *
     * @param string $dirPath Directory path
     * @param bool $thirdParty Whether the directory is third-party
     * @param callable|null $keyResolver Optional key resolver for keyed discovery
     * @return array<int|string, GuidelineEntry>
     */
    protected function guidelinesDir(string $dirPath, bool $thirdParty = false, ?callable $keyResolver = null): array
    {
        if (!is_dir($dirPath)) {
            $dirPath = str_replace('/', DIRECTORY_SEPARATOR, $this->getIgnisAiPath() . '/' . $dirPath);
        }

        try {
            $finder = Finder::create()
                ->files()
                ->in($dirPath)
                ->exclude('skill')
                ->name('*.twig')
                ->name('*.md')
                ->sortByName();
        } catch (DirectoryNotFoundException) {
            return [];
        }

        if ($keyResolver === null) {
            $guidelines = [];

            foreach ($finder as $file) {
                $realPath = $file->getRealPath();

                if ($realPath !== false) {
                    $guidelines[] = $this->guideline($realPath, $thirdParty);
                }
            }

            return $guidelines;
        }

        $guidelines = [];

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();

            if ($realPath === false) {
                continue;
            }

            $key = $keyResolver($file);
            $guidelines[$key] = $this->guideline($realPath, $thirdParty, $key);
        }

        return $guidelines;
    }

    /**
     * Build a guideline entry from a path or bundled key.
     *
     * @param string $path Guideline path or bundled key
     * @param bool $thirdParty Whether the guideline is third-party
     * @param string|null $overrideKey Optional override lookup key
     * @return GuidelineEntry
     */
    protected function guideline(string $path, bool $thirdParty = false, ?string $overrideKey = null): array
    {
        $path = $this->guidelinePath($path, $overrideKey);

        if ($path === null) {
            return [
                'content' => '',
                'description' => '',
                'name' => '',
                'path' => null,
                'custom' => false,
                'third_party' => $thirdParty,
                'scoped' => [],
            ];
        }

        $result = $this->renderTwigFileWithScopedBlocks($path, [], $this->rulesExtractionEnabled());
        $rendered = $result['content'];

        return [
            'content' => trim($rendered),
            'name' => str_replace(['.twig', '.md'], '', basename($path)),
            'description' => $this->extractDescription($rendered),
            'path' => $path,
            'custom' => $this->isCustomGuideline($path),
            'third_party' => $thirdParty,
            'scoped' => $result['blocks'],
            'tokens' => (int)round(str_word_count($rendered) * 1.3),
        ];
    }

    /**
     * Return the assist instance injected into Twig templates.
     *
     * @return \Crustum\Ignis\Install\GuidelineAssist
     */
    protected function getGuidelineAssist(): GuidelineAssist
    {
        return new GuidelineAssist($this->project, $this->config);
    }

    /**
     * Determine whether a guideline path belongs to the user guideline directory.
     *
     * @param string $path Absolute guideline path
     * @return bool
     */
    protected function isCustomGuideline(string $path): bool
    {
        $resolvedBase = realpath($this->customGuidelinePath());

        return $resolvedBase !== false && str_contains($path, $resolvedBase);
    }

    /**
     * Prepend the bundled Ignis guideline path to a relative key.
     *
     * @param string $path Relative guideline key
     * @return string
     */
    protected function prependPackageGuidelinePath(string $path): string
    {
        return $this->prependGuidelinePath($path, $this->getIgnisAiPath() . DS);
    }

    /**
     * Prepend the user guideline path to a relative key.
     *
     * @param string $path Relative guideline key
     * @return string
     */
    protected function prependUserGuidelinePath(string $path): string
    {
        return $this->prependGuidelinePath($path, $this->customGuidelinePath() . DS);
    }

    /**
     * Resolve a relative guideline key to an absolute path.
     *
     * @param string $path Relative guideline key or absolute path
     * @param string|null $overrideKey Optional override lookup key
     * @return string|null
     */
    protected function guidelinePath(string $path, ?string $overrideKey = null): ?string
    {
        if (!is_file($path)) {
            $path = $this->prependPackageGuidelinePath($path);

            if (!is_file($path)) {
                return null;
            }
        }

        $resolvedPath = realpath($path);

        if ($resolvedPath === false) {
            return null;
        }

        if ($this->isCustomGuideline($resolvedPath)) {
            return $resolvedPath;
        }

        if ($overrideKey !== null) {
            foreach (['.twig', '.md'] as $extension) {
                $customPath = $this->prependUserGuidelinePath($overrideKey . $extension);

                if (is_file($customPath)) {
                    $resolvedCustomPath = realpath($customPath);

                    return $resolvedCustomPath !== false ? $resolvedCustomPath : null;
                }
            }

            return $resolvedPath;
        }

        $basePath = realpath(dirname(__DIR__, 2));
        $relativePath = ltrim(str_replace(
            [$basePath, '.ai' . DS, '.ai/'],
            '',
            $resolvedPath,
        ), '/\\');
        $customPath = $this->prependUserGuidelinePath($relativePath);

        if (is_file($customPath)) {
            $resolvedCustomPath = realpath($customPath);

            return $resolvedCustomPath !== false ? $resolvedCustomPath : $resolvedPath;
        }

        return $resolvedPath;
    }

    /**
     * Prepend a base path and default extension when needed.
     *
     * @param string $path Relative guideline key
     * @param string $basePath Base directory
     * @return string
     */
    private function prependGuidelinePath(string $path, string $basePath): string
    {
        if (!str_ends_with($path, '.md') && !str_ends_with($path, '.twig')) {
            $path .= '.twig';
        }

        return str_replace('/', DS, $basePath . $path);
    }

    /**
     * Extract a short description from rendered markdown content.
     *
     * @param string $rendered Rendered markdown content
     * @return string
     */
    protected function extractDescription(string $rendered): string
    {
        if (!preg_match('/^#\s+(.+)$/m', $rendered, $matches)) {
            return 'No description provided';
        }

        $description = trim($matches[1]);

        if ($description === '') {
            return 'No description provided';
        }

        if (strlen($description) > 50) {
            return substr($description, 0, 50);
        }

        return $description;
    }

    /**
     * Merge multiple keyed guideline collections.
     *
     * @param array<int, \Cake\Collection\Collection<string, GuidelineEntry>> $collections Guideline collections
     * @return \Cake\Collection\Collection<string, GuidelineEntry>
     */
    protected function mergeGuidelineCollections(array $collections): Collection
    {
        $merged = [];

        foreach ($collections as $collection) {
            foreach ($collection as $key => $guideline) {
                $merged[(string)$key] = $guideline;
            }
        }

        // @phpstan-ignore return.type
        return new Collection($merged);
    }
}
