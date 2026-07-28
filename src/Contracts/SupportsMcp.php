<?php
declare(strict_types=1);

namespace Crustum\Ignis\Contracts;

/**
 * Contract for agents that support MCP (Model Context Protocol).
 */
interface SupportsMcp
{
    /**
     * Whether to use absolute paths for MCP commands.
     *
     * @return bool
     */
    public function useAbsolutePathForMcp(): bool;

    /**
     * Get the PHP executable path for this MCP client.
     *
     * @param bool $forceAbsolutePath Force an absolute path
     * @return string
     */
    public function getPhpPath(bool $forceAbsolutePath = false): string;

    /**
     * Get the Cake console entry path for this MCP client.
     *
     * @param bool $forceAbsolutePath Force an absolute path
     * @return string
     */
    public function getCakePath(bool $forceAbsolutePath = false): string;

    /**
     * Install an MCP server configuration in this IDE.
     *
     * @param string $key MCP server key
     * @param string $command Executable command
     * @param array<int, string> $args Command arguments
     * @param array<string, string> $env Environment variables
     * @return bool
     */
    public function installMcp(string $key, string $command, array $args = [], array $env = []): bool;

    /**
     * Install an HTTP MCP server configuration in this IDE.
     *
     * @param string $key MCP server key
     * @param string $url MCP server URL
     * @return bool
     */
    public function installHttpMcp(string $key, string $url): bool;
}
