<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Ignis\Install\Trait\DiscoverPackagePathsTrait;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Ignis\Support\SkillParseFailures;
use Crustum\Ignis\Trait\RendersTwigGuidelinesTrait;
use Crustum\Inspector\Package;
use Crustum\Inspector\ProjectManager;
use Psr\Container\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Discovers Ignis skills from bundled, inspector, and user directories.
 */
class SkillComposer
{
    use DiscoverPackagePathsTrait;
    use RendersTwigGuidelinesTrait;

    /**
     * Cached discovered skills keyed by skill name.
     *
     * @var \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>|null
     */
    protected ?Collection $skills = null;

    /**
     * Constructor.
     *
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     * @param \Crustum\Ignis\Install\GuidelineConfig $config Guideline configuration
     */
    public function __construct(protected ProjectManager $project, protected GuidelineConfig $config = new GuidelineConfig())
    {
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
        $this->skills = null;

        return $this;
    }

    /**
     * Discover all available skills.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>
     */
    public function skills(): Collection
    {
        if ($this->skills instanceof Collection) {
            return $this->skills;
        }

        $excluded = Configure::read('Ignis.skills.exclude', []);

        if (!is_array($excluded)) {
            $excluded = [];
        }

        return $this->skills = $this->mergeSkillCollections([
            $this->mergeSkillCollections([
                $this->getCoreSkills(),
                $this->getSkillPackSkills(),
                $this->getIgnisSkills(),
                $this->getThirdPartySkills(),
            ])
                ->filter(fn(Skill $skill, string $key): bool => !in_array($key, $excluded, true)),
            $this->getUserSkills(),
        ]);
    }

    /**
     * Skills Ignis ships itself, discovered from `.ai/ignis/skill`.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>
     */
    protected function getCoreSkills(): Collection
    {
        return $this->discoverSkillsFromDirectory(
            $this->getIgnisAiPath() . DIRECTORY_SEPARATOR . 'ignis' . DIRECTORY_SEPARATOR . 'skill',
            'ignis',
        );
    }

    /**
     * Return skills from installed Ignis skill packs for present target packages.
     *
     * Pack skills load after core skills; first-party vendor / bundled package skills override
     * them when the same skill name exists (owner package wins).
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>
     */
    protected function getSkillPackSkills(): Collection
    {
        $skills = [];

        foreach ((new SkillPackDiscovery($this->project))->discover() as $entry) {
            foreach ($this->discoverSkillsFromDirectory($entry['skillPath'], $entry['target']) as $key => $skill) {
                $skills[$key] = $skill;
            }
        }

        return new Collection($skills);
    }

    /**
     * Return bundled and inspector package skills.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>
     */
    protected function getIgnisSkills(): Collection
    {
        $skills = [];

        foreach ($this->packages() as $package) {
            if ($this->shouldExcludePackage($package)) {
                continue;
            }

            foreach ($this->buildPackageSkills($package) as $key => $skill) {
                $skills[$key] = $skill;
            }
        }

        return new Collection($skills);
    }

    /**
     * Build skill entries for a inspector package.
     *
     * @param \Crustum\Inspector\Package $package Inspector package
     * @return array<string, \Crustum\Ignis\Install\Skill>
     */
    protected function buildPackageSkills(Package $package): array
    {
        $name = $this->normalizePackageName($package->name());
        $vendorSkillPath = $this->resolveFirstPartyIgnisPath($package, 'skills');
        $skills = [];

        $aiPath = $this->getIgnisAiPath() . DIRECTORY_SEPARATOR . $name;

        if (is_dir($aiPath)) {
            foreach ($this->discoverSkillsFromPath($aiPath, $name, $package->major() !== null ? (string)$package->major() : null) as $key => $skill) {
                $skills[$key] = $skill;
            }
        }

        if ($vendorSkillPath !== null) {
            foreach ($this->discoverSkillsFromDirectory($vendorSkillPath, $name) as $key => $skill) {
                $skills[$key] = $skill;
            }
        }

        return $skills;
    }

    /**
     * Return third-party package skills.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>
     */
    protected function getThirdPartySkills(): Collection
    {
        $packages = [];

        foreach (ThirdPartyPackage::skillDirectories($this->project) as $package => $path) {
            if ($this->config->aiGuidelines !== null && !in_array($package, $this->config->aiGuidelines, true)) {
                continue;
            }

            $packages[$package] = $path;
        }

        $skills = [];

        foreach ($packages as $package => $path) {
            foreach ($this->discoverSkillsFromDirectory($path, $package) as $key => $skill) {
                $skills[$key] = $skill;
            }
        }

        return new Collection($skills);
    }

    /**
     * Return user-authored skills.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>
     */
    protected function getUserSkills(): Collection
    {
        return $this->mergeSkillCollections([
            $this->discoverPackageSpecificUserSkills(),
            $this->discoverExplicitUserSkills(),
        ]);
    }

    /**
     * Discover explicit user skills from `.ai/skills`.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>
     */
    protected function discoverExplicitUserSkills(): Collection
    {
        $path = ProjectRoot::path() . DS . '.ai' . DS . 'skills';

        if (!is_dir($path)) {
            return new Collection([]);
        }

        $skills = [];

        foreach (glob($path . DS . '*', GLOB_ONLYDIR) ?: [] as $skillPath) {
            $skill = $this->parseSkill($skillPath, 'user', custom: true);

            if ($skill instanceof Skill) {
                $skills[$skill->name] = $skill;
            }
        }

        return new Collection($skills);
    }

