<?php

declare(strict_types=1);

use Cake\Core\Configure;

beforeEach(function (): void {
    prepareAddSkillTestProject();
});

afterEach(function (): void {
    Configure::delete('Ignis.github.token');
    Configure::delete('Ignis.hosted.audit_url');
});

it('shows error for invalid repository format', function (): void {
    $this->exec('ignis add-skill invalid-format --no-interaction');

    $this->assertExitError();
    $this->assertErrorContains('Invalid repository format');
});

it('lists available skills with --list option', function (): void {
    bindAddSkillMocks($this, 'owner/repo', githubDiscoverResponses([
        ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
        ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
        ['path' => 'skill-two', 'type' => 'tree', 'sha' => 'jkl'],
        ['path' => 'skill-two/SKILL.md', 'type' => 'blob', 'sha' => 'mno', 'size' => 456],
    ]));

    $this->exec('ignis add-skill owner/repo --list --no-interaction');

    $this->assertExitSuccess();
});

it('shows error when no skills found', function (): void {
    bindAddSkillMocks($this, 'owner/repo', githubDiscoverResponses([]));

    $this->exec('ignis add-skill owner/repo --no-interaction');

    $this->assertExitError();
    $this->assertErrorContains('No valid skills are found');
});

it('shows error when api request fails', function (): void {
    bindAddSkillMocks($this, 'owner/repo', [
        githubDefaultBranchResponse(),
        githubJsonResponse(404, ['message' => 'Not Found']),
    ]);

    $this->exec('ignis add-skill owner/repo --no-interaction');

    $this->assertExitError();
    $this->assertErrorContains('Failed to fetch repository tree from GitHub');
});

it('installs all skills with --all option', function (): void {
    bindAddSkillMocks($this, 'owner/repo', [
        ...githubDiscoverResponses([
            ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
            ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
        ]),
        githubRawFileResponse(skillOneYaml()),
    ]);

    $this->exec('ignis add-skill owner/repo --all --no-interaction');

    $this->assertExitSuccess();
    assertSkillFilenameExists('.ai/skills/skill-one/SKILL.md');
    assertSkillFileContains(['# SKILL Content'], '.ai/skills/skill-one/SKILL.md');
});

it('installs specific skills with --skill option', function (): void {
    bindAddSkillMocks($this, 'owner/repo', [
        ...githubDiscoverResponses([
            ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
            ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
            ['path' => 'skill-two', 'type' => 'tree', 'sha' => 'jkl'],
            ['path' => 'skill-two/SKILL.md', 'type' => 'blob', 'sha' => 'mno', 'size' => 456],
        ]),
        githubRawFileResponse(skillOneYaml()),
    ]);

    $this->exec('ignis add-skill owner/repo --skill skill-one --no-interaction');

    $this->assertExitSuccess();
    assertSkillFilenameExists('.ai/skills/skill-one/SKILL.md');
    assertSkillFilenameNotExists('.ai/skills/skill-two/SKILL.md');
});

it('skips existing skills without --force flag', function (): void {
    writeSkillFile('.ai/skills/skill-one/SKILL.md', 'existing content');

    bindAddSkillMocks($this, 'owner/repo', githubDiscoverResponses([
        ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
        ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
    ]));

    $this->exec('ignis add-skill owner/repo --all --no-interaction');

    $this->assertExitSuccess();
    assertSkillFileContains(['existing content'], '.ai/skills/skill-one/SKILL.md');
});

it('overwrites existing skills with --force flag', function (): void {
    writeSkillFile('.ai/skills/skill-one/SKILL.md', 'existing content');

    $newContent = <<<'YAML'
---
name: skill-one
description: First skill
---
# New Content
YAML;

    bindAddSkillMocks($this, 'owner/repo', [
        ...githubDiscoverResponses([
            ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
            ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
        ]),
        githubRawFileResponse($newContent),
    ]);

    $this->exec('ignis add-skill owner/repo --all --force --no-interaction');

    $this->assertExitSuccess();
    assertSkillFileContains(['# New Content'], '.ai/skills/skill-one/SKILL.md');
    assertSkillFileNotContains(['existing content'], '.ai/skills/skill-one/SKILL.md');
});

