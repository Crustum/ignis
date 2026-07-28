<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

/**
 * Normalize MCP command strings into executable and argument parts.
 */
class CommandNormalizer
{
    /**
     * Split a command string into executable and arguments.
     *
     * @param string $command Command string
     * @param array<int, string> $args Additional arguments
     * @return array{command: string, args: array<int, string>}
     */
    public static function normalize(string $command, array $args = []): array
    {
        if (str_starts_with($command, '/') || preg_match('#^[a-zA-Z]:[/\\\\]#', $command) === 1) {
            return [
                'command' => $command,
                'args' => $args,
            ];
        }

        $parts = preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || $parts === []) {
            return [
                'command' => $command,
                'args' => $args,
            ];
        }

        $executable = array_shift($parts);

        return [
            'command' => $executable,
            'args' => [...$parts, ...$args],
        ];
    }
}
