<?php

declare(strict_types=1);

use Cake\Core\Configure;

beforeEach(function (): void {
    Configure::write('Ignis.skills.exclude', [
        'infer-conventions',
        'mcp-development',
        'pest-testing',
        'tessera-development',
        'cakephp-best-practices',
        'rhythm-development',
    ]);
});

afterEach(function (): void {
    Configure::delete('Ignis.skills.exclude');
});

it('lists available skills', function (): void {
    ensureDirectoryExists(base_path('.ai/skills/skill-one'));
    file_put_contents(base_path('.ai/skills/skill-one/SKILL.md'), "---\nname: skill-one\ndescription: First skill\n---\n\n# Skill One Content\n");

    ensureDirectoryExists(base_path('.ai/skills/skill-two'));
    file_put_contents(base_path('.ai/skills/skill-two/SKILL.md'), "---\nname: skill-two\ndescription: Second skill\n---\n\n# Skill Two Content\n");

    $this->exec('ignis list-skills');

    $this->assertExitSuccess();
    $this->assertOutputContains('Found 2 skills');
})->skip(fn (): bool => isPreferLowestCi(), preferLowestPromptsStdoutSkipReason());

it('shows message when no skills available', function (): void {
    $this->exec('ignis list-skills');

    $this->assertExitSuccess();
    $this->assertOutputContains('No skills available in this project.');
});

it('lists the shipped infer-conventions skill by default', function (): void {
    Configure::write('Ignis.skills.exclude', [
        'mcp-development',
        'pest-testing',
        'tessera-development',
        'cakephp-best-practices',
        'rhythm-development',
    ]);

    $this->exec('ignis list-skills');

    $this->assertExitSuccess();
    $this->assertOutputContains('infer-conventions');
})->skip(fn (): bool => isPreferLowestCi(), preferLowestPromptsStdoutSkipReason());

it('shows user-defined skills with local source', function (): void {
    ensureDirectoryExists(base_path('.ai/skills/my-custom-skill'));
    file_put_contents(base_path('.ai/skills/my-custom-skill/SKILL.md'), "---\nname: my-custom-skill\ndescription: My custom skill\n---\n\n# Content\n");

    $this->exec('ignis list-skills');

    $this->assertExitSuccess();
    $this->assertOutputContains('local');
})->skip(fn (): bool => isPreferLowestCi(), preferLowestPromptsStdoutSkipReason());
