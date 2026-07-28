<?php
declare(strict_types=1);

namespace Crustum\Ignis\Trait;

use Crustum\Ignis\Install\GuidelineAssist;
use Crustum\Ignis\Support\GuidelineTwig;
use Twig\Environment;

/**
 * Renders Ignis guideline and skill Twig templates to markdown.
 */
trait RendersTwigGuidelinesTrait
{
    /**
     * Stored ignis snippet placeholders keyed by token.
     *
     * @var array<string, string>
     */
    private array $storedSnippets = [];

    /**
     * Render template content when the path uses a Twig extension.
     *
     * @param string $content Raw template content
     * @param string $path Template path
     * @param array<string, mixed> $data Additional template context
     * @return string
     */
    protected function renderContent(string $content, string $path, array $data = []): string
    {
        if (!str_ends_with($path, '.twig')) {
            return $content;
        }

        $placeholders = [
            '`' => '___SINGLE_BACKTICK___',
            '<?php' => '___OPEN_PHP_TAG___',
        ];

        $content = str_replace(array_keys($placeholders), array_values($placeholders), $content);

        $twig = $this->createGuidelineTwigEnvironment($path);
        $rendered = $twig->createTemplate($content)->render([
            'assist' => $this->getGuidelineAssist(),
            ...$data,
        ]);

        $rendered = html_entity_decode((string)$rendered, ENT_QUOTES | ENT_HTML5);

        return str_replace(array_values($placeholders), array_keys($placeholders), $rendered);
    }

    /**
     * Replace ignis snippet directives with placeholders before Twig rendering.
     *
     * @param string $content Raw template content
     * @return string
     */
    protected function processIgnisSnippets(string $content): string
    {
        return preg_replace_callback(
            '/(?<!@)@ignissnippet\(\s*(?P<nameQuote>[\'"])(?P<name>[^\1]*?)\1(?:\s*,\s*(?P<langQuote>[\'"])(?P<lang>[^\3]*?)\3)?\s*\)(?P<content>.*?)@endignissnippet/s',
            function (array $matches): string {
                $name = $matches['name'];
                $lang = empty($matches['lang']) ? 'html' : $matches['lang'];
                $snippetContent = trim($matches['content']);
                $placeholder = '___IGNIS_SNIPPET_' . count($this->storedSnippets) . '___';

                $this->storedSnippets[$placeholder] = '<!-- ' . $name . ' -->' . "\n"
                    . '```' . $lang . "\n" . $snippetContent . "\n" . '```' . "\n\n";

                return $placeholder;
            },
            $content,
        ) ?? $content;
    }

    /**
     * Mark `@scoped` blocks with sentinels before Twig rendering.
     *
     * @param string $content Raw template content
     * @return string
     */
    protected function markScopedBlocks(string $content): string
    {
        $fences = [];

        $marked = preg_replace_callback('/(?<fence>`{3,}|~{3,}).*?\k<fence>/s', function (array $matches) use (&$fences): string {
            $placeholder = '___SCOPED_FENCE_' . count($fences) . '___';
            $fences[$placeholder] = $matches[0];

            return $placeholder;
        }, $content);

        if ($marked === null) {
            return $content;
        }

        $marked = preg_replace_callback(
            '/(?<!@)@scoped\(\s*(?P<paths>\[(?:[\s,]|\'[^\']*\'|"[^"]*")*\])\s*\)/s',
            fn(array $matches): string => '___SCOPED_START_' . base64_encode((string)json_encode($this->parseScopedPaths($matches['paths']))) . '___',
            $marked,
        );

        if ($marked === null) {
            return $content;
        }

        $marked = preg_replace('/(?<!@)@endscoped/', '___SCOPED_END___', $marked);

        if ($marked === null) {
            return $content;
        }

        return str_replace(array_keys($fences), array_values($fences), $marked);
    }

