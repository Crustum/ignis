<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Crustum\Ignis\Contracts\SupportsMcp;
use RuntimeException;

/**
 * Installs the Cake Ignis MCP server configuration for an agent.
 */
class McpWriter
{
    public const SUCCESS = 0;

    /**
     * Constructor.
     *
     * @param \Crustum\Ignis\Contracts\SupportsMcp $agent Target agent
     */
    public function __construct(protected SupportsMcp $agent)
    {
    }

    /**
     * Install the Ignis MCP server configuration.
     *
     * @return self::SUCCESS
     */
    public function write(): int
    {
        $this->installIgnisMcp();

        return self::SUCCESS;
    }

    /**
     * Write the Ignis MCP server configuration through the agent adapter.
     *
     * @return void
     */
    protected function installIgnisMcp(): void
    {
        $mcp = $this->buildIgnisMcpCommand();

        if (!$this->agent->installMcp($mcp['key'], $mcp['command'], $mcp['args'])) {
            throw new RuntimeException('Failed to install Ignis MCP: could not write configuration');
        }
    }

    /**
     * Build the Ignis MCP command payload for the current environment.
     *
     * @return array{key: string, command: string, args: array<int, string>}
     */
    protected function buildIgnisMcpCommand(): array
    {
        if ($this->isRunningInsideWsl()) {
            return [
                'key' => 'cake-ignis',
                'command' => 'wsl.exe',
                'args' => [
                    $this->agent->getPhpPath(true),
                    $this->agent->getCakePath(true),
                    'ignis',
                    'mcp',
                ],
            ];
        }

        return [
            'key' => 'cake-ignis',
            'command' => $this->agent->getPhpPath(),
            'args' => [
                $this->agent->getCakePath(),
                'ignis',
                'mcp',
            ],
        ];
    }

    /**
     * Determine whether the current process is running inside WSL.
     *
     * @return bool
     */
    private function isRunningInsideWsl(): bool
    {
        return (getenv('WSL_DISTRO_NAME') !== false && getenv('WSL_DISTRO_NAME') !== '')
            || (getenv('IS_WSL') !== false && getenv('IS_WSL') !== '');
    }
}
