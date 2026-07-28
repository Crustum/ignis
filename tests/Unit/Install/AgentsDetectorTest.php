<?php

declare(strict_types=1);

use Cake\Collection\Collection;
use Crustum\Ignis\IgnisManager;
use Crustum\Ignis\Install\Agents\Agent;
use Crustum\Ignis\Install\Agents\Amp;
use Crustum\Ignis\Install\Agents\Antigravity;
use Crustum\Ignis\Install\Agents\ClaudeCode;
use Crustum\Ignis\Install\Agents\Codex;
use Crustum\Ignis\Install\Agents\Copilot;
use Crustum\Ignis\Install\Agents\Cursor;
use Crustum\Ignis\Install\Agents\Factory;
use Crustum\Ignis\Install\Agents\GrokBuild;
use Crustum\Ignis\Install\Agents\Junie;
use Crustum\Ignis\Install\Agents\Kiro;
use Crustum\Ignis\Install\Agents\OpenCode;
use Crustum\Ignis\Install\Agents\Pi;
use Crustum\Ignis\Install\Agents\Zed;
use Crustum\Ignis\Install\AgentsDetector;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Crustum\Ignis\Install\Enums\Platform;

beforeEach(function (): void {
    $this->container = freshTestContainer();
    registerDetectionStrategies($this->container);
    $this->ignisManager = new IgnisManager;
    $strategyFactory = new DetectionStrategyFactory($this->container);

    foreach ($this->ignisManager->getAgents() as $className) {
        $this->container->add($className, fn (): object => new $className($strategyFactory));
    }

    $this->detector = new AgentsDetector($this->container, $this->ignisManager);
});

it('returns collection of all registered agents', function (): void {
    $agents = $this->detector->getAgents();

    expect($agents)->toBeInstanceOf(Collection::class)
        ->and($agents->count())->toBe(13)
        ->and(array_keys($agents->toArray()))->toBe([
            'amp', 'junie', 'cursor', 'claude_code', 'codex', 'copilot', 'factory', 'kiro', 'opencode', 'antigravity', 'zed', 'pi', 'grok_build',
        ]);

    $agents->each(function ($agent): void {
        expect($agent)->toBeInstanceOf(Agent::class);
    });
});

it('returns an array of detected agents names for system discovery', function (): void {
    $mockJunie = Mockery::mock(Agent::class);
    $mockJunie->shouldReceive('detectOnSystem')->with(Mockery::type(Platform::class))->andReturn(true);
    $mockJunie->shouldReceive('name')->andReturn('junie');

    $mockCursor = Mockery::mock(Agent::class);
    $mockCursor->shouldReceive('detectOnSystem')->with(Mockery::type(Platform::class))->andReturn(true);
    $mockCursor->shouldReceive('name')->andReturn('cursor');

    $mockOther = Mockery::mock(Agent::class);
    $mockOther->shouldReceive('detectOnSystem')->with(Mockery::type(Platform::class))->andReturn(false);
    $mockOther->shouldReceive('name')->andReturn('other');

    bindInstance($this->container, Amp::class, $mockOther);
    bindInstance($this->container, Junie::class, $mockJunie);
    bindInstance($this->container, Cursor::class, $mockCursor);
    bindInstance($this->container, ClaudeCode::class, $mockOther);
    bindInstance($this->container, Codex::class, $mockOther);
    bindInstance($this->container, Copilot::class, $mockOther);
    bindInstance($this->container, Factory::class, $mockOther);
    bindInstance($this->container, Kiro::class, $mockOther);
    bindInstance($this->container, OpenCode::class, $mockOther);
    bindInstance($this->container, Antigravity::class, $mockOther);
    bindInstance($this->container, Zed::class, $mockOther);
    bindInstance($this->container, Pi::class, $mockOther);
    bindInstance($this->container, GrokBuild::class, $mockOther);

    $detector = new AgentsDetector($this->container, $this->ignisManager);
    $detected = $detector->discoverSystemInstalledAgents();

    expect($detected)->toBe(['junie', 'cursor']);
});

