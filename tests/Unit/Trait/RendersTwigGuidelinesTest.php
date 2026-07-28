<?php

declare(strict_types=1);

use Crustum\Ignis\Install\GuidelineAssist;
use Crustum\Ignis\Trait\RendersTwigGuidelinesTrait;

beforeEach(function (): void {
    $assist = Mockery::mock(GuidelineAssist::class);

    $this->renderer = new class ($assist) {
        use RendersTwigGuidelinesTrait;

        public function __construct(private GuidelineAssist $assist)
        {
        }

        public function processSnippets(string $content): string
        {
            return $this->processIgnisSnippets($content);
        }

        public function render(string $content, string $path): string
        {
            return $this->renderContent($content, $path);
        }

        public function renderFile(string $twigPath): string
        {
            return $this->renderTwigFile($twigPath);
        }

        public function getStoredSnippets(): array
        {
            return $this->storedSnippets;
        }

        protected function getGuidelineAssist(): GuidelineAssist
        {
            return $this->assist;
        }
    };
});

afterEach(function (): void {
    Mockery::close();
});

test('ignissnippet directive extracts name and content into fenced code block', function (): void {
    $content = "@ignissnippet('Authentication Example')return Auth::user();@endignissnippet";

    $result = $this->renderer->processSnippets($content);

    expect($result)->toBe('___IGNIS_SNIPPET_0___');

    $snippet = $this->renderer->getStoredSnippets()['___IGNIS_SNIPPET_0___'];
    expect($snippet)
        ->toStartWith('<!-- Authentication Example -->')
        ->toContain('```html')
        ->toContain('return Auth::user();')
        ->toContain('```');
});

test('ignissnippet supports double quotes for name parameter', function (): void {
    $content = '@ignissnippet("Double Quoted")code@endignissnippet';

    $this->renderer->processSnippets($content);

    expect($this->renderer->getStoredSnippets()['___IGNIS_SNIPPET_0___'])
        ->toStartWith('<!-- Double Quoted -->');
});

test('ignissnippet uses specified language in fenced code block', function (): void {
    $content = "@ignissnippet('PHP Example', 'php')\$user = User::find(1);@endignissnippet";

    $this->renderer->processSnippets($content);

    expect($this->renderer->getStoredSnippets()['___IGNIS_SNIPPET_0___'])
        ->toContain('```php')
        ->toContain('$user = User::find(1);');
});

test('multiple ignissnippets are replaced with sequential placeholders', function (): void {
    $content = "@ignissnippet('First')code1@endignissnippet between @ignissnippet('Second', 'js')code2@endignissnippet";

    $result = $this->renderer->processSnippets($content);

    expect($result)->toBe('___IGNIS_SNIPPET_0___ between ___IGNIS_SNIPPET_1___')
        ->and($this->renderer->getStoredSnippets())->toHaveCount(2)
        ->and($this->renderer->getStoredSnippets()['___IGNIS_SNIPPET_1___'])->toContain('```js');
});

test('escaped ignissnippet directive is not processed', function (): void {
    $content = "@@ignissnippet('Escaped')content@@endignissnippet";

    $result = $this->renderer->processSnippets($content);

    expect($result)->toBe($content)
        ->and($this->renderer->getStoredSnippets())->toBeEmpty();
});

test('ignissnippet preserves multiline content', function (): void {
    $content = "@ignissnippet('Multiline')\$user = User::find(1);\n\$user->name = 'John';\n\$user->save();@endignissnippet";

    $this->renderer->processSnippets($content);

    expect($this->renderer->getStoredSnippets()['___IGNIS_SNIPPET_0___'])
        ->toContain("\$user = User::find(1);\n\$user->name = 'John';\n\$user->save();");
});

test('non-twig files bypass twig rendering entirely', function (): void {
    $twigContent = '{{ variable }} {% if true %} test {% endif %}';

    $result = $this->renderer->render($twigContent, '/path/to/readme.md');

    expect($result)->toBe($twigContent);
});

test('backticks are preserved through twig rendering for inline code documentation', function (): void {
    $content = 'Run `composer install` then `php bin/cake.php migrations migrate`';

    $result = $this->renderer->render($content, '/path/to/guide.twig');

    expect($result)->toContain('`composer install`')
        ->toContain('`php bin/cake.php migrations migrate`');
});

test('php opening tags are preserved through twig rendering for code examples', function (): void {
    $content = 'Example: <?php echo $greeting; ?>';

    $result = $this->renderer->render($content, '/path/to/guide.twig');

    expect($result)->toContain('<?php');
});

test('html entities from twig expressions are decoded back to plain text for markdown output', function (): void {
    $content = 'Run {{ "tinker --execute \"your code here\"" }}';

    $result = $this->renderer->render($content, '/path/to/guide.twig');

    expect($result)->toContain('tinker --execute "your code here"')
        ->not->toContain('&quot;');
});

test('all common html entities are decoded', function (): void {
    $content = 'Use {{ "a < b & c > d" }}';

    $result = $this->renderer->render($content, '/path/to/guide.twig');

    expect($result)->toContain('a < b & c > d')
        ->not->toContain('&lt;')
        ->not->toContain('&amp;')
        ->not->toContain('&gt;');
});

test('renderTwigFile returns empty string for non-existent file', function (): void {
    $result = $this->renderer->renderFile('/non/existent/guideline.twig');

    expect($result)->toBe('');
});

test('renderTwigFile processes snippets and renders twig in single pipeline', function (): void {
    $tempFile = sys_get_temp_dir() . '/ignis_test_' . uniqid() . '.twig';
    file_put_contents($tempFile, "@ignissnippet('Query', 'php')User::all()@endignissnippet\n\nVersion: {{ \"1.0\" }}");

    try {
        $result = $this->renderer->renderFile($tempFile);

        expect($result)
            ->toContain('<!-- Query -->')
            ->toContain('```php')
            ->toContain('User::all()')
            ->toContain('```')
            ->toContain('Version: 1.0');
    } finally {
        @unlink($tempFile);
    }
});

test('renderTwigFile clears stored snippets after rendering to prevent leakage between files', function (): void {
    $tempFile = sys_get_temp_dir() . '/ignis_test_' . uniqid() . '.twig';
    file_put_contents($tempFile, "@ignissnippet('Test')content@endignissnippet");

    try {
        $this->renderer->renderFile($tempFile);

        expect($this->renderer->getStoredSnippets())->toBeEmpty();
    } finally {
        @unlink($tempFile);
    }
});
