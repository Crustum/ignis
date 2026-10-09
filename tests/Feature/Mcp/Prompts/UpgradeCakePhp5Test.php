<?php

declare(strict_types=1);

use Crustum\Ignis\Mcp\Prompts\UpgradeCakePhp5\UpgradeCakePhp5;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\Ecosystems\Ecosystem;
use Crustum\Inspector\ProjectManager;
use JMac\Testing\Double;

beforeEach(function (): void {
    $this->prompt = new UpgradeCakePhp5();
});

test('it has the correct name', function (): void {
    expect($this->prompt->name())->toBe('upgrade-cakephp-5');
});

test('it returns a valid response', function (): void {
    $response = $this->prompt->handle();

    expect($response)
        ->isToolResult()
        ->toolHasNoError();
});

test('it contains core upgrade content', function (): void {
    $response = $this->prompt->handle();

    expect($response)->isToolResult()
        ->toolTextContains('CakePHP 4 to 5 Upgrade Specialist')
        ->toolTextContains('migration guide')
        ->toolTextContains('cakephp/cakephp')
        ->toolTextContains('record-rule');
});

test('it properly compiles twig assist helpers', function (): void {
    $response = $this->prompt->handle();
    $text = toolResponseText($response);

    expect($text)
        ->toContain('composer show cakephp/cakephp')
        ->toContain('composer require cakephp/cakephp:^5.0 --with-all-dependencies')
        ->toContain('php bin/cake.php cache clear_all')
        ->not->toContain('assist.composerCommand')
        ->not->toContain('{{ assist')
        ->not->toContain('{% if');
});

test('it registers only for cakephp 4.x projects', function (): void {
    $project = Double::for(ProjectManager::class, override: true);
    $php = Double::for(Ecosystem::class);
    $project->allows('php')->returns($php);
    $php->allows('uses')
        ->with(PackageRegistry::CAKEPHP, '>=4.0.0 <5.0.0')
        ->returns(true);

    expect($this->prompt->shouldRegister($project->instance()))->toBeTrue();
});

test('it does not register for cakephp 5.x projects', function (): void {
    $project = Double::for(ProjectManager::class, override: true);
    $php = Double::for(Ecosystem::class);
    $project->allows('php')->returns($php);
    $php->allows('uses')
        ->with(PackageRegistry::CAKEPHP, '>=4.0.0 <5.0.0')
        ->returns(false);

    expect($this->prompt->shouldRegister($project->instance()))->toBeFalse();
});
