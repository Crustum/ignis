<?php

declare(strict_types=1);

use Cake\Collection\Collection;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Crustum\Ignis\Command\InstallCommand;
use Crustum\Ignis\Install\Agents\Agent;
use Crustum\Ignis\Install\Agents\ClaudeCode;
use Crustum\Ignis\Install\AgentsDetector;
use Crustum\Ignis\Install\GuidelineComposer;
use Crustum\Ignis\Install\Skill;
use Crustum\Ignis\Install\SkillComposer;
use Crustum\Ignis\Rules\RuleRepository;
use Crustum\Ignis\Support\Config;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Inspector\ProjectManager;
use JMac\Testing\Double;

beforeEach(function (): void {
    flushIgnisConfig();
    $this->previousRoot = ProjectRoot::override();
    $this->tempRoot = testAppTmpPath('ignis-skill-sync-' . uniqid());
    mkdir($this->tempRoot, 0777, true);
    ProjectRoot::set($this->tempRoot);
});

afterEach(function (): void {
    restoreSyncTestPermissions($this->tempRoot);
    ProjectRoot::set($this->previousRoot);
    deleteDirectory($this->tempRoot);
    flushIgnisConfig();
    Configure::delete('app.container');
});

/**
 * Restore write permissions so a failed-sync fixture can be deleted.
 *
 * @param string $root Temporary project root
 * @return void
 */
function restoreSyncTestPermissions(string $root): void
{
    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        @chmod($file->getPathname(), $file->isDir() ? 0755 : 0644);
    }

    @chmod($root, 0755);
}

/**
 * Stage a source skill directory with a valid marker file.
 *
 * @param string $root Temporary project root
 * @param string $name Skill name
 * @return string Source skill directory
 */
function stageSyncSourceSkill(string $root, string $name): string
{
    $source = $root . DIRECTORY_SEPARATOR . 'source-' . $name;
    mkdir($source, 0777, true);
    file_put_contents(
        $source . DIRECTORY_SEPARATOR . 'SKILL.md',
        "---\nname: {$name}\ndescription: Sync reproduction\n---\n",
    );
    file_put_contents($source . DIRECTORY_SEPARATOR . 'asset.txt', 'new content');

    return $source;
}

/**
 * Build an install command with a stubbed skill composer and agent.
 *
 * @param \Crustum\Ignis\Support\Config $config Ignis install config
 * @param \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill> $skills Skills to sync
 * @param \Crustum\Ignis\Install\Agents\Agent $agent Agent receiving the skills
 * @return \Crustum\Ignis\Command\InstallCommand
 */
function makeSkillSyncCommand(Config $config, Collection $skills, Agent $agent): InstallCommand
{
    $skillComposer = Double::for(SkillComposer::class);
    $skillComposer->allows('config')->returns($skillComposer);
    $skillComposer->allows('skills')->returns($skills);

    return new class (
        Double::for(AgentsDetector::class),
        $config,
        Double::for(GuidelineComposer::class),
        $skillComposer,
        Double::for(RuleRepository::class),
        Double::for(ProjectManager::class, override: true)->instance(),
        $agent,
    ) extends InstallCommand {
        public function __construct(
            AgentsDetector $detector,
            Config $config,
            GuidelineComposer $guidelineComposer,
            SkillComposer $skillComposer,
            RuleRepository $ruleRepository,
            ProjectManager $project,
            private Agent $syncAgent,
        ) {
            parent::__construct($detector, $config, $guidelineComposer, $skillComposer, $ruleRepository, $project);
        }

        protected function agentsWithSkills(): Collection
        {
            return new Collection([$this->syncAgent]);
        }

        public function runSkillsSync(ConsoleIo $io): void
        {
            $this->installSkills($io);
        }

        /**
         * @return array<int, string>
         */
        public function syncedSkillNames(): array
        {
            return $this->installedSkillNames;
        }
    };
}

/**
 * Run the skills sync and return all console output lines.
 *
 * @param \Crustum\Ignis\Command\InstallCommand $command Install command
 * @return string
 */
function runSkillsSync(InstallCommand $command): string
{
    $out = new StubConsoleOutput();
    $command->runSkillsSync(new ConsoleIo($out, $out));

    return implode("\n", $out->messages());
}

it('reports a failed skill sync instead of displaying success', function (): void {
    $source = stageSyncSourceSkill($this->tempRoot, 'failed-skill');
    $skills = new Collection([
        'failed-skill' => new Skill(
            name: 'failed-skill',
            package: 'ignis',
            path: $source,
            description: 'Sync reproduction',
        ),
    ]);
    deleteDirectory($source);

    $command = makeSkillSyncCommand(new Config(), $skills, new ClaudeCode(detectionStrategyFactory()));
    $output = runSkillsSync($command);

    expect($output)->toContain('Failed to sync skills: failed-skill');
});

it('does not track a skill that failed to sync', function (): void {
    $source = stageSyncSourceSkill($this->tempRoot, 'failed-skill');
    $skills = new Collection([
        'failed-skill' => new Skill(
            name: 'failed-skill',
            package: 'ignis',
            path: $source,
            description: 'Sync reproduction',
        ),
    ]);
    deleteDirectory($source);

    $command = makeSkillSyncCommand(new Config(), $skills, new ClaudeCode(detectionStrategyFactory()));
    runSkillsSync($command);

    expect($command->syncedSkillNames())->not->toContain('failed-skill');
});

it('reports a stale skill that could not be removed', function (): void {
    $stale = $this->tempRoot . DIRECTORY_SEPARATOR . '.claude' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'stale-skill';

    if (!posixPermissionsEffective($this->tempRoot)) {
        $this->markTestSkipped('Skipped: POSIX file permissions are not effective on this filesystem.');
    }

    mkdir($stale, 0777, true);
    file_put_contents($stale . DIRECTORY_SEPARATOR . 'SKILL.md', 'stale content');
    chmod($stale . DIRECTORY_SEPARATOR . 'SKILL.md', 0444);
    chmod($stale, 0555);

    $config = new Config();
    $config->setSkills(['stale-skill']);

    $command = makeSkillSyncCommand($config, new Collection([]), new ClaudeCode(detectionStrategyFactory()));
    $output = runSkillsSync($command);

    expect($output)->toContain('Failed to sync skills: stale-skill')
        ->and($stale)->toBeDirectory();
})->skipOnWindows();
