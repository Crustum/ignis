<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Mcp\Tools\RecordRule;
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

it('writes a rule into an area file with frontmatter and an entry', function (): void {
    $tool = new RecordRule($this->repository);

    $response = $tool->handle(new Request([
        'glob' => 'app/Http/Controllers/**',
        'title' => 'Extend BaseController for tenant scoping',
        'note' => 'New controllers must extend App\Http\Controllers\BaseController.',
    ]));

    expect($response)->isToolResult()->toolHasNoError()
        ->toolTextContains('controllers.md', 'Extend BaseController for tenant scoping');

    $file = $this->rulesDir . '/controllers.md';
    expect(is_file($file))->toBeTrue();

    $contents = file_get_contents($file);
    expect($contents)
        ->toContain('paths:')
        ->toContain('app/Http/Controllers/**')
        ->toContain('# Controllers')
        ->toContain('## Extend BaseController for tenant scoping');
});

it('groups a second rule for the same glob into the same file', function (): void {
    $tool = new RecordRule($this->repository);

    $tool->handle(new Request([
        'glob' => 'app/Http/Controllers/**',
        'title' => 'First',
        'note' => 'One.',
    ]));

    $tool->handle(new Request([
        'glob' => 'app/Http/Controllers/**',
        'title' => 'Form Requests over inline validation',
        'note' => 'Validate in Form Request classes.',
    ]));

    expect(featureRuleFiles($this->rulesDir))->toHaveCount(1);
    expect(file_get_contents($this->rulesDir . '/controllers.md'))
        ->toContain('## First')
        ->toContain('## Form Requests over inline validation');
});

it('merges a new glob into an existing area file without losing entries', function (): void {
    $tool = new RecordRule($this->repository);

    $tool->handle(new Request([
        'glob' => 'app/Models/**',
        'title' => 'First model note',
        'note' => 'Keep models thin.',
    ]));

    $tool->handle(new Request([
        'glob' => 'app/Models/*.php',
        'title' => 'Second model note',
        'note' => 'Watch the users table.',
    ]));

    expect(featureRuleFiles($this->rulesDir))->toHaveCount(1);

    $contents = file_get_contents($this->rulesDir . '/models.md');
    expect($contents)
        ->toContain('app/Models/**')
        ->toContain('app/Models/*.php')
        ->toContain('## First model note')
        ->toContain('Keep models thin.')
        ->toContain('## Second model note')
        ->toContain('Watch the users table.');
});

it('routes different globs to their own area files', function (): void {
    $tool = new RecordRule($this->repository);

    $tool->handle(new Request(['glob' => 'app/Http/Controllers/**', 'title' => 'A', 'note' => 'a']));
    $tool->handle(new Request(['glob' => 'app/Models/*.php', 'title' => 'B', 'note' => 'b']));

    expect(is_file($this->rulesDir . '/controllers.md'))->toBeTrue();
    expect(is_file($this->rulesDir . '/models.md'))->toBeTrue();
});

it('keeps distinct areas that share a last path segment in separate files', function (): void {
    $tool = new RecordRule($this->repository);

    $tool->handle(new Request(['glob' => 'app/Admin/Controllers/**', 'title' => 'Admin rule', 'note' => 'Admin only.']));
    $tool->handle(new Request(['glob' => 'app/Api/Controllers/**', 'title' => 'Api rule', 'note' => 'Api only.']));

    expect(featureRuleFiles($this->rulesDir))->toHaveCount(2);
    expect(is_file($this->rulesDir . '/controllers.md'))->toBeTrue();
    expect(is_file($this->rulesDir . '/api-controllers.md'))->toBeTrue();

    expect(file_get_contents($this->rulesDir . '/controllers.md'))
        ->toContain('app/Admin/Controllers/**')
        ->toContain('## Admin rule')
        ->not->toContain('app/Api/Controllers/**')
        ->not->toContain('## Api rule');

    expect(file_get_contents($this->rulesDir . '/api-controllers.md'))
        ->toContain('app/Api/Controllers/**')
        ->toContain('## Api rule')
        ->not->toContain('app/Admin/Controllers/**')
        ->not->toContain('## Admin rule');
});

