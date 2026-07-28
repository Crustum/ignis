<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

/**
 * Applies consistent formatting to composed markdown guidelines.
 */
class MarkdownFormatter
{
    /**
     * Apply consistent formatting to markdown content.
     *
     * @param string $content Raw markdown content
     * @return string
     */
    public static function format(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/(?<!\n)\n(#{1,4} )/m', "\n\n$1", $content);
        $content = preg_replace('/(#{1,4} .+)\n(?!\n)/m', "$1\n\n", (string)$content);
        $content = preg_replace('/\n{3,}/', "\n\n", (string)$content);

        return (string)$content;
    }
}