it('returns an empty array when no agents are detected for system discovery', function (): void {
    $mockAgent = Mockery::mock(Agent::class);
    $mockAgent->shouldReceive('detectOnSystem')->with(Mockery::type(Platform::class))->andReturn(false);
    $mockAgent->shouldReceive('name')->andReturn('mock');

    bindInstance($this->container, Amp::class, $mockAgent);
    bindInstance($this->container, Junie::class, $mockAgent);
    bindInstance($this->container, Cursor::class, $mockAgent);
    bindInstance($this->container, ClaudeCode::class, $mockAgent);
    bindInstance($this->container, Codex::class, $mockAgent);
    bindInstance($this->container, Copilot::class, $mockAgent);
    bindInstance($this->container, Factory::class, $mockAgent);
    bindInstance($this->container, Kiro::class, $mockAgent);
    bindInstance($this->container, OpenCode::class, $mockAgent);
    bindInstance($this->container, Antigravity::class, $mockAgent);
    bindInstance($this->container, Zed::class, $mockAgent);
    bindInstance($this->container, Pi::class, $mockAgent);
    bindInstance($this->container, GrokBuild::class, $mockAgent);

    $detector = new AgentsDetector($this->container, $this->ignisManager);
    $detected = $detector->discoverSystemInstalledAgents();

    expect($detected)->toBe([]);
});

it('returns an array of detected agent names for project discovery', function (): void {
    $basePath = '/test/project';

    $mockJunie = Mockery::mock(Agent::class);
    $mockJunie->shouldReceive('detectInProject')->with($basePath)->andReturn(false);
    $mockJunie->shouldReceive('name')->andReturn('junie');

    $mockClaudeCode = Mockery::mock(Agent::class);
    $mockClaudeCode->shouldReceive('detectInProject')->with($basePath)->andReturn(true);
    $mockClaudeCode->shouldReceive('name')->andReturn('claude_code');

    $mockOther = Mockery::mock(Agent::class);
    $mockOther->shouldReceive('detectInProject')->with($basePath)->andReturn(false);
    $mockOther->shouldReceive('name')->andReturn('other');

    bindInstance($this->container, Amp::class, $mockOther);
    bindInstance($this->container, Junie::class, $mockJunie);
    bindInstance($this->container, Cursor::class, $mockOther);
    bindInstance($this->container, ClaudeCode::class, $mockClaudeCode);
    bindInstance($this->container, Codex::class, $mockOther);
    bindInstance($this->container, Copilot::class, $mockOther);
    bindInstance($this->container, Factory::class, $mockOther);
    bindInstance($this->container, Kiro::class, $mockOther);
    bindInstance($this->container, OpenCode::class, $mockOther);
    bindInstance($this->container, Antigravity::class, $mockOther);
    bindInstance($this->container, Zed::class, $mockOther);
    bindInstance($this->container, Pi::class, $mockOther);
    bindInstance($this->container, GrokBuild::class, $mockOther);

    $detector = new AgentsDetector($this->container, $this->ignisManager);
    $detected = $detector->discoverProjectInstalledAgents($basePath);

    expect($detected)->toBe(['claude_code']);
});

it('returns an empty array when no agents are detected for project discovery', function (): void {
    $basePath = '/empty/project';

    $mockAgent = Mockery::mock(Agent::class);
    $mockAgent->shouldReceive('detectInProject')->with($basePath)->andReturn(false);
    $mockAgent->shouldReceive('name')->andReturn('mock');

    bindInstance($this->container, Amp::class, $mockAgent);
    bindInstance($this->container, Junie::class, $mockAgent);
    bindInstance($this->container, Cursor::class, $mockAgent);
    bindInstance($this->container, ClaudeCode::class, $mockAgent);
    bindInstance($this->container, Codex::class, $mockAgent);
    bindInstance($this->container, Copilot::class, $mockAgent);
    bindInstance($this->container, Factory::class, $mockAgent);
    bindInstance($this->container, Kiro::class, $mockAgent);
    bindInstance($this->container, OpenCode::class, $mockAgent);
    bindInstance($this->container, Antigravity::class, $mockAgent);
    bindInstance($this->container, Zed::class, $mockAgent);
    bindInstance($this->container, Pi::class, $mockAgent);
    bindInstance($this->container, GrokBuild::class, $mockAgent);

    $detector = new AgentsDetector($this->container, $this->ignisManager);
    $detected = $detector->discoverProjectInstalledAgents($basePath);

    expect($detected)->toBe([]);
});
