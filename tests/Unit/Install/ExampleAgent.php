<?php

declare(strict_types=1);

namespace Tests\Unit\Install;

use Crustum\Ignis\Contracts\SupportsGuidelines;
use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Install\Agents\Agent;
use Crustum\Ignis\Install\Enums\Platform;

class ExampleAgent extends Agent implements SupportsGuidelines, SupportsMcp
{
    public function name(): string
    {
        return 'example';
    }

    public function displayName(): string
    {
        return 'Example IDE';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return ['command' => 'which example'];
    }

    public function projectDetectionConfig(): array
    {
        return ['paths' => ['.example']];
    }

    public function mcpConfigPath(): string
    {
        return '.example/config.json';
    }

    public function guidelinesPath(): string
    {
        return 'EXAMPLE.md';
    }
}
