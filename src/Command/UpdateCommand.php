<?php
declare(strict_types=1);

namespace Crustum\Ignis\Command;

use Cake\Collection\Collection;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Ignis\Install\ThirdPartyPackage;
use Crustum\Ignis\Support\Config;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Ignis\Trait\ConsolePromptTrait;
use Crustum\Inspector\ProjectManager;
use Override;

/**
 * Updates Ignis guidelines and skills from the saved ignis.json configuration.
 */
class UpdateCommand extends Command
{
    use ConsolePromptTrait;

    /**
     * Constructor.
     *
     * @param \Crustum\Ignis\Support\Config $config Ignis install config
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory
     */
    public function __construct(
        protected Config $config,
        protected ProjectManager $project,
        ?CommandFactoryInterface $factory = null,
    ) {
        parent::__construct($factory);
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
        if (!$this->config->isValid() || $this->config->getAgents() === []) {
            $io->error('Please set up Ignis with [php bin/cake.php ignis install] first.');

            return static::CODE_ERROR;
        }

        if ($args->getBooleanOption('no-discover') !== true) {
            $this->discoverNewContent($args, $io);
        }

        $guidelines = $this->config->getGuidelines();
        $skillsPath = ProjectRoot::path() . DS . '.ai' . DS . 'skills';
        $hasSkills = $args->getBooleanOption('ignore-skills') !== true
            && ($this->config->hasSkills() || is_dir($skillsPath));

        if (!$guidelines && !$hasSkills) {
            return static::CODE_SUCCESS;
        }

        $installArgs = ['--no-interaction'];

        if ($guidelines) {
            $installArgs[] = '--guidelines';
        }

        if ($hasSkills) {
            $installArgs[] = '--skills';
        }

        $this->executeCommand(InstallCommand::class, $installArgs, $io);
        $io->success('Ignis guidelines and skills updated successfully.');

        return static::CODE_SUCCESS;
    }

    /**
     * Discover and optionally add newly available third-party packages.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function discoverNewContent(Arguments $args, ConsoleIo $io): void
    {
        $newPackages = $this->resolveNewPackages();

        if ($newPackages->isEmpty()) {
            return;
        }

        if (!$this->isInteractive($args, $io)) {
            return;
        }

        $options = $newPackages
            ->map(fn(ThirdPartyPackage $package, string $name): array => [$name => $package->displayLabel()])
            ->reduce(fn(array $carry, array $item): array => array_merge($carry, $item), []);

        $selectedPackages = $this->promptMultiselect(
            $io,
            'New packages with guidelines/skills discovered! Which would you like to add?',
            $options,
            [],
            false,
            'Select packages to include their guidelines and skills',
        );

        if ($selectedPackages !== []) {
            $this->config->setPackages(array_merge($this->config->getPackages(), $selectedPackages));
        }
    }

    /**
     * Resolve packages that are not yet configured in ignis.json.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Install\ThirdPartyPackage>
     */
    protected function resolveNewPackages(): Collection
    {
        $configuredPackages = $this->config->getPackages();

        return ThirdPartyPackage::discover($this->project)
            ->filter(fn(ThirdPartyPackage $package, string $name): bool => !in_array($name, $configuredPackages, true));
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

        $parser
            ->addOption('discover', [
                'help' => 'Discover and prompt for newly available guidelines and skills (default)',
                'boolean' => true,
            ])
            ->addOption('no-discover', [
                'help' => 'Skip discovering and prompting for newly available guidelines and skills',
                'boolean' => true,
            ])
            ->addOption('ignore-skills', [
                'help' => 'Skip updating the skills directory',
                'boolean' => true,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'ignis update';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Update the CakePHP Ignis guidelines and skills to the latest guidance';
    }
}
