<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Agents;

use Crustum\Ignis\Contracts\SupportsGuidelines;
use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Contracts\SupportsSkills;
use Crustum\Ignis\Install\Enums\Platform;
use Override;

/**
 * Amp agent installer.
 */
class Amp extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    /**
     * Agent identifier.
     *
     * @return string
     */
    public function name(): string
    {
        return 'amp';
    }

    /**
     * Human-readable agent label.
     *
     * @return string
     */
    public function displayName(): string
    {
        return 'Amp';
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
                'command' => 'command -v amp',
                'paths' => ['~/.amp', '~/.config/amp'],
            ],
            Platform::Windows => [
                'command' => 'cmd /c where amp 2>nul',
                'paths' => ['%USERPROFILE%\\.amp', '%USERPROFILE%\\.config\\amp'],
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
            'paths' => ['.amp'],
        ];
    }

    /**
     * MCP configuration file path.
     *
     * @return string
     */
    public function mcpConfigPath(): string
    {
        return $this->ignisConfig(
            'agents.amp.mcp_config_path',
            $this->rootPath('.amp/settings.json'),
        );
    }

    /**
     * MCP configuration key.
     *
     * @return string
     */
    #[Override]
    public function mcpConfigKey(): string
    {
        return 'amp.mcpServers';
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
        return ['url' => $url];
    }

    /**
     * Guidelines output path.
     *
     * @return string
     */
    public function guidelinesPath(): string
    {
        return $this->ignisConfig('agents.amp.guidelines_path', 'AGENTS.md');
    }

    /**
     * Skills output directory.
     *
     * @return string
     */
    public function skillsPath(): string
    {
        return $this->ignisConfig('agents.amp.skills_path', '.agents/skills');
    }
}
