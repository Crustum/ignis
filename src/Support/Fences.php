<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

/**
 * Masks fenced code blocks while transforming surrounding markdown.
 */
class Fences
{
    /**
     * Fenced code blocks per CommonMark, so inline backtick runs are not paired.
     */
    private const PATTERN = '/^ {0,3}(?<fence>`{3,}|~{3,})[^\n]*\n.*?(?:^ {0,3}\k<fence>[`~]*[ \t]*$|\z)/ms';

    /**
     * Run a transformation over the markdown outside its fenced code blocks.
     *
     * @param string $content Raw markdown content
     * @param callable(string): string $callback Transformation for content outside fences
     * @return string
     */
    public static function outside(string $content, callable $callback): string
    {
        $fences = [];

        $masked = preg_replace_callback(self::PATTERN, static function (array $matches) use (&$fences): string {
            $placeholder = "\0" . count($fences) . "\0";
            $fences[$placeholder] = $matches[0];

            return $placeholder;
        }, $content);

        if (!is_string($masked)) {
            return $content;
        }

        return str_replace(array_keys($fences), array_values($fences), $callback($masked));
    }
}
