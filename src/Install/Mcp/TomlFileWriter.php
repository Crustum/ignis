<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Mcp;

/**
 * Writes MCP server definitions into TOML configuration files.
 */
class TomlFileWriter
{
    /**
     * TOML table prefix for MCP server definitions.
     *
     * @var string
     */
    protected string $configKey = 'mcp_servers';

    /**
     * Server definitions queued for writing.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $serversToAdd = [];

    /**
     * Constructor.
     *
     * @param string $filePath Target configuration file path
     * @param array<string, mixed> $baseConfig Base configuration scaffold
     */
    public function __construct(protected string $filePath, protected array $baseConfig = [])
    {
    }

    /**
     * Set the TOML table prefix used for MCP servers.
     *
     * @param string $key Configuration key
     * @return $this
     */
    public function configKey(string $key)
    {
        $this->configKey = $key;

        return $this;
    }

    /**
     * Queue an MCP server definition for writing.
     *
     * @param string $key Server key
     * @param array<string, mixed> $config Server configuration
     * @return $this
     */
    public function addServerConfig(string $key, array $config)
    {
        $this->serversToAdd[$key] = $this->filterEmptyValues($config);

        return $this;
    }

    /**
     * Persist queued MCP server definitions to disk.
     *
     * @return bool
     */
    public function save(): bool
    {
        $this->ensureDirectoryExists();

        $rawContents = is_file($this->filePath) ? file_get_contents($this->filePath) : false;
        $content = is_string($rawContents) ? $this->normalizeContent($rawContents) : '';

        if ($content === '') {
            return $this->createNewFile();
        }

        return $this->updateExistingFile($content);
    }

    /**
     * Create a new TOML configuration file.
     *
     * @return bool
     */
    protected function createNewFile(): bool
    {
        $lines = [];

        foreach ($this->baseConfig as $key => $value) {
            if (!is_array($value)) {
                $lines[] = "{$key} = " . $this->formatValue($value);
            }
        }

        foreach ($this->serversToAdd as $key => $config) {
            if ($lines !== []) {
                $lines[] = '';
            }

            $lines[] = $this->buildServerToml($key, $config);
        }

        return $this->writeFile(implode(PHP_EOL, $lines) . PHP_EOL);
    }

    /**
     * Update an existing TOML configuration file.
     *
     * @param string $content Normalized file contents
     * @return bool
     */
    protected function updateExistingFile(string $content): bool
    {
        foreach ($this->serversToAdd as $key => $config) {
            if ($this->serverExists($content, $key)) {
                $content = $this->removeExistingServer($content, $key);
            }

            $trimmed = rtrim($content);
            $separator = $trimmed === '' ? '' : PHP_EOL . PHP_EOL;
            $content = $trimmed . $separator . $this->buildServerToml($key, $config) . PHP_EOL;
        }

        return $this->writeFile($content);
    }

    /**
     * Build TOML for a single MCP server definition.
     *
     * @param string $key Server key
     * @param array<string, mixed> $config Server configuration
     * @return string
     */
    protected function buildServerToml(string $key, array $config): string
    {
        $lines = [];
        $lines[] = "[{$this->configKey}.{$key}]";

        foreach ($config as $field => $value) {
            if ($field === 'env' && is_array($value)) {
                continue;
            }

            $lines[] = "{$field} = " . $this->formatValue($value);
        }

        if (isset($config['env']) && is_array($config['env']) && $config['env'] !== []) {
            $lines[] = '';
            $lines[] = "[{$this->configKey}.{$key}.env]";

            foreach ($config['env'] as $envKey => $envValue) {
                $lines[] = "{$envKey} = " . $this->formatValue($envValue);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Format a TOML value.
     *
     * @param mixed $value Value to format
     * @return string
     */
    protected function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return '"' . $this->escapeTomlString($value) . '"';
        }

        if (is_array($value)) {
            $items = array_map($this->formatValue(...), $value);

            return '[' . implode(', ', $items) . ']';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string)$value;
    }

    /**
     * Escape special characters in a TOML string.
     *
     * @param string $value Raw string
     * @return string
     */
    protected function escapeTomlString(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]);
    }

    /**
     * Whether a server table already exists in the file.
     *
     * @param string $content Existing file contents
     * @param string $key Server key
     * @return bool
     */
    protected function serverExists(string $content, string $key): bool
    {
        $pattern = '/^\[' . preg_quote($this->configKey, '/') . '\.' . preg_quote($key, '/') . '\]/m';

        return preg_match($pattern, $content) === 1;
    }

    /**
     * Remove an existing server table from the file.
     *
     * @param string $content Existing file contents
     * @param string $key Server key
     * @return string
     */
    protected function removeExistingServer(string $content, string $key): string
    {
        $escapedConfigKey = preg_quote($this->configKey, '/');
        $escapedKey = preg_quote($key, '/');

        $envPattern = '/(\r?\n)*\[' . $escapedConfigKey . '\.' . $escapedKey . '\.env\].*?(?=\r?\n\[|$)/s';
        $content = preg_replace($envPattern, '', $content) ?? $content;

        $mainPattern = '/(\r?\n)*\[' . $escapedConfigKey . '\.' . $escapedKey . '\].*?(?=\r?\n\[|$)/s';

        return preg_replace($mainPattern, '', $content) ?? $content;
    }

    /**
     * Normalize raw file contents for parsing (strip BOM, trim surrounding whitespace).
     *
     * @param string $content Raw file contents
     * @return string
     */
    protected function normalizeContent(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        return trim($content);
    }

    /**
     * Ensure the target directory exists.
     *
     * @return void
     */
    protected function ensureDirectoryExists(): void
    {
        $directory = dirname($this->filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    /**
     * Write content to the target configuration file.
     *
     * @param string $content File contents
     * @return bool
     */
    protected function writeFile(string $content): bool
    {
        return file_put_contents($this->filePath, $content) !== false;
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
}
