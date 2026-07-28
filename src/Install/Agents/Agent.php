<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Agents;

use Cake\Core\Configure;
use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Crustum\Ignis\Install\Enums\McpInstallationStrategy;
use Crustum\Ignis\Install\Enums\Platform;
use Crustum\Ignis\Install\Mcp\FileWriter;
use Crustum\Ignis\Install\Mcp\TomlFileWriter;
use Crustum\Ignis\Support\CommandNormalizer;

/**
 * Base installer for AI coding agents.
 */
abstract class Agent implements SupportsMcp
{
    /**
     * Constructor.
     *
     * @param \Crustum\Ignis\Install\Detection\DetectionStrategyFactory $strategyFactory Detection strategy factory
     */
    public function __construct(protected readonly DetectionStrategyFactory $strategyFactory)
    {
    }

    /**
     * Agent identifier used in ignis.json and detection.
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * Human-readable agent label for console output.
     *
     * @return string
     */
    abstract public function displayName(): string;

    /**
     * Whether MCP commands should use absolute executable paths.
     *
     * @return bool
     */
    public function useAbsolutePathForMcp(): bool
    {
        return false;
    }

    /**
     * Return the PHP executable path for MCP server commands.
     *
     * @param bool $forceAbsolutePath Force an absolute path
     * @return string
     */
    public function getPhpPath(bool $forceAbsolutePath = false): string
    {
        $phpBinaryPath = Configure::read('Ignis.executable_paths.php') ?? 'php';

        if ($phpBinaryPath === 'php' && ($this->useAbsolutePathForMcp() || $forceAbsolutePath)) {
            return PHP_BINARY;
        }

        return is_string($phpBinaryPath) ? $phpBinaryPath : 'php';
    }

    /**
     * Return the Cake console entry path for MCP server commands.
     *
     * @param bool $forceAbsolutePath Force an absolute path
     * @return string
     */
    public function getCakePath(bool $forceAbsolutePath = false): string
    {
        if ($this->useAbsolutePathForMcp() || $forceAbsolutePath) {
            return ROOT . DS . 'bin' . DS . 'cake.php';
        }

        return 'bin/cake.php';
    }

    /**
     * Detection configuration for system-wide installation.
     *
     * @param \Crustum\Ignis\Install\Enums\Platform $platform Target platform
     * @return array{paths?: array<int, string>, command?: string, files?: array<int, string>}
     */
    abstract public function systemDetectionConfig(Platform $platform): array;

    /**
     * Detection configuration for project-specific installation.
     *
     * @return array{paths?: array<int, string>, files?: array<int, string>}
     */
    abstract public function projectDetectionConfig(): array;

    /**
     * Detect whether the agent is installed on the system.
     *
     * @param \Crustum\Ignis\Install\Enums\Platform $platform Target platform
     * @return bool
     */
    public function detectOnSystem(Platform $platform): bool
    {
        $config = $this->systemDetectionConfig($platform);
        $strategy = $this->strategyFactory->makeFromConfig($config);

        return $strategy->detect($config, $platform);
    }

    /**
     * Detect whether the agent is configured in a project.
     *
     * @param string $basePath Project root path
     * @return bool
     */
    public function detectInProject(string $basePath): bool
    {
        $config = array_merge($this->projectDetectionConfig(), ['basePath' => $basePath]);
        $strategy = $this->strategyFactory->makeFromConfig($config);

        return $strategy->detect($config);
    }

    /**
     * MCP installation strategy for this agent.
     *
     * @return \Crustum\Ignis\Install\Enums\McpInstallationStrategy
     */
    public function mcpInstallationStrategy(): McpInstallationStrategy
    {
        return McpInstallationStrategy::FILE;
    }

    /**
     * Shell command template for shell-based MCP installation.
     *
     * @return string|null
     */
    public function shellMcpCommand(): ?string
    {
        return null;
    }

    /**
     * Path to the MCP configuration file for this agent.
     *
     * @return string|null
     */
    public function mcpConfigPath(): ?string
    {
        return null;
    }

    /**
     * Whether guidelines require YAML frontmatter.
     *
     * @return bool
     */
    public function frontmatter(): bool
    {
        return false;
    }

    /**
     * JSON or TOML key that stores MCP server definitions.
     *
     * @return string
     */
    public function mcpConfigKey(): string
    {
        return 'mcpServers';
    }

    /**
     * Default MCP configuration scaffold written to new config files.
     *
     * @return array<string, mixed>
     */
    public function defaultMcpConfig(): array
    {
        return [];
    }

    /**
     * Install an MCP server using the configured strategy.
     *
     * @param string $key MCP server key
     * @param string $command Executable command
     * @param array<int, string> $args Command arguments
     * @param array<string, string> $env Environment variables
     * @return bool
     */
    public function installMcp(string $key, string $command, array $args = [], array $env = []): bool
    {
        return match ($this->mcpInstallationStrategy()) {
            McpInstallationStrategy::SHELL => $this->installShellMcp($key, $command, $args, $env),
            McpInstallationStrategy::FILE => $this->installFileMcp($key, $command, $args, $env),
            McpInstallationStrategy::NONE => false,
        };
    }

