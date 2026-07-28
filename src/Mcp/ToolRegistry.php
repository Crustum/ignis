<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp;

use Cake\Core\Configure;
use DirectoryIterator;

/**
 * Discovers MCP tools permitted for isolated execution.
 *
 * Built-in tools from {@see Tools} are discovered once and cached. Entries in
 * `Ignis.mcp.tools.include` are merged into that cache on every lookup so
 * plugins that append includes after Ignis bootstrap still become allowed.
 */
class ToolRegistry
{
    /**
     * Cached list of available tool classes.
     *
     * @var array<int, class-string>|null
     */
    private static ?array $cachedTools = null;

    /**
     * Get configured and discovered tool classes.
     *
     * @return array<int, class-string>
     */
    public static function getAvailableTools(): array
    {
        if (self::$cachedTools === null) {
            self::$cachedTools = self::discoverBuiltinTools();
        }

        self::mergeIncludedTools();

        return self::$cachedTools;
    }

    /**
     * Determine whether a tool is permitted.
     *
     * @param string $toolClass Tool class name
     * @return bool Whether the tool is allowed
     */
    public static function isToolAllowed(string $toolClass): bool
    {
        return in_array($toolClass, self::getAvailableTools(), true);
    }

    /**
     * Clear the tool discovery cache.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$cachedTools = null;
    }

    /**
     * Get tool names mapped to their class names.
     *
     * @return array<string, class-string>
     */
    public static function getToolNames(): array
    {
        $names = [];

        foreach (self::getAvailableTools() as $toolClass) {
            $names[substr(strrchr($toolClass, '\\') ?: $toolClass, 1)] = $toolClass;
        }

        return $names;
    }

    /**
     * Discover Ignis built-in tool classes from the Tools directory.
     *
     * @return array<int, class-string>
     */
    private static function discoverBuiltinTools(): array
    {
        $tools = [];
        $excluded = self::excludedTools();
        $toolDir = new DirectoryIterator(__DIR__ . DIRECTORY_SEPARATOR . 'Tools');

        foreach ($toolDir as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = 'Crustum\\Ignis\\Mcp\\Tools\\' . $file->getBasename('.php');

            if (class_exists($class) && !in_array($class, $excluded, true)) {
                $tools[] = $class;
            }
        }

        return $tools;
    }

    /**
     * Append newly configured include tools into the cache.
     *
     * @return void
     */
    private static function mergeIncludedTools(): void
    {
        if (self::$cachedTools === null) {
            self::$cachedTools = [];
        }

        $excluded = self::excludedTools();
        $included = Configure::read('Ignis.mcp.tools.include', []);

        if (!is_array($included)) {
            return;
        }

        foreach ($included as $tool) {
            if (!is_string($tool)) {
                continue;
            }

            if ($tool === '') {
                continue;
            }

            if (!class_exists($tool)) {
                continue;
            }

            if (in_array($tool, $excluded, true)) {
                continue;
            }

            if (in_array($tool, self::$cachedTools, true)) {
                continue;
            }

            self::$cachedTools[] = $tool;
        }
    }

    /**
     * @return array<int, string>
     */
    private static function excludedTools(): array
    {
        $excluded = Configure::read('Ignis.mcp.tools.exclude', []);

        if (!is_array($excluded)) {
            return [];
        }

        return array_values(array_filter($excluded, is_string(...)));
    }
}
