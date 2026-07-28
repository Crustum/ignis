<?php

declare(strict_types=1);

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Ignis\Skills\Remote\GitHubRepository;
use Crustum\Ignis\Skills\Remote\RemoteSkill;

/**
 * @param \Cake\Collection\Collection<string, \Crustum\Ignis\Skills\Remote\RemoteSkill> $skills Indexed skills
 * @param string $name Skill name
 * @return \Crustum\Ignis\Skills\Remote\RemoteSkill|null
 */
function remoteSkillNamed(Collection $skills, string $name): ?RemoteSkill
{
    $items = $skills->toArray();

    $skill = $items[$name] ?? null;

    return $skill instanceof RemoteSkill ? $skill : null;
}

afterEach(function (): void {
    Configure::delete('Ignis.github.token');
});

it('discovers skills from repository directories', function (): void {
    $history = [];
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([
        ['path' => 'skill-one', 'mode' => '040000', 'type' => 'tree', 'sha' => 'def'],
        ['path' => 'skill-one/SKILL.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
        ['path' => 'skill-two', 'mode' => '040000', 'type' => 'tree', 'sha' => 'jkl'],
        ['path' => 'skill-two/SKILL.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'mno', 'size' => 456],
        ['path' => 'README.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'pqr', 'size' => 789],
    ]), $history);
    $skills = $fetcher->discoverSkills();

    expect($skills)->toHaveCount(2)
        ->and(remoteSkillNamed($skills, 'skill-one'))->not->toBeNull()
        ->and(remoteSkillNamed($skills, 'skill-two'))->not->toBeNull()
        ->and(remoteSkillNamed($skills, 'skill-one'))->toBeInstanceOf(RemoteSkill::class)
        ->and(remoteSkillNamed($skills, 'skill-one')->name)->toBe('skill-one')
        ->and(remoteSkillNamed($skills, 'skill-two')->name)->toBe('skill-two');

    expect($history)->toHaveCount(2);
});

it('skips directories without SKILL.md', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([
        ['path' => 'valid-skill', 'type' => 'tree', 'sha' => 'def'],
        ['path' => 'valid-skill/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
        ['path' => 'no-skill-file', 'type' => 'tree', 'sha' => 'jkl'],
        ['path' => 'no-skill-file/README.md', 'type' => 'blob', 'sha' => 'mno', 'size' => 456],
    ]));
    $skills = $fetcher->discoverSkills();

    expect($skills)->toHaveCount(1)
        ->and(remoteSkillNamed($skills, 'valid-skill'))->not->toBeNull()
        ->and(remoteSkillNamed($skills, 'no-skill-file'))->toBeNull();
});

it('throws exception when api fails with 404', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), [
        githubDefaultBranchResponse(),
        githubJsonResponse(404, ['message' => 'Not Found']),
    ]);

    expect(fn (): Collection => $fetcher->discoverSkills())
        ->toThrow(RuntimeException::class, 'Failed to fetch repository tree from GitHub: Not Found (HTTP 404)');
});

it('downloads skill files to target directory', function (): void {
    $targetDir = sys_get_temp_dir() . '/ignis-test-' . uniqid();

    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), [
        ...githubDiscoverResponses([
            ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
            ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
            ['path' => 'skill-one/README.md', 'type' => 'blob', 'sha' => 'jkl', 'size' => 456],
        ]),
        githubRawFileResponse('# SKILL Content'),
        githubRawFileResponse('# README Content'),
    ]);

    $skill = new RemoteSkill(
        name: 'skill-one',
        repo: 'owner/repo',
        path: 'skill-one',
    );

    $result = $fetcher->downloadSkill($skill, $targetDir);

    expect($result)->toBeTrue()
        ->and($targetDir . '/SKILL.md')->toBeFile()
        ->and($targetDir . '/README.md')->toBeFile()
        ->and(file_get_contents($targetDir . '/SKILL.md'))->toBe('# SKILL Content')
        ->and(file_get_contents($targetDir . '/README.md'))->toBe('# README Content');

    array_map(unlink(...), glob($targetDir . '/*'));
    rmdir($targetDir);
});

it('downloads nested directory structure', function (): void {
    $targetDir = sys_get_temp_dir() . '/ignis-test-' . uniqid();

    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), [
        ...githubDiscoverResponses([
            ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
            ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
            ['path' => 'skill-one/examples', 'type' => 'tree', 'sha' => 'jkl'],
            ['path' => 'skill-one/examples/example.md', 'type' => 'blob', 'sha' => 'mno', 'size' => 456],
        ]),
        githubRawFileResponse('# SKILL'),
        githubRawFileResponse('# Example'),
    ]);

    $skill = new RemoteSkill(
        name: 'skill-one',
        repo: 'owner/repo',
        path: 'skill-one',
    );

    $result = $fetcher->downloadSkill($skill, $targetDir);

    expect($result)->toBeTrue()
        ->and($targetDir . '/SKILL.md')->toBeFile()
        ->and($targetDir . '/examples/example.md')->toBeFile();

    @unlink($targetDir . '/examples/example.md');
    @rmdir($targetDir . '/examples');
    @unlink($targetDir . '/SKILL.md');
    @rmdir($targetDir);
});