it('installs nested skill files correctly', function (): void {
    bindAddSkillMocks($this, 'owner/repo', [
        ...githubDiscoverResponses([
            ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
            ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
            ['path' => 'skill-one/examples', 'type' => 'tree', 'sha' => 'jkl'],
            ['path' => 'skill-one/examples/example.md', 'type' => 'blob', 'sha' => 'mno', 'size' => 456],
        ]),
        githubRawFileResponse(<<<'YAML'
---
name: skill-one
description: First skill
---
# SKILL
YAML),
        githubRawFileResponse('# Example content'),
    ]);

    $this->exec('ignis add-skill owner/repo --all --no-interaction');

    $this->assertExitSuccess();
    assertSkillFilenameExists('.ai/skills/skill-one/SKILL.md');
    assertSkillFilenameExists('.ai/skills/skill-one/examples/example.md');
    assertSkillFileContains(['# SKILL'], '.ai/skills/skill-one/SKILL.md');
    assertSkillFileContains(['# Example content'], '.ai/skills/skill-one/examples/example.md');
});

it('shows success message after installing skills', function (): void {
    bindAddSkillMocks($this, 'owner/repo', [
        ...githubDiscoverResponses([
            ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
            ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
        ]),
        githubRawFileResponse(skillOneYaml()),
    ]);

    $this->exec('ignis add-skill owner/repo --all --no-interaction');

    $this->assertExitSuccess();
    $this->assertOutputContains('Skills installed');
});

it('shows available skill count when listing', function (): void {
    bindAddSkillMocks($this, 'owner/repo', githubDiscoverResponses([
        ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
        ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
        ['path' => 'skill-two', 'type' => 'tree', 'sha' => 'jkl'],
        ['path' => 'skill-two/SKILL.md', 'type' => 'blob', 'sha' => 'mno', 'size' => 456],
    ]));

    $this->exec('ignis add-skill owner/repo --list --no-interaction');

    $this->assertExitSuccess();
    $this->assertOutputContains('Found 2 available skills');
})->skip(fn (): bool => isPreferLowestCi(), preferLowestPromptsStdoutSkipReason());

it('displays audit results before installing skills when risk is medium or higher', function (): void {
    $auditHistory = [];

    bindAddSkillMocks(
        $this,
        'owner/repo',
        [
            ...githubDiscoverResponses([
                ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
                ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
            ]),
            githubRawFileResponse(skillOneYaml()),
        ],
        [
            githubJsonResponse(200, [
                'skill-one' => [
                    'ath' => ['risk' => 'medium', 'analyzedAt' => '2025-01-01T00:00:00Z'],
                    'socket' => ['risk' => 'low', 'alerts' => 2, 'analyzedAt' => '2025-01-01T00:00:00Z'],
                ],
            ]),
        ],
        auditHistory: $auditHistory,
    );

    $this->exec('ignis add-skill owner/repo --all --no-interaction');

    $this->assertExitSuccess();
    $this->assertOutputContains('Security Audit');
    $this->assertOutputContains('Skills installed');
    expect(auditHistoryContainsUri($auditHistory, 'ignis.test/api/v1/skills/audit'))->toBeTrue();
})->skip(fn (): bool => isPreferLowestCi(), preferLowestPromptsStdoutSkipReason());

it('skips audit display when all skills are safe or low risk', function (): void {
    bindAddSkillMocks(
        $this,
        'owner/repo',
        [
            ...githubDiscoverResponses([
                ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
                ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
            ]),
            githubRawFileResponse(skillOneYaml()),
        ],
        [
            githubJsonResponse(200, [
                'skill-one' => [
                    'ath' => ['risk' => 'safe', 'analyzedAt' => '2025-01-01T00:00:00Z'],
                    'socket' => ['risk' => 'low', 'alerts' => 2, 'analyzedAt' => '2025-01-01T00:00:00Z'],
                ],
            ]),
        ],
    );

    $this->exec('ignis add-skill owner/repo --all --no-interaction');

    $this->assertExitSuccess();
    $this->assertOutputNotContains('Security Audit');
    $this->assertOutputContains('Skills installed');
});

it('skips audit when --skip-audit flag is used', function (): void {
    $auditHistory = [];

    bindAddSkillMocks(
        $this,
        'owner/repo',
        [
            ...githubDiscoverResponses([
                ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
                ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
            ]),
            githubRawFileResponse(skillOneYaml()),
        ],
        [],
        auditHistory: $auditHistory,
    );

    $this->exec('ignis add-skill owner/repo --all --skip-audit --no-interaction');

    $this->assertExitSuccess();
    $this->assertOutputContains('Skills installed');
    expect(auditHistoryContainsUri($auditHistory, 'ignis.test/api/v1/skills/audit'))->toBeFalse();
});

