<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

use Crustum\Ignis\Support\Fences;

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

        return Fences::outside($content, static function (string $markdown): string {
            $spaced = preg_replace('/(?<!\n)\n(#{1,4} )/m', "\n\n$1", $markdown);
            $spaced = preg_replace('/^(#{1,4} .+)\n(?!\n)/m', "$1\n\n", (string)$spaced);

            return (string)preg_replace('/\n{3,}/', "\n\n", (string)$spaced);
        });
    }
}