it('rejects remote tree paths that escape the skill target with parent segments', function (): void {
    $parentDir = sys_get_temp_dir() . '/ignis-jail-parent-' . uniqid();
    $targetDir = $parentDir . '/skill-target';
    $escapeMarker = $parentDir . '/escaped.md';

    mkdir($targetDir, 0755, true);

    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), [
        ...githubDiscoverResponses([
            ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
            ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
            ['path' => 'skill-one/../escaped.md', 'type' => 'blob', 'sha' => 'jkl', 'size' => 456],
        ]),
        githubRawFileResponse('# SKILL'),
        githubRawFileResponse('# ESCAPED'),
    ]);

    $skill = new RemoteSkill(
        name: 'skill-one',
        repo: 'owner/repo',
        path: 'skill-one',
    );

    $result = $fetcher->downloadSkill($skill, $targetDir);

    expect($result)->toBeFalse()
        ->and($escapeMarker)->not->toBeFile();

    @unlink($targetDir . '/SKILL.md');
    @rmdir($targetDir);
    @rmdir($parentDir);
});

it('returns false when skill path not in tree', function (): void {
    $targetDir = sys_get_temp_dir() . '/ignis-test-' . uniqid();

    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([
        ['path' => 'other-skill', 'type' => 'tree', 'sha' => 'def'],
    ]));

    $skill = new RemoteSkill(
        name: 'skill-one',
        repo: 'owner/repo',
        path: 'skill-one',
    );

    $result = $fetcher->downloadSkill($skill, $targetDir);

    expect($result)->toBeFalse();

    if (is_dir($targetDir)) {
        @rmdir($targetDir);
    }
});

it('handles empty repository', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([]));
    $skills = $fetcher->discoverSkills();

    expect($skills)->toBeEmpty();
});

it('ignores files at root level', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([
        ['path' => 'README.md', 'type' => 'blob', 'sha' => 'def', 'size' => 123],
        ['path' => 'LICENSE', 'type' => 'blob', 'sha' => 'ghi', 'size' => 456],
        ['path' => '.gitignore', 'type' => 'blob', 'sha' => 'jkl', 'size' => 789],
    ]));
    $skills = $fetcher->discoverSkills();

    expect($skills)->toBeEmpty();
});

it('caches tree for multiple operations', function (): void {
    $targetDir = sys_get_temp_dir() . '/ignis-test-' . uniqid();
    $history = [];

    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), [
        ...githubDiscoverResponses([
            ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
            ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
        ]),
        githubRawFileResponse('# Content'),
    ], $history);

    $skills = $fetcher->discoverSkills();
    $fetcher->downloadSkill($skills->first(), $targetDir);

    expect(githubHistoryUriCount($history, 'git/trees'))->toBe(1);

    @unlink($targetDir . '/SKILL.md');
    @rmdir($targetDir);
});

it('handles truncated tree response', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([
        ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
        ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
    ], truncated: true));
    $skills = $fetcher->discoverSkills();

    expect($skills)->toBeInstanceOf(Collection::class)
        ->and($skills)->toHaveCount(1);
});

it('discovers skills in nested paths like .ai/skills', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([
        ['path' => '.ai', 'type' => 'tree', 'sha' => 'aaa'],
        ['path' => '.ai/skills', 'type' => 'tree', 'sha' => 'bbb'],
        ['path' => '.ai/skills/my-skill', 'type' => 'tree', 'sha' => 'ccc'],
        ['path' => '.ai/skills/my-skill/SKILL.md', 'type' => 'blob', 'sha' => 'ddd', 'size' => 123],
    ]));
    $skills = $fetcher->discoverSkills();

    expect($skills)->toHaveCount(1)
        ->and(remoteSkillNamed($skills, 'my-skill'))->not->toBeNull();
});

it('throws exception when rate limit is exceeded', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), [
        githubDefaultBranchResponse(),
        githubJsonResponse(403, ['message' => 'API rate limit exceeded'], [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string)(time() + 3600),
        ]),
    ]);

    expect(fn (): Collection => $fetcher->discoverSkills())
        ->toThrow(RuntimeException::class, 'GitHub API rate limit exceeded');
});

it('throws exception on invalid response structure', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), [
        githubDefaultBranchResponse(),
        githubJsonResponse(200, ['invalid' => 'structure']),
    ]);

    expect(fn (): Collection => $fetcher->discoverSkills())
        ->toThrow(RuntimeException::class, 'Invalid response structure from GitHub Tree API');
});

it('uses specified repository path when provided', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo', 'custom/path'), githubDiscoverResponses([
        ['path' => 'custom/path', 'type' => 'tree', 'sha' => 'aaa'],
        ['path' => 'custom/path/my-skill', 'type' => 'tree', 'sha' => 'bbb'],
        ['path' => 'custom/path/my-skill/SKILL.md', 'type' => 'blob', 'sha' => 'ccc', 'size' => 123],
    ]));
    $skills = $fetcher->discoverSkills();

    expect($skills)->toHaveCount(1)
        ->and(remoteSkillNamed($skills, 'my-skill'))->not->toBeNull()
        ->and(remoteSkillNamed($skills, 'my-skill')->path)->toBe('custom/path/my-skill');
});

