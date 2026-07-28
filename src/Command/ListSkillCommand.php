<?php
declare(strict_types=1);

namespace Crustum\Ignis\Command;

use Cake\Collection\Collection;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Ignis\Install\SkillComposer;
use Crustum\Ignis\Trait\ConsolePromptTrait;
use Override;

/**
 * Lists skills available in the current project.
 */
class ListSkillCommand extends Command
{
    use ConsolePromptTrait;

    /**
     * Constructor.
     *
     * @param \Crustum\Ignis\Install\SkillComposer $skillComposer Skill composer
     */
    public function __construct(protected SkillComposer $skillComposer)
    {
        parent::__construct();
    }

    /**
     * Execute the command.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int|null
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $skills = $this->skillComposer->config(new GuidelineConfig())->skills();

        if ($skills->isEmpty()) {
            $io->info('No skills available in this project.');

            return static::CODE_SUCCESS;
        }

        $projectName = (string)Configure::read('App.name', 'Application');
        $this->displayIgnisHeader($io, 'Skills', $projectName);

        $count = $skills->count();
        $this->displayNote($io, "Found {$count} skill" . ($count === 1 ? '' : 's'));
        $this->displaySkillsTable($io, $skills);

        return static::CODE_SUCCESS;
    }

    /**
     * Render the skills table.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param \Cake\Collection\Collection<string, \Crustum\Ignis\Install\Skill> $skills Skills collection
     * @return void
     */
    protected function displaySkillsTable(ConsoleIo $io, Collection $skills): void
    {
        /** @var array<int, array<int, string>> $rows */
        $rows = $skills
            ->sortBy(fn($skill) => $skill->name, SORT_ASC, SORT_STRING)
            ->map(fn($skill): array => $skill->custom
                ? [$skill->name . '*', 'local']
                : [$skill->name, $skill->package])
            ->toList();

        $io->out('');
        $this->displayTable($io, ['Skill', 'Source'], $rows);
    }

    /**
     * Build the option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser = $this->configureIgnisOptionParser($parser);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'ignis list-skills';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'List all available skills in the current project';
    }
}