it('blocks install when no audit url is configured without --skip-audit', function (): void {
    bindAddSkillMocks(
        $this,
        'owner/repo',
        [
            ...githubDiscoverResponses([
                ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
                ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
            ]),
            githubRawFileResponse(skillOneYaml()),
        ],
        null,
    );

    Configure::delete('Ignis.hosted.audit_url');

    $this->exec('ignis add-skill owner/repo --all --no-interaction');

    $this->assertExitError();
    $this->assertErrorContains('No remote skill auditor is configured');
    $this->assertErrorContains('--skip-audit');
    assertSkillFilenameNotExists('.ai/skills/skill-one/SKILL.md');
});

it('succeeds when audit api fails', function (): void {
    bindAddSkillMocks(
        $this,
        'owner/repo',
        [
            ...githubDiscoverResponses([
                ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
                ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
            ]),
            githubRawFileResponse(skillOneYaml()),
        ],
        [githubJsonResponse(500, [])],
    );

    $this->exec('ignis add-skill owner/repo --all --no-interaction');

    $this->assertExitSuccess();
    $this->assertOutputNotContains('Security Audit');
    $this->assertOutputContains('Skills installed');
    assertSkillFilenameExists('.ai/skills/skill-one/SKILL.md');
});

it('sends correct source and skills to audit api before download', function (): void {
    $auditHistory = [];

    bindAddSkillMocks(
        $this,
        'owner/repo/path/to/skills',
        [
            ...githubDiscoverResponses([
                ['path' => 'path/to/skills/skill-one', 'type' => 'tree', 'sha' => 'def'],
                ['path' => 'path/to/skills/skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
                ['path' => 'path/to/skills/skill-two', 'type' => 'tree', 'sha' => 'jkl'],
                ['path' => 'path/to/skills/skill-two/SKILL.md', 'type' => 'blob', 'sha' => 'mno', 'size' => 456],
            ]),
            githubRawFileResponse(<<<'YAML'
---
name: skill
description: A skill
---
# Content
YAML),
            githubRawFileResponse(<<<'YAML'
---
name: skill
description: A skill
---
# Content
YAML),
        ],
        [githubJsonResponse(200, [])],
        auditHistory: $auditHistory,
    );

    $this->exec('ignis add-skill owner/repo/path/to/skills --all --no-interaction');

    $this->assertExitSuccess();
    expect(auditHistoryContainsUri($auditHistory, 'source=owner%2Frepo%2Fpath%2Fto%2Fskills'))->toBeTrue();
    expect(auditHistoryContainsUri($auditHistory, 'skills='))->toBeTrue();
});

it('audits only skills that will be installed', function (): void {
    writeSkillFile('.ai/skills/skill-one/SKILL.md', 'existing content');
    $auditHistory = [];

    bindAddSkillMocks(
        $this,
        'owner/repo',
        [
            ...githubDiscoverResponses([
                ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
                ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
                ['path' => 'skill-two', 'type' => 'tree', 'sha' => 'jkl'],
                ['path' => 'skill-two/SKILL.md', 'type' => 'blob', 'sha' => 'mno', 'size' => 456],
            ]),
            githubRawFileResponse(skillTwoYaml()),
        ],
        [githubJsonResponse(200, [])],
        auditHistory: $auditHistory,
    );

    $this->exec('ignis add-skill owner/repo --all --no-interaction');

    $this->assertExitSuccess();
    expect(auditHistoryContainsUri($auditHistory, 'skills=skill-two'))->toBeTrue();
    expect(auditHistoryContainsUri($auditHistory, 'skill-one'))->toBeFalse();
});

it('displays error when rate limit is exceeded', function (): void {
    bindAddSkillMocks($this, 'owner/repo', [
        githubDefaultBranchResponse(),
        githubJsonResponse(403, ['message' => 'API rate limit exceeded'], [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string)(time() + 3600),
        ]),
    ]);

    $this->exec('ignis add-skill owner/repo --no-interaction');

    $this->assertExitError();
    $this->assertErrorContains('GitHub API rate limit exceeded');
});
