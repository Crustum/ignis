<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Agents;

use Crustum\Ignis\Contracts\SupportsGuidelines;
use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Contracts\SupportsSkills;
use Crustum\Ignis\Install\Enums\Platform;
use Override;

/**
 * Factory Droid agent installer.
 */
class Factory extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    /**
     * Agent identifier.
     *
     * @return string
     */
    public function name(): string
    {
        return 'factory';
    }

    /**
     * Human-readable agent label.
     *
     * @return string
     */
    public function displayName(): string
    {
        return 'Factory Droid';
    }

    /**
     * System-wide detection configuration.
     *
     * @param \Crustum\Ignis\Install\Enums\Platform $platform Target platform
     * @return array{paths?: array<int, string>, command?: string, files?: array<int, string>}
     */
    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin, Platform::Linux => [
                'command' => 'command -v droid',
                'paths' => ['~/.factory'],
            ],
            Platform::Windows => [
                'command' => 'cmd /c where droid 2>nul',
                'paths' => ['%USERPROFILE%\\.factory'],
            ],
        };
    }

    /**
     * Project detection configuration.
     *
     * @return array{paths?: array<int, string>, files?: array<int, string>}
     */
    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.factory'],
        ];
    }

    /**
     * MCP configuration file path.
     *
     * @return string
     */
    public function mcpConfigPath(): string
    {
        return $this->ignisConfig('agents.factory.mcp_config_path', '.factory/mcp.json');
    }

    /**
     * MCP server configuration payload.
     *
     * @param string $command Executable command
     * @param array<int, string> $args Command arguments
     * @param array<string, string> $env Environment variables
     * @return array<string, mixed>
     */
    #[Override]
    public function mcpServerConfig(string $command, array $args = [], array $env = []): array
    {
        return $this->filterEmptyValues([
            'type' => 'stdio',
            'command' => $command,
            'args' => $args,
            'env' => $env,
        ]);
    }

    /**
     * Guidelines output path.
     *
     * @return string
     */
    public function guidelinesPath(): string
    {
        return $this->ignisConfig('agents.factory.guidelines_path', 'AGENTS.md');
    }

    /**
     * Skills output directory.
     *
     * @return string
     */
    public function skillsPath(): string
    {
        return $this->ignisConfig('agents.factory.skills_path', '.factory/skills');
    }
}
