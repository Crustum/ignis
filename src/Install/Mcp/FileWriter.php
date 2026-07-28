<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Mcp;

use stdClass;

/**
 * Writes MCP server definitions into JSON or JSON5 configuration files.
 */
class FileWriter
{
    /**
     * JSON key that stores MCP server definitions.
     *
     * @var string
     */
    protected string $configKey = 'mcpServers';

    /**
     * Server definitions queued for writing.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $serversToAdd = [];

    /**
     * Default indentation for injected JSON blocks.
     *
     * @var int
     */
    protected int $defaultIndentation = 8;

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
     * Set the configuration key used for MCP servers.
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

        if ($this->shouldWriteNew()) {
            return $this->createNewFile();
        }

        $content = $this->readFile();

        if ($this->isPlainJson($content)) {
            return $this->updatePlainJsonFile($content);
        }

        if (!$this->hasJson5Features($content)) {
            return false;
        }

        return $this->updateJson5File($content);
    }

    /**
     * Update a plain JSON configuration file.
     *
     * @param string $content Existing file contents
     * @return bool
     */
    protected function updatePlainJsonFile(string $content): bool
    {
        $config = json_decode($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        $this->addServersToConfig($config);

        return $this->writeJsonConfig($config);
    }

    /**
     * Update a JSON5 configuration file.
     *
     * @param string $content Existing file contents
     * @return bool
     */
    protected function updateJson5File(string $content): bool
    {
        $configKeyPattern = '/["\']' . preg_quote($this->configKey, '/') . '["\']\\s*:\\s*\\{/';

        if (preg_match($configKeyPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $this->injectIntoExistingConfigKey($content, $matches);
        }

        return $this->injectNewConfigKey($content);
    }

    /**
     * Inject servers into an existing configuration key block.
     *
     * @param string $content Existing file contents
     * @param array<int, array<int, int|string>> $matches Regex match offsets
     * @return bool
     */
    protected function injectIntoExistingConfigKey(string $content, array $matches): bool
    {
        $configKeyStart = $matches[0][1];
        $openBracePos = strpos($content, '{', $configKeyStart);

        if ($openBracePos === false) {
            return false;
        }

        $closeBracePos = $this->findMatchingClosingBrace($content, $openBracePos);

        if ($closeBracePos === false) {
            return false;
        }

        $serversToAdd = $this->filterExistingServers($content, $openBracePos, $closeBracePos);

        if ($serversToAdd === []) {
            return true;
        }

        $indentLength = $this->detectIndentation($content, $closeBracePos);
        $serverJsonParts = [];

        foreach ($serversToAdd as $key => $serverConfig) {
            $serverJsonParts[] = $this->generateServerJson($key, $serverConfig, $indentLength);
        }

        $serversJson = implode(',' . "\n", $serverJsonParts);
        $needsComma = $this->needsCommaBeforeClosingBrace($content, $openBracePos, $closeBracePos);

        if (!$needsComma) {
            $newContent = substr_replace($content, $serversJson, $closeBracePos, 0);

            return $this->writeFile($newContent);
        }

        $commaPosition = $this->findCommaInsertionPoint($content, $openBracePos, $closeBracePos);

        if ($commaPosition !== -1) {
            $newContent = substr_replace($content, ',', $commaPosition, 0);
            $newContent = substr_replace($newContent, $serversJson, $commaPosition + 1, 0);
        } else {
            $newContent = substr_replace($content, $serversJson, $closeBracePos, 0);
        }

        return $this->writeFile($newContent);
    }

    /**
     * Filter out servers that already exist in the target block.
     *
     * @param string $content Existing file contents
     * @param int $openBracePos Opening brace position
     * @param int $closeBracePos Closing brace position
     * @return array<string, array<string, mixed>>
     */
    protected function filterExistingServers(string $content, int $openBracePos, int $closeBracePos): array
    {
        $configContent = substr($content, $openBracePos + 1, $closeBracePos - $openBracePos - 1);
        $filteredServers = [];

        foreach ($this->serversToAdd as $key => $serverConfig) {
            if (!$this->serverExistsInContent($configContent, $key)) {
                $filteredServers[$key] = $serverConfig;
            }
        }

        return $filteredServers;
    }

    /**
     * Whether a server key already exists in a content block.
     *
     * @param string $content Content block
     * @param string $serverKey Server key
     * @return bool
     */
    protected function serverExistsInContent(string $content, string $serverKey): bool
    {
        $quotedPattern = '/["\']' . preg_quote($serverKey, '/') . '["\']\\s*:/';
        $unquotedPattern = '/(?<=^|\\s|,|{)' . preg_quote($serverKey, '/') . '\\s*:/m';

        return preg_match($quotedPattern, $content) === 1 || preg_match($unquotedPattern, $content) === 1;
    }

    /**
     * Inject a new configuration key block into the file.
     *
     * @param string $content Existing file contents
     * @return bool
     */
    protected function injectNewConfigKey(string $content): bool
    {
        $openBracePos = strpos($content, '{');

        if ($openBracePos === false) {
            return false;
        }

        $serverJsonParts = [];

        foreach ($this->serversToAdd as $key => $serverConfig) {
            $serverJsonParts[] = $this->generateServerJson($key, $serverConfig);
        }

        $serversJson = implode(',', $serverJsonParts);
        $configKeySection = '"' . $this->configKey . '": {' . $serversJson . '}';
        $needsComma = $this->needsCommaAfterBrace($content, $openBracePos);
        $injection = $configKeySection . ($needsComma ? ',' : '');
        $newContent = substr_replace($content, $injection, $openBracePos + 1, 0);

        return $this->writeFile($newContent);
    }

    /**
     * Generate JSON for a single MCP server entry.
     *
     * @param string $key Server key
     * @param array<string, mixed> $serverConfig Server configuration
     * @param int $baseIndent Base indentation width
     * @return string
     */
    protected function generateServerJson(string $key, array $serverConfig, int $baseIndent = 0): string
    {
        $json = json_encode($serverConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            return '"' . $key . '": {}';
        }

        $json = str_replace("\r\n", "\n", $json);

        if ($baseIndent === 0) {
            return '"' . $key . '": ' . $json;
        }

        $baseIndentString = str_repeat(' ', $baseIndent);
        $lines = explode("\n", $json);
        $firstLine = array_shift($lines);
        $indentedLines = [
            "{$baseIndentString}\"{$key}\": {$firstLine}",
            ...array_map(fn(string $line): string => $baseIndentString . $line, $lines),
        ];

        return "\n" . implode("\n", $indentedLines);
    }

    /**
     * Whether a comma is needed immediately after the root opening brace.
     *
     * @param string $content Existing file contents
     * @param int $bracePosition Opening brace position
     * @return bool
     */
    protected function needsCommaAfterBrace(string $content, int $bracePosition): bool
    {
        $afterBrace = substr($content, $bracePosition + 1);
        $trimmed = preg_replace('/^\s*(?:\/\/.*$)?/m', '', $afterBrace);

        return is_string($trimmed) && $trimmed !== '' && !str_starts_with($trimmed, '}');
    }

    /**
     * Find the closing brace that matches an opening brace.
     *
     * @param string $content Existing file contents
     * @param int $openBracePos Opening brace position
     * @return int|false
     */
    protected function findMatchingClosingBrace(string $content, int $openBracePos): int|false
    {
        $braceCount = 1;
        $length = strlen($content);
        $stringQuote = null;
        $escaped = false;

        for ($i = $openBracePos + 1; $i < $length; $i++) {
            $char = $content[$i];

            if ($stringQuote === null) {
                if ($char === '{') {
                    $braceCount++;
                } elseif ($char === '}') {
                    $braceCount--;

                    if ($braceCount === 0) {
                        return $i;
                    }
                } elseif (($char === '"' || $char === "'") && !$escaped) {
                    $stringQuote = $char;
                }
            } elseif ($char === $stringQuote && !$escaped) {
                $stringQuote = null;
            }

            $escaped = ($char === '\\' && !$escaped);
        }

        return false;
    }

    /**
     * Whether a comma is needed before a closing brace.
     *
     * @param string $content Existing file contents
     * @param int $openBracePos Opening brace position
     * @param int $closeBracePos Closing brace position
     * @return bool
     */
    protected function needsCommaBeforeClosingBrace(string $content, int $openBracePos, int $closeBracePos): bool
    {
        $innerContent = substr($content, $openBracePos + 1, $closeBracePos - $openBracePos - 1);
        $trimmed = preg_replace('/\s+|\/\/.*$/m', '', $innerContent);

        if (!is_string($trimmed) || $trimmed === '' || str_ends_with($trimmed, '{')) {
            return false;
        }

        return !str_ends_with($trimmed, ',');
    }

    /**
     * Find the position where a comma should be inserted before a closing brace.
     *
     * @param string $content Existing file contents
     * @param int $openBracePos Opening brace position
     * @param int $closeBracePos Closing brace position
     * @return int
     */
    protected function findCommaInsertionPoint(string $content, int $openBracePos, int $closeBracePos): int
    {
        for ($i = $closeBracePos - 1; $i > $openBracePos; $i--) {
            $char = $content[$i];

            if (in_array($char, [' ', "\t", "\n", "\r"], true)) {
                continue;
            }

            if ($i > 0 && $content[$i - 1] === '/' && $char === '/') {
                $lineStart = strrpos($content, "\n", $i - strlen($content));

                if ($lineStart === false) {
                    $lineStart = 0;
                }

                $i = $lineStart;

                continue;
            }

            if ($char !== ',') {
                return $i + 1;
            }

            return -1;
        }

        return $openBracePos + 1;
    }

    /**
     * Detect indentation used by existing server definitions.
     *
     * @param string $content Existing file contents
     * @param int $nearPosition Position near which to inspect indentation
     * @return int
     */
    public function detectIndentation(string $content, int $nearPosition): int
    {
        $lines = explode("\n", substr($content, 0, $nearPosition));

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $lines[$i];

            if (preg_match('/^(\s*)"[^"]+"\s*:\s*\{/', $line, $matches) === 1) {
                return strlen($matches[1]);
            }
        }

        return $this->defaultIndentation;
    }

    /**
     * Whether file content is plain JSON without JSON5 features.
     *
     * @param string $content Existing file contents
     * @return bool
     */
    protected function isPlainJson(string $content): bool
    {
        if ($this->hasJson5Features($content)) {
            return false;
        }

        json_decode($content);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Whether file content contains JSON5-only features.
     *
     * @param string $content Existing file contents
     * @return bool
     */
    protected function hasJson5Features(string $content): bool
    {
        if ($this->hasUnquotedComments($content)) {
            return true;
        }

        if (preg_match('/,\s*[\]}]/', $content) === 1) {
            return true;
        }

        if (preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*:/m', $content) === 1) {
            return true;
        }

        return $this->hasSingleQuotedStrings($content);
    }

    /**
     * Whether file content contains line or block comments.
     *
     * @param string $content Existing file contents
     * @return bool
     */
    protected function hasUnquotedComments(string $content): bool
    {
        $pattern = '/"(?:\\\\.|[^"\\\\])*"|(\/\/.*)|(\\/\\*[\\s\\S]*?\\*\\/)/';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!empty($match[1]) || !empty($match[2])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether file content contains single-quoted strings.
     *
     * @param string $content Existing file contents
     * @return bool
     */
    protected function hasSingleQuotedStrings(string $content): bool
    {
        $pattern = '/"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/';

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[0] as $match) {
                if ($match[0] === "'") {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Create a new configuration file with queued servers.
     *
     * @return bool
     */
    protected function createNewFile(): bool
    {
        $config = $this->baseConfig;
        $this->addServersToConfig($config);

        return $this->writeJsonConfig($config);
    }

    /**
     * Merge queued servers into a configuration object.
     *
     * @param object|array<string, mixed> $config Configuration object
     * @return void
     */
    protected function addServersToConfig(array|object &$config): void
    {
        if (is_array($config)) {
            $config = (object)$config;
        }

        if (!isset($config->{$this->configKey}) || !is_object($config->{$this->configKey})) {
            $config->{$this->configKey} = new stdClass();
        }

        foreach ($this->serversToAdd as $key => $serverConfig) {
            $config->{$this->configKey}->{$key} = $serverConfig;
        }
    }

    /**
     * Write a configuration object as pretty JSON.
     *
     * @param object $config Configuration object
     * @return bool
     */
    protected function writeJsonConfig(object $config): bool
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            return false;
        }

        $json = str_replace("\r\n", "\n", $json);

        return $this->writeFile($json);
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
     * Whether a new file should be written instead of updating an existing one.
     *
     * @return bool
     */
    protected function shouldWriteNew(): bool
    {
        if (!is_file($this->filePath)) {
            return true;
        }

        return filesize($this->filePath) < 3;
    }

    /**
     * Read the target configuration file.
     *
     * @return string
     */
    protected function readFile(): string
    {
        $contents = file_get_contents($this->filePath);

        return is_string($contents) ? $contents : '';
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