it('keeps distinct single-segment filename globs in separate files', function (): void {
    $tool = new RecordRule($this->repository);

    $tool->handle(new Request(['glob' => 'composer.json', 'title' => 'Composer rule', 'note' => 'Composer only.']));
    $tool->handle(new Request(['glob' => '.env*', 'title' => 'Env rule', 'note' => 'Env only.']));
    $tool->handle(new Request(['glob' => '**', 'title' => 'Global rule', 'note' => 'Global only.']));

    expect(featureRuleFiles($this->rulesDir))->toHaveCount(3);

    expect(file_get_contents($this->rulesDir . '/composer-json.md'))
        ->toContain('composer.json')
        ->toContain('## Composer rule')
        ->not->toContain('.env*')
        ->not->toContain('## Env rule')
        ->not->toContain('## Global rule');
});

it('does not record a rule into the reserved index file', function (): void {
    $located = $this->repository->write('index/**', 'Index area rule', 'This must survive.');

    expect(basename((string)$located))->not->toBe('index.md');

    $index = file_get_contents($this->rulesDir . '/index.md');
    expect($index)
        ->toContain('# Project Rules Index')
        ->toContain('index/**')
        ->not->toContain('This must survive.');

    expect(file_get_contents($located))
        ->toContain('## Index area rule')
        ->toContain('This must survive.');
});

it('rejects a rule with a missing glob, title, or note', function (): void {
    $response = (new RecordRule($this->repository))->handle(new Request([
        'glob' => 'app/**',
        'title' => '',
        'note' => 'y',
    ]));

    expect($response)->isToolResult()->toolHasError()
        ->toolTextContains('non-empty glob, title, and note');
});

it('is gated by the rules enabled config flag', function (): void {
    Configure::write('Ignis.rules.enabled', false);
    expect((new RecordRule($this->repository))->shouldRegister())->toBeFalse();

    Configure::write('Ignis.rules.enabled', true);
    expect((new RecordRule($this->repository))->shouldRegister())->toBeTrue();
});

it('regenerates index.md mapping globs to rule files on every write', function (): void {
    $tool = new RecordRule($this->repository);

    $tool->handle(new Request(['glob' => 'app/Http/Controllers/**', 'title' => 'A', 'note' => 'a']));
    $tool->handle(new Request(['glob' => 'app/Models/*.php', 'title' => 'B', 'note' => 'b']));

    $index = $this->rulesDir . '/index.md';
    expect(is_file($index))->toBeTrue();
    expect(file_get_contents($index))
        ->toContain('app/Http/Controllers/**')
        ->toContain('.ai/rules/controllers.md')
        ->toContain('app/Models/*.php')
        ->toContain('.ai/rules/models.md');
});

it('excludes a rule file with no paths frontmatter from the index', function (): void {
    ensureDirectoryExists($this->rulesDir);
    file_put_contents($this->rulesDir . '/global.md', "# Global\n\n## Always use transactions\nWrap DB writes in a transaction.\n");

    (new RecordRule($this->repository))->handle(new Request([
        'glob' => 'app/Http/Controllers/**',
        'title' => 'A',
        'note' => 'a',
    ]));

    expect(file_get_contents($this->rulesDir . '/index.md'))
        ->toContain('.ai/rules/controllers.md')
        ->not->toContain('.ai/rules/global.md')
        ->not->toContain('entire project');
});

it('normalizes an absolute path glob to the same area as its relative twin', function (): void {
    $tool = new RecordRule($this->repository);

    $tool->handle(new Request(['glob' => 'app/Http/Controllers/**', 'title' => 'Relative', 'note' => 'a']));
    $tool->handle(new Request([
        'glob' => base_path('app/Http/Controllers/**'),
        'title' => 'Absolute',
        'note' => 'b',
    ]));

    expect(featureRuleFiles($this->rulesDir))->toHaveCount(1);
    expect(file_get_contents($this->rulesDir . '/controllers.md'))
        ->toContain('## Relative')
        ->toContain('## Absolute')
        ->not->toContain(base_path('app/Http/Controllers/**'));
});

it('writes a placeholder index when no rule files have paths', function (): void {
    $this->repository->writeIndex();

    expect(file_get_contents($this->rulesDir . '/index.md'))
        ->toContain('# Project Rules Index')
        ->toContain('No rules recorded yet.')
        ->not->toContain('| Applies to |');
});
