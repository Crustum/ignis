<?php
declare(strict_types=1);

namespace Crustum\Ignis\Trait;

use Cake\Log\Engine\FileLog;
use Cake\Log\Log;
use Crustum\Mcp\Response;

/**
 * Shared helpers for reading CakePHP log files.
 */
trait ReadsLogsTrait
{
    /**
     * Return the PSR-3 timestamp prefix regex fragment.
     *
     * @return string
     */
    protected function getTimestampRegex(): string
    {
        return '\\[\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}\\]';
    }

    /**
     * Return the regex fragment matching Cake DefaultFormatter timestamps.
     *
     * @return string
     */
    protected function getCakeTimestampRegex(): string
    {
        return '\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}';
    }

    /**
     * Return the regex used to split log entries.
     *
     * @return string
     */
    protected function getEntrySplitRegex(): string
    {
        return '/(?=' . $this->getEntryStartPattern() . ')/';
    }

    /**
     * Return the alternation matching the start of a log entry.
     *
     * @return string
     */
    protected function getEntryStartPattern(): string
    {
        return '(?:' . $this->getTimestampRegex() . '|' . $this->getCakeTimestampRegex() . ')';
    }

    /**
     * Return the regex used to identify error log entries.
     *
     * @return string
     */
    protected function getErrorEntryRegex(): string
    {
        return '/^' . $this->getTimestampRegex() . '.*\\.ERROR:/';
    }

    /**
     * Return the initial chunk size for reverse log scanning.
     *
     * @return int
     */
    protected function getChunkSizeStart(): int
    {
        return 64 * 1024;
    }

    /**
     * Return the maximum chunk size for reverse log scanning.
     *
     * @return int
     */
    protected function getChunkSizeMax(): int
    {
        return 1024 * 1024;
    }

    /**
     * Resolve a configured CakePHP log channel to its file path.
     *
     * @param string $channel Log channel name
     * @param string|null $default Fallback path when the channel does not write to a file
     * @return string
     */
    protected function resolveLogFilePathForChannel(string $channel, ?string $default = null): string
    {
        $config = Log::getConfig($channel);

        if (!is_array($config) || !$this->isFileLogConfig($config)) {
            return $default ?? $this->defaultLogFilePathForChannel($channel);
        }

        return $this->resolveFileLogPath($config, $channel);
    }

    /**
     * Return configured Cake File log channels mapped to absolute file paths.
     *
     * @return array<string, string>
     */
    protected function resolveConfiguredFileLogPaths(): array
    {
        $paths = [];

        foreach (Log::configured() as $channel) {
            $config = Log::getConfig($channel);
            if (!is_array($config)) {
                continue;
            }

            if (!$this->isFileLogConfig($config)) {
                continue;
            }

            $paths[$channel] = $this->resolveFileLogPath($config, $channel);
        }

        return $paths;
    }

    /**
     * Determine whether a log channel uses the Cake File engine.
     *
     * @param array<string, mixed> $config Log channel configuration
     * @return bool
     */
    protected function isFileLogConfig(array $config): bool
    {
        $className = $config['className'] ?? '';

        if ($className === 'File' || $className === FileLog::class) {
            return true;
        }

        return is_string($className) && str_ends_with($className, 'FileLog');
    }

    /**
     * Validate that a log channel exists and uses the File engine.
     *
     * @param string $channel Log channel name
     * @return \Crustum\Mcp\Response|null
     */
    protected function validateFileLogChannel(string $channel): ?Response
    {
        $config = Log::getConfig($channel);

        if (!is_array($config)) {
            return Response::error(
                "Log channel `{$channel}` is not configured. Configured file log channels: "
                . $this->formatConfiguredFileLogChannels(),
            );
        }

        if (!$this->isFileLogConfig($config)) {
            return Response::error("Log channel `{$channel}` is not a file log channel.");
        }

        return null;
    }

    /**
     * Format configured file log channel names for error messages.
     *
     * @return string
     */
    protected function formatConfiguredFileLogChannels(): string
    {
        $channels = array_keys($this->resolveConfiguredFileLogPaths());

        return $channels === [] ? 'none' : implode(', ', $channels);
    }

    /**
     * Resolve the absolute path for a Cake File log engine configuration.
     *
     * @param array<string, mixed> $config Log channel configuration
     * @param string $channel Log channel name
     * @return string
     */
    protected function resolveFileLogPath(array $config, string $channel): string
    {
        $path = rtrim((string)($config['path'] ?? $this->logsDirectory()), DS) . DS;
        $file = (string)($config['file'] ?? $channel);

        if (!str_ends_with($file, '.log')) {
            $file .= '.log';
        }

        return $path . $file;
    }

