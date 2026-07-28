<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Agents;

use Crustum\Ignis\Contracts\SupportsGuidelines;
use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Contracts\SupportsSkills;
use Crustum\Ignis\Install\Enums\Platform;
use Override;

/**
 * Zed agent installer.
 */
class Zed extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    /**
     * Agent identifier.
     *
     * @return string
     */
    public function name(): string
    {
        return 'zed';
    }

    /**
     * Human-readable agent label.
     *
     * @return string
     */
    public function displayName(): string
    {
        return 'Zed';
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
            Platform::Darwin => [
                'paths' => ['/Applications/Zed.app'],
            ],
            Platform::Linux => [
                'command' => 'command -v zed || command -v zeditor',
            ],
            Platform::Windows => [
                'command' => 'cmd /c where zed 2>nul',
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
            'paths' => ['.zed'],
        ];
    }

    /**
     * MCP configuration file path.
     *
     * @return string
     */
    public function mcpConfigPath(): string
    {
        return $this->ignisConfig('agents.zed.mcp_config_path', '.zed/settings.json');
    }

    /**
     * MCP configuration key.
     *
     * @return string
     */
    #[Override]
    public function mcpConfigKey(): string
    {
        return 'context_servers';
    }

    /**
     * HTTP MCP server configuration payload.
     *
     * @param string $url MCP server URL
     * @return array<string, mixed>
     */
    #[Override]
    public function httpMcpServerConfig(string $url): array
    {
        return [
            'url' => $url,
        ];
    }

    /**
     * Guidelines output path.
     *
     * @return string
     */
    public function guidelinesPath(): string
    {
        return $this->ignisConfig('agents.zed.guidelines_path', 'AGENTS.md');
    }

    /**
     * Skills output directory.
     *
     * @return string
     */
    public function skillsPath(): string
    {
        return $this->ignisConfig('agents.zed.skills_path', '.agents/skills');
    }
}
