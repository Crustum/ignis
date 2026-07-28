<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Configures a shared Twig environment for Ignis guideline text templates.
 *
 * Autoescape stays disabled intentionally: templates compile to markdown / plain
 * text for agents (not HTML views). Enabling HTML escaping would corrupt code
 * fences and markdown. Trust comes from bundled assets plus remote skill audit
 * and path jailing — not from Twig HTML escaping.
 */
class GuidelineTwig
{
    /**
     * Create a Twig environment for compiling Ignis `.ai/` text templates.
     *
     * @param string|null $filesystemRoot Optional filesystem loader root
     * @return \Twig\Environment
     */
    public static function create(?string $filesystemRoot = null): Environment
    {
        $loader = $filesystemRoot !== null && is_dir($filesystemRoot)
            ? new FilesystemLoader([$filesystemRoot])
            : new FilesystemLoader([]);

        return new Environment($loader, [
            'autoescape' => false,
            'strict_variables' => false,
            'cache' => false,
        ]);
    }

    /**
     * Render a Twig template file to plain text or markdown.
     *
     * @param \Twig\Environment $twig Twig environment
     * @param string $path Absolute template path
     * @param array<string, mixed> $context Template context
     * @return string
     */
    public static function renderFile(Environment $twig, string $path, array $context = []): string
    {
        if (!is_file($path)) {
            return '';
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return '';
        }

        return $twig->createTemplate($content)->render($context);
    }
}
