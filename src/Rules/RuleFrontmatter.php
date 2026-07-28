<?php
declare(strict_types=1);

namespace Crustum\Ignis\Rules;

use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Parses YAML frontmatter from Ignis rule markdown files.
 */
class RuleFrontmatter
{
    /**
     * Parse rule frontmatter and body from markdown content.
     *
     * @param string $content Rule markdown content
     * @return array{paths: array<int, string>, body: string}
     */
    public static function parse(string $content): array
    {
        $content = (string)preg_replace('/\R/', "\n", $content);

        if (preg_match('/^\s*---\s*\n(.*?)\n---\s*\n?/s', $content, $matches) !== 1) {
            return ['paths' => [], 'body' => $content];
        }

        try {
            $meta = Yaml::parse($matches[1]);
        } catch (Throwable) {
            return ['paths' => [], 'body' => $content];
        }

        $paths = (array)(is_array($meta) ? ($meta['paths'] ?? []) : []);
        $paths = array_values(array_filter($paths, is_string(...)));

        return [
            'paths' => $paths,
            'body' => substr($content, strlen($matches[0])),
        ];
    }
}
