<?php

declare(strict_types=1);

use Crustum\Ignis\IgnisManager;
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
use Tests\Unit\Install\ExampleAgent;

it('returns default agents', function (): void {
    $manager = new IgnisManager;
    $registered = $manager->getAgents();

    expect($registered)->toMatchArray([
        'amp' => Amp::class,
        'junie' => Junie::class,
        'cursor' => Cursor::class,
        'claude_code' => ClaudeCode::class,
        'codex' => Codex::class,
        'copilot' => Copilot::class,
        'factory' => Factory::class,
        'kiro' => Kiro::class,
        'opencode' => OpenCode::class,
        'antigravity' => Antigravity::class,
        'zed' => Zed::class,
        'pi' => Pi::class,
        'grok_build' => GrokBuild::class,
    ]);
});

it('can register a single agent', function (): void {
    $manager = new IgnisManager;
    $manager->registerAgent('example', ExampleAgent::class);

    $registered = $manager->getAgents();

    expect($registered)->toHaveKey('example')
        ->and($registered['example'])->toBe(ExampleAgent::class)
        ->and($registered)->toHaveKey('junie');
});

it('can register multiple agents', function (): void {
    $manager = new IgnisManager;
    $manager->registerAgent('example1', ExampleAgent::class);
    $manager->registerAgent('example2', ExampleAgent::class);

    $registered = $manager->getAgents();

    expect($registered)->toHaveKey('example1')->toHaveKey('example2')
        ->and($registered['example1'])->toBe(ExampleAgent::class)
        ->and($registered['example2'])->toBe(ExampleAgent::class)
        ->and($registered)->toHaveKey('junie');
});

it('throws an exception when registering a duplicate key', function (): void {
    $manager = new IgnisManager;

    expect(fn () => $manager->registerAgent('junie', ExampleAgent::class))
        ->toThrow(InvalidArgumentException::class, "Agent 'junie' is already registered");
});

it('throws an exception when registering a custom agent with a duplicate key', function (): void {
    $manager = new IgnisManager;
    $manager->registerAgent('custom', ExampleAgent::class);

    expect(fn () => $manager->registerAgent('custom', ExampleAgent::class))
        ->toThrow(InvalidArgumentException::class, "Agent 'custom' is already registered");
});
