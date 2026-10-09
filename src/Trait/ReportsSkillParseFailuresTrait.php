<?php
declare(strict_types=1);

namespace Crustum\Ignis\Trait;

use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Ignis\Support\SkillParseFailures;
use Psr\Container\ContainerInterface;

/**
 * Warns about skill files skipped for invalid or incomplete frontmatter.
 */
trait ReportsSkillParseFailuresTrait
{
    /**
     * Report recorded skill parse failures before the outro.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function reportSkillParseFailures(ConsoleIo $io): void
    {
        $failures = $this->skillParseFailures();

        if ($failures->isEmpty()) {
            return;
        }

        $entries = $failures->all();

        $io->out('');
        $io->warning(sprintf(
            'Skipped %d %s with invalid or incomplete frontmatter, leaving existing %s unchanged:',
            count($entries),
            count($entries) === 1 ? 'skill' : 'skills',
            count($entries) === 1 ? 'registration' : 'registrations',
        ));

        foreach ($entries as $entry) {
            $location = $this->repoRelativeSkillPath($entry['path']);
            $io->out(rtrim(sprintf('  - %s (%s): %s', $entry['name'], $location, $entry['reason']), ': '));
        }
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
     * Render a skill path relative to the project root with forward slashes.
     *
     * @param string $path Absolute skill file path
     * @return string
     */
    private function repoRelativeSkillPath(string $path): string
    {
        $root = str_replace('\\', '/', ProjectRoot::path());
        $location = str_replace('\\', '/', $path);

        if (str_starts_with($location, $root . '/')) {
            return substr($location, strlen($root) + 1);
        }

        return $location;
    }
}