    /**
     * Determine whether a log line represents an error entry.
     *
     * @param string $line Log line or entry
     * @return bool
     */
    protected function isErrorEntry(string $line): bool
    {
        if (str_starts_with(trim($line), '{')) {
            return $this->isJsonErrorEntry($line);
        }

        if (preg_match($this->getErrorEntryRegex(), $line) === 1) {
            return true;
        }

        return preg_match(
            '/^' . $this->getCakeTimestampRegex() . ' (error|critical|alert|emergency):/i',
            trim($line),
        ) === 1;
    }

    /**
     * Read the last complete log entries from a file.
     *
     * @param string $logFile Absolute log file path
     * @param int $count Number of entries to return
     * @return array<int, string>
     */
    protected function readLastLogEntries(string $logFile, int $count): array
    {
        $chunkSize = $this->getChunkSizeStart();

        do {
            $entries = $this->scanLogChunkForEntries($logFile, $chunkSize);

            if (count($entries) >= $count || $chunkSize >= $this->getChunkSizeMax()) {
                break;
            }

            $chunkSize *= 2;
        } while (true);

        return array_slice($entries, -$count);
    }

    /**
     * Read the most recent error log entry when present.
     *
     * @param string $logFile Absolute log file path
     * @return string|null
     */
    protected function readLastErrorEntry(string $logFile): ?string
    {
        $chunkSize = $this->getChunkSizeStart();

        do {
            $entries = $this->scanLogChunkForEntries($logFile, $chunkSize);

            for ($index = count($entries) - 1; $index >= 0; $index--) {
                if ($this->isErrorEntry($entries[$index])) {
                    return trim($entries[$index]);
                }
            }

            if ($chunkSize >= $this->getChunkSizeMax()) {
                return null;
            }

            $chunkSize *= 2;
        } while (true);
    }

    /**
     * Determine whether the log content uses JSON-per-line formatting.
     *
     * @param string $content Log chunk content
     * @return bool
     */
    protected function isJsonLogFormat(string $content): bool
    {
        $firstLine = strtok($content, "\n");

        if ($firstLine === false || trim($firstLine) === '') {
            return false;
        }

        $trimmed = trim($firstLine);

        if (!str_starts_with($trimmed, '{')) {
            return false;
        }

        json_decode($trimmed);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Determine whether a JSON log entry represents an error.
     *
     * @param string $entry JSON log entry
     * @return bool
     */
    protected function isJsonErrorEntry(string $entry): bool
    {
        $decoded = json_decode(trim($entry), true);

        if (!is_array($decoded)) {
            return false;
        }

        $level = $decoded['level'] ?? $decoded['level_name'] ?? '';

        return strtoupper((string)$level) === 'ERROR' || (int)($decoded['level'] ?? 0) >= 400;
    }

    /**
     * Scan the tail of a log file and return complete entries.
     *
     * @param string $logFile Absolute log file path
     * @param int $chunkSize Chunk size in bytes
     * @return array<int, string>
     */
    protected function scanLogChunkForEntries(string $logFile, int $chunkSize): array
    {
        $fileSize = filesize($logFile);

        if ($fileSize === false) {
            return [];
        }

        $handle = fopen($logFile, 'r');

        if ($handle === false) {
            return [];
        }

        try {
            $offset = max($fileSize - $chunkSize, 0);
            fseek($handle, $offset);

            if ($offset > 0) {
                fgets($handle);
            }

            $content = stream_get_contents($handle);

            if (!is_string($content)) {
                return [];
            }

            if ($this->isJsonLogFormat($content)) {
                return array_values(array_filter(
                    explode("\n", $content),
                    fn(string $line): bool => trim($line) !== '',
                ));
            }

            $entries = preg_split($this->getEntrySplitRegex(), $content, -1, PREG_SPLIT_NO_EMPTY);

            if (!is_array($entries)) {
                return [];
            }

            if ($offset > 0 && isset($entries[0]) && preg_match('/^' . $this->getEntryStartPattern() . '/', $entries[0]) !== 1) {
                array_shift($entries);
            }

            return $entries;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Return the default CakePHP log path for a channel name.
     *
     * @param string $channel Log channel name
     * @return string
     */
    protected function defaultLogFilePathForChannel(string $channel): string
    {
        return $this->logsDirectory() . $channel . '.log';
    }

    /**
     * Return the configured CakePHP logs directory.
     *
     * @return string
     */
    protected function logsDirectory(): string
    {
        if (defined('LOGS')) {
            return LOGS;
        }

        $tmp = defined('TMP') ? TMP : sys_get_temp_dir() . DS;

        return $tmp . 'logs' . DS;
    }
}
