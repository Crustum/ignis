<?php

declare(strict_types=1);

it('regenerates a conflicted index from the rule files', function (): void {
    ensureDirectoryExists(base_path('.ai/rules/ignis'));
    file_put_contents(base_path('.ai/rules/models.md'), "---\npaths:\n  - src/Model/**\n---\n\n# Models\n");
    file_put_contents(base_path('.ai/rules/ignis/tests.md'), "---\npaths:\n  - tests/**\n---\n\n# Tests\n");
    file_put_contents(base_path('.ai/rules/index.md'), "<<<<<<< HEAD\n| a | b |\n=======\n| c | d |\n>>>>>>> feature\n");

    $this->exec('ignis index-rules');

    $this->assertExitSuccess();
    $this->assertOutputContains('Regenerated .ai/rules/index.md');

    $contents = (string)file_get_contents(base_path('.ai/rules/index.md'));

    expect($contents)
        ->not->toContain('<<<<<<<')
        ->toContain('| src/Model/** | .ai/rules/models.md |')
        ->toContain('| tests/** | .ai/rules/ignis/tests.md |');
});

it('refuses to regenerate while rule files contain conflict markers', function (): void {
    ensureDirectoryExists(base_path('.ai/rules'));
    file_put_contents(base_path('.ai/rules/models.md'), "---\npaths:\n<<<<<<< HEAD\n  - src/Model/**\n=======\n  - src/Model/*.php\n>>>>>>> feature\n---\n\n# Models\n");
    file_put_contents(base_path('.ai/rules/index.md'), 'original');

    $this->exec('ignis index-rules');

    $this->assertExitError();
    $this->assertOutputContains('.ai/rules/models.md');

    expect((string)file_get_contents(base_path('.ai/rules/index.md')))->toBe('original');
});

it('refuses to regenerate while managed rule files contain conflict markers', function (): void {
    ensureDirectoryExists(base_path('.ai/rules/ignis'));
    file_put_contents(base_path('.ai/rules/ignis/tests.md'), "---\npaths:\n  - tests/**\n---\n\n# Tests\n>>>>>>> feature\n");

    $this->exec('ignis index-rules');

    $this->assertExitError();
    $this->assertOutputContains('.ai/rules/ignis/tests.md');

    expect(is_file(base_path('.ai/rules/index.md')))->toBeFalse();
});

it('warns about rule files with invalid frontmatter', function (): void {
    ensureDirectoryExists(base_path('.ai/rules'));
    file_put_contents(base_path('.ai/rules/models.md'), "---\npaths:\n  - src/Model/**\n paths:\n  - src/*.php\n---\n\n# Models\n");

    $this->exec('ignis index-rules');

    $this->assertExitSuccess();
    $this->assertErrorContains('Skipped .ai/rules/models.md');
});

it('does not mistake a markdown heading underline for a conflict marker', function (): void {
    ensureDirectoryExists(base_path('.ai/rules'));
    file_put_contents(base_path('.ai/rules/models.md'), "---\npaths:\n  - src/Model/**\n---\n\nTesting\n=======\n");

    $this->exec('ignis index-rules');

    $this->assertExitSuccess();

    expect((string)file_get_contents(base_path('.ai/rules/index.md')))->toContain('| src/Model/** | .ai/rules/models.md |');
});

it('does nothing when there are no project rules', function (): void {
    $this->exec('ignis index-rules');

    $this->assertExitSuccess();
    $this->assertOutputContains('No project rules found');

    expect(is_dir(base_path('.ai/rules')))->toBeFalse();
});
