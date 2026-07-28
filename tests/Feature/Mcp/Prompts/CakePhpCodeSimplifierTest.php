<?php

declare(strict_types=1);

use Crustum\Ignis\Mcp\Prompts\CakePhpCodeSimplifier\CakePhpCodeSimplifier;

beforeEach(function (): void {
    $this->prompt = new CakePhpCodeSimplifier();
});

test('it has correct name', function (): void {
    expect($this->prompt->name())->toBe('cakephp-code-simplifier');
});

test('it has a description', function (): void {
    expect($this->prompt->description())
        ->toContain('Simplifies')
        ->toContain('CakePHP')
        ->toContain('maintainability');
});

test('it returns a valid response', function (): void {
    $response = $this->prompt->handle();

    expect($response)->isToolResult()
        ->toolHasNoError();
});

test('it contains core guideline content', function (): void {
    $response = $this->prompt->handle();

    expect($response)->isToolResult()
        ->toolTextContains('CakePHP Code Simplifier')
        ->toolTextContains('Preserve Functionality')
        ->toolTextContains('Apply Project Standards')
        ->toolTextContains('Enhance Clarity');
});
