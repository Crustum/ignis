<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Skills\Remote\AuditResult;
use Crustum\Ignis\Skills\Remote\Risk;
use Crustum\Ignis\Skills\Remote\SkillAuditor;

beforeEach(function (): void {
    Configure::write('Ignis.hosted.audit_url', ignisTestAuditUrl());
});

afterEach(function (): void {
    Configure::delete('Ignis.hosted.audit_url');
});

it('reports configured when audit url is set', function (): void {
    expect((new SkillAuditor())->isConfigured())->toBeTrue();
});

it('reports not configured when audit url is unset', function (): void {
    Configure::delete('Ignis.hosted.audit_url');

    expect((new SkillAuditor())->isConfigured())->toBeFalse();
});

it('returns empty array without http when audit url is unset', function (): void {
    Configure::delete('Ignis.hosted.audit_url');
    $history = [];
    $auditor = new SkillAuditor(githubMockClient([
        githubJsonResponse(200, [
            'skill-one' => [
                'ath' => ['risk' => 'safe'],
            ],
        ]),
    ], $history));

    expect($auditor->isConfigured())->toBeFalse()
        ->and($auditor->audit('owner/repo', ['skill-one']))->toBe([])
        ->and($history)->toBeEmpty();
});

it('returns audit results for skills', function (): void {
    $auditor = skillAuditorWithResponses([
        githubJsonResponse(200, [
            'skill-one' => [
                'ath' => ['risk' => 'safe', 'analyzedAt' => '2025-01-01T00:00:00Z'],
                'socket' => ['risk' => 'low', 'alerts' => 2, 'analyzedAt' => '2025-01-01T00:00:00Z'],
                'snyk' => ['risk' => 'safe', 'analyzedAt' => '2025-01-01T00:00:00Z'],
            ],
        ]),
    ]);
    $results = $auditor->audit('owner/repo', ['skill-one']);

    expect($results)->toHaveKey('skill-one')
        ->and($results['skill-one'])->toHaveCount(3)
        ->and($results['skill-one'][0])->toBeInstanceOf(AuditResult::class)
        ->and($results['skill-one'][0]->partner)->toBe('ath')
        ->and($results['skill-one'][0]->risk)->toBe(Risk::Safe)
        ->and($results['skill-one'][1]->partner)->toBe('socket')
        ->and($results['skill-one'][1]->risk)->toBe(Risk::Low)
        ->and($results['skill-one'][1]->alerts)->toBe(2)
        ->and($results['skill-one'][2]->partner)->toBe('snyk')
        ->and($results['skill-one'][2]->risk)->toBe(Risk::Safe);
});

it('returns audit results for multiple skills', function (): void {
    $auditor = skillAuditorWithResponses([
        githubJsonResponse(200, [
            'skill-one' => [
                'ath' => ['risk' => 'safe', 'analyzedAt' => '2025-01-01T00:00:00Z'],
            ],
            'skill-two' => [
                'ath' => ['risk' => 'high', 'analyzedAt' => '2025-01-01T00:00:00Z'],
            ],
        ]),
    ]);
    $results = $auditor->audit('owner/repo', ['skill-one', 'skill-two']);

    expect($results)->toHaveCount(2)
        ->and($results)->toHaveKey('skill-one')
        ->and($results)->toHaveKey('skill-two')
        ->and($results['skill-two'][0]->risk)->toBe(Risk::High);
});

it('sends correct query parameters', function (): void {
    $history = [];
    $auditor = skillAuditorWithResponses([
        githubJsonResponse(200, []),
    ], $history);
    $auditor->audit('owner/repo', ['skill-one', 'skill-two']);

    expect($history)->not->toBeEmpty();

    $uri = (string)$history[0]['request']->getUri();

    expect($uri)->toContain('ignis.test/api/v1/skills/audit')
        ->and($uri)->toContain('source=owner%2Frepo')
        ->and($uri)->toContain('skills=skill-one%2Cskill-two');
});

it('returns empty array on network failure', function (): void {
    $auditor = skillAuditorWithResponses([
        githubJsonResponse(500, []),
    ]);
    $results = $auditor->audit('owner/repo', ['skill-one']);

    expect($results)->toBe([]);
});

it('returns empty array on connection timeout', function (): void {
    $auditor = skillAuditorWithResponses([
        githubConnectException('Connection timed out'),
    ]);
    $results = $auditor->audit('owner/repo', ['skill-one']);

    expect($results)->toBe([]);
});

it('returns empty array on malformed response', function (): void {
    $auditor = skillAuditorWithResponses([
        githubJsonResponse(200, 'not json'),
    ]);
    $results = $auditor->audit('owner/repo', ['skill-one']);

    expect($results)->toBe([]);
});

it('skips partner entries without risk field', function (): void {
    $auditor = skillAuditorWithResponses([
        githubJsonResponse(200, [
            'skill-one' => [
                'ath' => ['risk' => 'safe', 'analyzedAt' => '2025-01-01T00:00:00Z'],
                'socket' => ['analyzedAt' => '2025-01-01T00:00:00Z'],
            ],
        ]),
    ]);
    $results = $auditor->audit('owner/repo', ['skill-one']);

    expect($results['skill-one'])->toHaveCount(1)
        ->and($results['skill-one'][0]->partner)->toBe('ath');
});

it('skips non-array partner data', function (): void {
    $auditor = skillAuditorWithResponses([
        githubJsonResponse(200, [
            'skill-one' => 'invalid',
        ]),
    ]);
    $results = $auditor->audit('owner/repo', ['skill-one']);

    expect($results)->toBe([]);
});
