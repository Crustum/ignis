<?php
declare(strict_types=1);

namespace Crustum\Ignis\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Ignis\Rules\RuleRepository;
use Override;

/**
 * Regenerates the project rules index from the rule files in .ai/rules.
 */
class RulesIndexCommand extends Command
{
    /**
     * Constructor.
     *
     * @param \Crustum\Ignis\Rules\RuleRepository $ruleRepository Rule repository
     */
    public function __construct(protected RuleRepository $ruleRepository)
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
        if (!$this->ruleRepository->exists()) {
            $io->info('No project rules found in .ai/rules.');

            return static::CODE_SUCCESS;
        }

        $conflicted = $this->ruleRepository->conflictedFiles();

        if ($conflicted !== []) {
            $io->error('Resolve the merge conflicts in these rule files first:');

            foreach ($conflicted as $file) {
                $io->out('  - ' . $this->ruleRepository->relativePath($file));
            }

            return static::CODE_ERROR;
        }

        foreach ($this->ruleRepository->unindexedFiles() as $file) {
            $io->warning('Skipped ' . $this->ruleRepository->relativePath($file) . ': no valid `paths` frontmatter.');
        }

        $path = $this->ruleRepository->writeIndex();
        $io->info('Regenerated ' . $this->ruleRepository->relativePath($path) . '.');

        return static::CODE_SUCCESS;
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
        return parent::buildOptionParser($parser);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'ignis index-rules';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Regenerate the project rules index from the rule files in .ai/rules';
    }
}