    /**
     * Extract `@scoped` blocks from rendered content.
     *
     * @param string $content Rendered content with scoped sentinels
     * @param bool $remove Whether to strip scoped bodies from content
     * @return array{content: string, blocks: array<int, array{paths: array<int, string>, body: string}>}
     */
    protected function extractScopedBlocks(string $content, bool $remove): array
    {
        $blocks = [];

        $matched = preg_match_all(
            '/___SCOPED_START_(?P<paths>[A-Za-z0-9+\/=]*)___|___SCOPED_END___/',
            $content,
            $tokens,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER,
        );

        if ($matched === false || $matched === 0) {
            return ['content' => $this->stripScopedSentinels($content), 'blocks' => []];
        }

        $result = '';
        $cursor = 0;
        $depth = 0;
        $bodyStart = 0;
        $blockPaths = [];
        $nested = false;

        foreach ($tokens as $token) {
            $text = $token[0][0];
            $offset = $token[0][1];

            if (str_starts_with($text, '___SCOPED_START_')) {
                if ($depth === 0) {
                    $result .= substr($content, $cursor, $offset - $cursor);
                    $cursor = $offset;
                    $bodyStart = $offset + strlen($text);
                    $blockPaths = $this->decodeScopedPaths($token['paths'][0]);
                    $nested = false;
                } else {
                    $nested = true;
                }

                $depth++;

                continue;
            }

            if ($depth === 0) {
                continue;
            }

            $depth--;

            if ($depth !== 0) {
                continue;
            }

            $body = $this->stripScopedSentinels(substr($content, $bodyStart, $offset - $bodyStart));

            if ($nested || $blockPaths === []) {
                $result .= $body;
            } else {
                $blocks[] = ['paths' => $blockPaths, 'body' => trim((string)$body)];
                $result .= $remove ? '' : $body;
            }

            $cursor = $offset + strlen($text);
        }

        $result .= substr($content, $cursor);

        return ['content' => $this->stripScopedSentinels($result), 'blocks' => $blocks];
    }

    /**
     * Strip scoped sentinel markers from content.
     *
     * @param string $content Content with sentinels
     * @return string
     */
    protected function stripScopedSentinels(string $content): string
    {
        return preg_replace('/___SCOPED_(?:START_[A-Za-z0-9+\/=]*|END)___/', '', $content) ?? $content;
    }

    /**
     * Decode scoped path list from a sentinel payload.
     *
     * @param string $encoded Base64 JSON payload
     * @return array<int, string>
     */
    protected function decodeScopedPaths(string $encoded): array
    {
        $decoded = json_decode(base64_decode($encoded, true) ?: '', true);

        return array_values(array_filter(is_array($decoded) ? $decoded : [], is_string(...)));
    }

    /**
     * Parse path globs from a `@scoped([...])` expression.
     *
     * @param string $expression Scoped paths expression
     * @return array<int, string>
     */
    protected function parseScopedPaths(string $expression): array
    {
        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $expression, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Render a Twig guideline or skill file to markdown.
     *
     * @param string $twigPath Absolute Twig template path
     * @param array<string, mixed> $data Additional template context
     * @param bool $stripScoped Whether to strip scoped blocks from content
     * @return string
     */
    protected function renderTwigFile(string $twigPath, array $data = [], bool $stripScoped = false): string
    {
        return $this->renderTwigFileWithScopedBlocks($twigPath, $data, $stripScoped)['content'];
    }

    /**
     * Render a Twig file and return content plus extracted scoped blocks.
     *
     * @param string $twigPath Absolute Twig template path
     * @param array<string, mixed> $data Additional template context
     * @param bool $stripScoped Whether to strip scoped blocks from content
     * @return array{content: string, blocks: array<int, array{paths: array<int, string>, body: string}>}
     */
    protected function renderTwigFileWithScopedBlocks(string $twigPath, array $data = [], bool $stripScoped = false): array
    {
        if (!is_file($twigPath)) {
            return ['content' => '', 'blocks' => []];
        }

        $content = file_get_contents($twigPath);

        if ($content === false) {
            return ['content' => '', 'blocks' => []];
        }

        return $this->renderTwigStringWithScopedBlocks($content, $twigPath, $data, $stripScoped);
    }

    /**
     * Render Twig string content and return content plus extracted scoped blocks.
     *
     * @param string $content Raw template content
     * @param string $path Template path
     * @param array<string, mixed> $data Additional template context
     * @param bool $stripScoped Whether to strip scoped blocks from content
     * @return array{content: string, blocks: array<int, array{paths: array<int, string>, body: string}>}
     */
    protected function renderTwigStringWithScopedBlocks(
        string $content,
        string $path,
        array $data = [],
        bool $stripScoped = false,
    ): array {
        $content = $this->processIgnisSnippets($content);
        $content = $this->markScopedBlocks($content);

        $rendered = $this->renderContent($content, $path, $data);
        $rendered = str_replace(array_keys($this->storedSnippets), array_values($this->storedSnippets), $rendered);

        $this->storedSnippets = [];

        return $this->extractScopedBlocks($rendered, $stripScoped);
    }

    /**
     * Return the assist instance injected into Twig templates.
     *
     * @return \Crustum\Ignis\Install\GuidelineAssist
     */
    abstract protected function getGuidelineAssist(): GuidelineAssist;

    /**
     * Create a Twig environment scoped to the template directory.
     *
     * @param string $path Absolute template path
     * @return \Twig\Environment
     */
    protected function createGuidelineTwigEnvironment(string $path): Environment
    {
        return GuidelineTwig::create(dirname($path));
    }
}
