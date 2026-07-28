<?php

declare(strict_types=1);

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Ignis\Mcp\Tools\RecordRule;
use Crustum\Ignis\Rules\RuleRepository;
use Crustum\Mcp\Request;

beforeEach(function (): void {
    $sandbox = prepareFeatureRulesSandbox();

    $this->originalRoot = $sandbox['originalRoot'];
    $this->rulesBasePath = $sandbox['basePath'];
    $this->rulesDir = $sandbox['rulesDir'];
    $this->repository = $sandbox['repository'];
});

afterEach(function (): void {
    resetFeatureRulesSandbox($this->rulesBasePath, $this->originalRoot);
});

/**
 * Build managed rule file payloads keyed by slug.
 *
 * @param string ...$slugs Rule slugs
 * @return \Cake\Collection\Collection<string, array{paths: array<int, string>, title: string, content: string}>
 */
function managedRuleFiles(string ...$slugs): Collection
{
    $files = [];

    foreach ($slugs as $slug) {
        $files[$slug] = [
            'paths' => ['tests/**'],
            'title' => ucfirst($slug),
            'content' => "Rule body for {$slug}.",
        ];
    }

    return new Collection($files);
}

/**
 * Record a user rule via the record-rule MCP tool.
 *
 * @param \Crustum\Ignis\Rules\RuleRepository $repository Rule repository
 * @param string $glob Path glob
 * @param string $title Rule title
 * @param string $note Rule note
 * @return void
 */
function recordManagedRulesTestRule(RuleRepository $repository, string $glob, string $title, string $note): void
{
    (new RecordRule($repository))->handle(new Request([
        'glob' => $glob,
        'title' => $title,
        'note' => $note,
    ]));
}

it('writes managed rule files under a ignis subdirectory with frontmatter', function (): void {
    $written = $this->repository->syncManaged(managedRuleFiles('tests'));

    expect($written)->toHaveCount(1);

    $path = $this->rulesDir . '/ignis/tests.md';
    expect(is_file($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('paths:')
        ->toContain('tests/**')
        ->toContain('# Tests')
        ->toContain('Rule body for tests.');
});

it('wipes and regenerates managed files on every sync', function (): void {
    $this->repository->syncManaged(managedRuleFiles('tests', 'components'));

    expect(is_file($this->rulesDir . '/ignis/tests.md'))->toBeTrue();
    expect(is_file($this->rulesDir . '/ignis/components.md'))->toBeTrue();

    $this->repository->syncManaged(managedRuleFiles('tests'));

    expect(is_file($this->rulesDir . '/ignis/tests.md'))->toBeTrue();
    expect(is_file($this->rulesDir . '/ignis/components.md'))->toBeFalse();
});

it('leaves root user-recorded rule files untouched when syncing managed rules', function (): void {
    recordManagedRulesTestRule($this->repository, 'src/Controller/**', 'Team rule', 'A team-recorded rule.');

    $this->repository->syncManaged(managedRuleFiles('tests'));

    expect(is_file($this->rulesDir . '/controllers.md') || is_file($this->rulesDir . '/controller.md') || count(featureRuleFiles($this->rulesDir)) >= 1)->toBeTrue();

    $rootFiles = featureRuleFiles($this->rulesDir);
    expect($rootFiles)->not->toBeEmpty();
    expect(file_get_contents($rootFiles[0]))->toContain('Team rule');
    expect(is_file($this->rulesDir . '/ignis/tests.md'))->toBeTrue();
});

it('record-rule never appends into a managed rule file even with a matching glob', function (): void {
    $this->repository->syncManaged(managedRuleFiles('tests'));

    recordManagedRulesTestRule($this->repository, 'tests/**', 'Team testing note', 'A team addition.');

    $managed = file_get_contents($this->rulesDir . '/ignis/tests.md');
    expect($managed)->not->toContain('Team testing note');

    $rootFiles = array_map(basename(...), featureRuleFiles($this->rulesDir));
    expect($rootFiles)->toContain('tests.md');
    expect(file_get_contents($this->rulesDir . '/tests.md'))->toContain('Team testing note');
});

it('includes both root and managed rows in the index sorted by path', function (): void {
    recordManagedRulesTestRule($this->repository, 'src/Model/Entity/**', 'Team rule', 'note');

    $this->repository->syncManaged(managedRuleFiles('tests'));

    $index = file_get_contents($this->rulesDir . '/index.md');

    expect($index)
        ->toContain('.ai/rules/ignis/tests.md')
        ->toContain('.ai/rules/');
});

it('clearManaged removes the managed directory and regenerates the index when root rules remain', function (): void {
    recordManagedRulesTestRule($this->repository, 'src/Model/Entity/**', 'Team rule', 'note');

    $this->repository->syncManaged(managedRuleFiles('tests'));

    $removed = $this->repository->clearManaged();

    expect($removed)->toBeTrue();
    expect(is_dir($this->rulesDir . '/ignis'))->toBeFalse();

    $index = file_get_contents($this->rulesDir . '/index.md');
    expect($index)
        ->not->toContain('.ai/rules/ignis');
});

it('clearManaged removes the whole rules directory when nothing else remains', function (): void {
    $this->repository->syncManaged(managedRuleFiles('tests'));

    $removed = $this->repository->clearManaged();

    expect($removed)->toBeTrue();
    expect(is_dir($this->rulesDir))->toBeFalse();
});

it('clearManaged is a no-op when there is nothing managed', function (): void {
    expect($this->repository->clearManaged())->toBeFalse();
    expect(is_dir($this->rulesDir))->toBeFalse();
});

it('syncManaged with an empty collection clears any previously managed files', function (): void {
    $this->repository->syncManaged(managedRuleFiles('tests'));
    expect(is_file($this->rulesDir . '/ignis/tests.md'))->toBeTrue();

    $written = $this->repository->syncManaged(new Collection([]));

    expect($written)->toBe([]);
    expect(is_dir($this->rulesDir . '/ignis'))->toBeFalse();
});

it('syncManaged with an empty collection removes the whole rules directory when nothing else remains', function (): void {
    expect(is_dir($this->rulesDir))->toBeFalse();

    $this->repository->syncManaged(new Collection([]));

    expect(is_dir($this->rulesDir))->toBeFalse();
});

it('syncManaged with an empty collection keeps the index when root rules remain', function (): void {
    recordManagedRulesTestRule($this->repository, 'src/Model/Entity/**', 'Team rule', 'note');

    $this->repository->syncManaged(new Collection([]));

    expect(is_dir($this->rulesDir))->toBeTrue();
    expect(file_get_contents($this->rulesDir . '/index.md'))->toContain('.ai/rules/');
});