    /**
     * Build the HTTP MCP server configuration payload for file-based installation.
     *
     * @param string $url MCP server URL
     * @return array<string, mixed>
     */
    public function httpMcpServerConfig(string $url): array
    {
        return [
            'type' => 'http',
            'url' => $url,
        ];
    }

    /**
     * Install an HTTP MCP server using the file-based strategy.
     *
     * @param string $key MCP server key
     * @param string $url MCP server URL
     * @return bool
     */
    public function installHttpMcp(string $key, string $url): bool
    {
        $path = $this->mcpConfigPath();

        if ($path === null) {
            return false;
        }

        $writer = str_ends_with($path, '.toml')
            ? new TomlFileWriter($path, $this->defaultMcpConfig())
            : new FileWriter($path, $this->defaultMcpConfig());

        return $writer
            ->configKey($this->mcpConfigKey())
            ->addServerConfig($key, $this->httpMcpServerConfig($url))
            ->save();
    }

    /**
     * Build the MCP server configuration payload for file-based installation.
     *
     * @param string $command Executable command
     * @param array<int, string> $args Command arguments
     * @param array<string, string> $env Environment variables
     * @return array<string, mixed>
     */
    public function mcpServerConfig(string $command, array $args = [], array $env = []): array
    {
        return [
            'command' => $command,
            'args' => $args,
            'env' => $env,
        ];
    }

    /**
     * Post-process generated guidelines markdown.
     *
     * @param string $markdown Generated markdown
     * @return string
     */
    public function transformGuidelines(string $markdown): string
    {
        return $markdown;
    }

    /**
     * Read a Ignis configuration value with a fallback default.
     *
     * @param string $key Configuration key without the Ignis prefix
     * @param mixed $default Default value when the key is unset
     * @return mixed
     */
    protected function ignisConfig(string $key, mixed $default = null): mixed
    {
        if (!Configure::check('Ignis.' . $key)) {
            return $default;
        }

        return Configure::read('Ignis.' . $key);
    }

    /**
     * Resolve a path relative to the project root.
     *
     * @param string $path Relative path
     * @return string
     */
    protected function rootPath(string $path = ''): string
    {
        if ($path === '') {
            return ROOT;
        }

        return ROOT . DS . ltrim(str_replace(['/', '\\'], DS, $path), DS);
    }

    /**
     * Remove empty values from MCP configuration arrays.
     *
     * @param array<string, mixed> $values Configuration values
     * @return array<string, mixed>
     */
    protected function filterEmptyValues(array $values): array
    {
        return array_filter(
            $values,
            fn(mixed $value): bool => !in_array($value, [[], null, ''], true),
        );
    }

    /**
     * Install MCP server using a shell command strategy.
     *
     * @param string $key MCP server key
     * @param string $command Executable command
     * @param array<int, string> $args Command arguments
     * @param array<string, string> $env Environment variables
     * @return bool
     */
    protected function installShellMcp(string $key, string $command, array $args = [], array $env = []): bool
    {
        $shellCommand = $this->shellMcpCommand();

        if ($shellCommand === null) {
            return false;
        }

        $normalized = $this->normalizeCommand($command, $args);

        $envString = '';

        foreach ($env as $envKey => $value) {
            $envKey = strtoupper($envKey);
            $envString .= "-e {$envKey}=\"{$value}\" ";
        }

        $command = str_replace(
            ['{key}', '{command}', '{args}', '{env}'],
            [
                $key,
                $normalized['command'],
                implode(' ', array_map(fn(string $arg): string => '"' . $arg . '"', $normalized['args'])),
                trim($envString),
            ],
            $shellCommand,
        );

        $result = $this->runShellCommand($command);

        if ($result['success']) {
            return true;
        }

        return str_contains($result['errorOutput'], 'already exists');
    }

    /**
     * Install MCP server using a file-based configuration strategy.
     *
     * @param string $key MCP server key
     * @param string $command Executable command
     * @param array<int, string> $args Command arguments
     * @param array<string, string> $env Environment variables
     * @return bool
     */
    protected function installFileMcp(string $key, string $command, array $args = [], array $env = []): bool
    {
        $path = $this->mcpConfigPath();

        if ($path === null) {
            return false;
        }

        $normalized = $this->normalizeCommand($command, $args);

        $writer = str_ends_with($path, '.toml')
            ? new TomlFileWriter($path, $this->defaultMcpConfig())
            : new FileWriter($path, $this->defaultMcpConfig());

        return $writer
            ->configKey($this->mcpConfigKey())
            ->addServerConfig($key, $this->mcpServerConfig($normalized['command'], $normalized['args'], $env))
            ->save();
    }

    /**
     * Normalize command by splitting space-separated commands into command and args.
     *
     * @param string $command Command string
     * @param array<int, string> $args Additional arguments
     * @return array{command: string, args: array<int, string>}
     */
    protected function normalizeCommand(string $command, array $args = []): array
    {
        return CommandNormalizer::normalize($command, $args);
    }

    /**
     * Execute a shell command and capture success state and stderr.
     *
     * @param string $command Shell command
     * @return array{success: bool, errorOutput: string}
     */
    protected function runShellCommand(string $command): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'errorOutput' => '',
            ];
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'success' => proc_close($process) === 0,
            'errorOutput' => is_string($errorOutput) ? $errorOutput : '',
        ];
    }
}