it('uses ignis.github.token for authentication when available', function (): void {
    Configure::write('Ignis.github.token', 'test-token-123');

    $history = [];
    $fetcher = githubAuthProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([
        ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
        ['path' => 'skill-one/SKILL.md', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
    ]), $history);
    $fetcher->discoverSkills();

    expect(githubHistoryHasAuthorization($history, 'test-token-123'))->toBeTrue();
});

it('discovers skills in resources/ignis/skills path', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([
        ['path' => 'resources', 'type' => 'tree', 'sha' => 'aaa'],
        ['path' => 'resources/ignis', 'type' => 'tree', 'sha' => 'bbb'],
        ['path' => 'resources/ignis/skills', 'type' => 'tree', 'sha' => 'ccc'],
        ['path' => 'resources/ignis/skills/my-skill', 'type' => 'tree', 'sha' => 'ddd'],
        ['path' => 'resources/ignis/skills/my-skill/SKILL.md', 'type' => 'blob', 'sha' => 'eee', 'size' => 123],
    ]));
    $skills = $fetcher->discoverSkills();

    expect($skills)->toHaveCount(1)
        ->and(remoteSkillNamed($skills, 'my-skill'))->not->toBeNull()
        ->and(remoteSkillNamed($skills, 'my-skill')->path)->toBe('resources/ignis/skills/my-skill');
});

it('resolves non-main default branch from github api', function (): void {
    $history = [];
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([
        ['path' => 'resources', 'type' => 'tree', 'sha' => 'aaa'],
        ['path' => 'resources/ignis', 'type' => 'tree', 'sha' => 'bbb'],
        ['path' => 'resources/ignis/skills', 'type' => 'tree', 'sha' => 'ccc'],
        ['path' => 'resources/ignis/skills/my-skill', 'type' => 'tree', 'sha' => 'ddd'],
        ['path' => 'resources/ignis/skills/my-skill/SKILL.md', 'type' => 'blob', 'sha' => 'eee', 'size' => 123],
    ], '0.x'), $history);
    $skills = $fetcher->discoverSkills();

    expect($skills)->toHaveCount(1)
        ->and(remoteSkillNamed($skills, 'my-skill'))->not->toBeNull()
        ->and(githubHistoryContainsUri($history, 'git/trees/0.x'))->toBeTrue();
});

it('url-encodes branch names containing slashes', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), [
        githubDefaultBranchResponse('release/1.x'),
        githubTreeResponse([
            ['path' => 'my-skill', 'type' => 'tree', 'sha' => 'aaa'],
            ['path' => 'my-skill/SKILL.md', 'type' => 'blob', 'sha' => 'bbb', 'size' => 123],
        ]),
    ]);
    $skills = $fetcher->discoverSkills();

    expect($skills)->toHaveCount(1)
        ->and(remoteSkillNamed($skills, 'my-skill'))->not->toBeNull();
});

it('discovers skills with SKILL.twig marker', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([
        ['path' => 'skill-one', 'type' => 'tree', 'sha' => 'def'],
        ['path' => 'skill-one/SKILL.twig', 'type' => 'blob', 'sha' => 'ghi', 'size' => 123],
        ['path' => 'skill-two', 'type' => 'tree', 'sha' => 'jkl'],
        ['path' => 'skill-two/SKILL.md', 'type' => 'blob', 'sha' => 'mno', 'size' => 456],
    ]));
    $skills = $fetcher->discoverSkills();

    expect($skills)->toHaveCount(2)
        ->and(remoteSkillNamed($skills, 'skill-one'))->not->toBeNull()
        ->and(remoteSkillNamed($skills, 'skill-two'))->not->toBeNull()
        ->and(remoteSkillNamed($skills, 'skill-one')->name)->toBe('skill-one')
        ->and(remoteSkillNamed($skills, 'skill-two')->name)->toBe('skill-two');
});

it('discovers skills in wildcard paths like .ai/*/skills', function (): void {
    $fetcher = githubProvider(new GitHubRepository('owner', 'repo'), githubDiscoverResponses([
        ['path' => '.ai', 'type' => 'tree', 'sha' => 'aaa'],
        ['path' => '.ai/claude', 'type' => 'tree', 'sha' => 'bbb'],
        ['path' => '.ai/claude/skills', 'type' => 'tree', 'sha' => 'ccc'],
        ['path' => '.ai/claude/skills/my-skill', 'type' => 'tree', 'sha' => 'ddd'],
        ['path' => '.ai/claude/skills/my-skill/SKILL.md', 'type' => 'blob', 'sha' => 'eee', 'size' => 123],
    ]));
    $skills = $fetcher->discoverSkills();

    expect($skills)->toHaveCount(1)
        ->and(remoteSkillNamed($skills, 'my-skill'))->not->toBeNull()
        ->and(remoteSkillNamed($skills, 'my-skill')->path)->toBe('.ai/claude/skills/my-skill');
});
