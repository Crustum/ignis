<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Agents;

use Crustum\Ignis\Contracts\SupportsGuidelines;
use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Contracts\SupportsSkills;
use Crustum\Ignis\Install\Enums\McpInstallationStrategy;
use Crustum\Ignis\Install\Enums\Platform;
use Override;

/**
 * Grok Build agent installer.
 */
class GrokBuild extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    /**
     * Agent identifier.
     *
     * @return string
     */
    public function name(): string
    {
        return 'grok_build';
    }

    /**
     * Human-readable agent label.
     *
     * @return string
     */
    public function displayName(): string
    {
        return 'Grok Build';
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
                'command' => 'command -v grok',
                'paths' => ['~/.grok'],
            ],
            Platform::Windows => [
                'command' => 'cmd /c where grok 2>nul',
                'paths' => ['%USERPROFILE%\\.grok'],
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
            'paths' => ['.grok'],
            'files' => ['.grok/config.toml'],
        ];
    }

    /**
     * Guidelines output path.
     *
     * @return string
     */
    public function guidelinesPath(): string
    {
        return $this->ignisConfig('agents.grok_build.guidelines_path', 'AGENTS.md');
    }

    /**
     * MCP installation strategy.
     *
     * @return \Crustum\Ignis\Install\Enums\McpInstallationStrategy
     */
    #[Override]
    public function mcpInstallationStrategy(): McpInstallationStrategy
    {
        return McpInstallationStrategy::FILE;
    }

    /**
     * MCP configuration file path.
     *
     * @return string
     */
    public function mcpConfigPath(): string
    {
        return $this->ignisConfig('agents.grok_build.mcp_config_path', '.grok/config.toml');
    }

    /**
     * MCP configuration key.
     *
     * @return string
     */
    #[Override]
    public function mcpConfigKey(): string
    {
        return 'mcp_servers';
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
            'command' => $command,
            'args' => $args,
            'env' => $env,
        ]);
    }

    /**
     * Skills output directory.
     *
     * @return string
     */
    public function skillsPath(): string
    {
        return $this->ignisConfig('agents.grok_build.skills_path', '.grok/skills');
    }
}
