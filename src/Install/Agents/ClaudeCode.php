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
 * Claude Code agent installer.
 */
class ClaudeCode extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    /**
     * Agent identifier.
     *
     * @return string
     */
    public function name(): string
    {
        return 'claude_code';
    }

    /**
     * Human-readable agent label.
     *
     * @return string
     */
    public function displayName(): string
    {
        return 'Claude Code';
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
                'command' => 'command -v claude',
            ],
            Platform::Windows => [
                'command' => 'cmd /c where claude 2>nul',
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
            'paths' => ['.claude'],
            'files' => ['CLAUDE.md'],
        ];
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
        return $this->ignisConfig('agents.claude_code.mcp_config_path', '.mcp.json');
    }

    /**
     * Guidelines output path.
     *
     * @return string
     */
    public function guidelinesPath(): string
    {
        $configured = $this->ignisConfig('agents.claude_code.guidelines_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return is_file($this->rootPath('CLAUDE.md')) ? 'CLAUDE.md' : 'AGENTS.md';
    }

    /**
     * Skills output directory.
     *
     * @return string
     */
    public function skillsPath(): string
    {
        return $this->ignisConfig('agents.claude_code.skills_path', '.claude/skills');
    }
}