    /**
     * Discover user skills nested under package directories in `.ai`.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>
     */
    protected function discoverPackageSpecificUserSkills(): Collection
    {
        $userAiPath = ProjectRoot::path() . DS . '.ai';

        if (!is_dir($userAiPath)) {
            return new Collection([]);
        }

        $skills = [];

        foreach ($this->discoverPackagePaths($userAiPath) as $package) {
            foreach ($this->discoverSkillsFromPath($package['path'], $package['name'], $package['version']) as $key => $skill) {
                $skills[$key] = $skill->withCustom(true);
            }
        }

        return new Collection($skills);
    }

    /**
     * Discover skills from a package path and optional version directory.
     *
     * @param string $packagePath Package directory path
     * @param string $packageName Package name
     * @param string|null $version Package major version
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>
     */
    protected function discoverSkillsFromPath(string $packagePath, string $packageName, ?string $version): Collection
    {
        $rootSkills = $this->discoverSkillsFromDirectory($packagePath . DIRECTORY_SEPARATOR . 'skill', $packageName);

        if ($version === null) {
            return $rootSkills;
        }

        $versionSkills = $this->discoverSkillsFromDirectory(
            $packagePath . DIRECTORY_SEPARATOR . $version . DIRECTORY_SEPARATOR . 'skill',
            $packageName,
        );

        return $this->mergeSkillCollections([$rootSkills, $versionSkills]);
    }

    /**
     * Discover skills from a skill directory.
     *
     * @param string $skillPath Skill directory path
     * @param string $packageName Package name
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>
     */
    protected function discoverSkillsFromDirectory(string $skillPath, string $packageName): Collection
    {
        if (!is_dir($skillPath)) {
            return new Collection([]);
        }

        $skills = [];

        foreach (glob($skillPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $skillDir) {
            $skill = $this->parseSkill($skillDir, $packageName);

            if ($skill instanceof Skill) {
                $skills[$skill->name] = $skill;
            }
        }

        return new Collection($skills);
    }

    /**
     * Parse a skill directory into a Skill value object.
     *
     * @param string $skillPath Skill directory path
     * @param string $package Package name
     * @param bool $custom Whether the skill is user-authored
     * @return \Crustum\Ignis\Install\Skill|null
     */
    protected function parseSkill(string $skillPath, string $package = '', bool $custom = false): ?Skill
    {
        $skillFile = $this->findSkillFile($skillPath);

        if ($skillFile === null) {
            return null;
        }

        $content = str_ends_with($skillFile, '.twig')
            ? $this->renderTwigFile($skillFile)
            : file_get_contents($skillFile);

        if (!is_string($content) || $content === '') {
            return null;
        }

        try {
            $frontmatter = $this->parseSkillFrontmatter($content);
        } catch (ParseException $parseException) {
            $this->skillParseFailures()->record($skillFile, $parseException->getMessage());

            return null;
        }

        if (empty($frontmatter['name']) || empty($frontmatter['description'])) {
            if ($frontmatter !== []) {
                $this->skillParseFailures()->record($skillFile, 'The frontmatter must define both [name] and [description].');
            }

            return null;
        }

        return new Skill(
            name: (string)$frontmatter['name'],
            package: $package !== '' ? $package : $this->determinePackageFromPath($skillPath),
            path: $skillPath,
            description: (string)$frontmatter['description'],
            custom: $custom,
        );
    }

    /**
     * Locate the primary skill definition file in a skill directory.
     *
     * @param string $skillPath Skill directory path
     * @return string|null
     */
    protected function findSkillFile(string $skillPath): ?string
    {
        foreach (['SKILL.twig', 'SKILL.md'] as $filename) {
            $path = $skillPath . DIRECTORY_SEPARATOR . $filename;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Parse YAML frontmatter from skill markdown content.
     *
     * @param string $content Skill file content
     * @return array<string, mixed>
     * @throws \Symfony\Component\Yaml\Exception\ParseException When the frontmatter is present but unusable
     */
    protected function parseSkillFrontmatter(string $content): array
    {
        $content = preg_replace('/^(\s*<!--.*?-->\s*)+/s', '', $content);

        if (preg_match('/^\s*---[^\S\r\n]*\R(.*?)\R---[^\S\r\n]*(?:\R|$)/s', (string)$content, $matches)) {
            $frontmatter = Yaml::parse($matches[1]);

            if ($frontmatter === null) {
                return [];
            }

            if (!is_array($frontmatter)) {
                throw new ParseException('Skill frontmatter must be a YAML mapping.');
            }

            return $frontmatter;
        }

        if (preg_match('/^\s*---[^\S\r\n]*(?:\R|$)/', (string)$content)) {
            throw new ParseException('The SKILL.md frontmatter has no closing delimiter.');
        }

        return [];
    }

    /**
     * Infer the package name from a skill directory path.
     *
     * @param string $skillPath Skill directory path
     * @return string
     */
    protected function determinePackageFromPath(string $skillPath): string
    {
        $parentDir = basename(dirname($skillPath));

        return preg_match('/^\d+(\.\d+)?$/', $parentDir) === 1
            ? basename(dirname($skillPath, 2))
            : $parentDir;
    }

    /**
     * Return the shared skill parse-failures recorder.
     *
     * @return \Crustum\Ignis\Support\SkillParseFailures
     */
    protected function skillParseFailures(): SkillParseFailures
    {
        $container = Configure::read('app.container');

        if ($container instanceof ContainerInterface && $container->has(SkillParseFailures::class)) {
            return $container->get(SkillParseFailures::class);
        }

        return new SkillParseFailures();
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
     * Merge multiple keyed skill collections.
     *
     * @param array<int, \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>> $collections Skill collections
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill>
     */
    protected function mergeSkillCollections(array $collections): Collection
    {
        $merged = [];

        foreach ($collections as $collection) {
            foreach ($collection as $key => $skill) {
                $merged[(string)$key] = $skill;
            }
        }

        return new Collection($merged);
    }
}
