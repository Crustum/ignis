<?php

declare(strict_types=1);

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Ignis\Middleware\InjectIgnis;
use Psr\Http\Server\RequestHandlerInterface;

function createInjectIgnisResponse(string $body, string $contentType = 'text/html', int $status = 200): Response
{
    return (new Response())
        ->withStatus($status)
        ->withType($contentType === 'text/html' ? 'html' : $contentType)
        ->withStringBody($body);
}

function runInjectIgnis(Response $response): Response
{
    $middleware = new InjectIgnis();
    $request = new ServerRequest();
    $handler = new class ($response) implements RequestHandlerInterface {
        public function __construct(private Response $response)
        {
        }

        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            return $this->response;
        }
    };

    /** @var \Cake\Http\Response $result */
    $result = $middleware->process($request, $handler);

    return $result;
}

it('does not inject into partial html fragment with header tag', function (): void {
    $result = runInjectIgnis(createInjectIgnisResponse('<header></header>'));

    expect((string)$result->getBody())->toBe('<header></header>');
});

it('injects script in html responses', function (string $html): void {
    $result = runInjectIgnis(createInjectIgnisResponse($html));

    expect((string)$result->getBody())->toContain('browser-logger-active');
})->with([
    'with head and body tags' => '<html><head><title>Test</title></head><body></body></html>',
    'with only head tag' => '<html><head></head></html>',
]);

it('does not inject into non-HTML responses', function (): void {
    $json = json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR);
    $result = runInjectIgnis(createInjectIgnisResponse($json, 'json'));

    expect((string)$result->getBody())
        ->toBe($json)
        ->not->toContain('browser-logger-active');
});

it('does not inject script twice', function (): void {
    $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Test Page</title>
    <script id="browser-logger-active">// Already injected</script>
</head>
<body>
    <h1>Hello World</h1>
</body>
</html>
HTML;

    $result = runInjectIgnis(createInjectIgnisResponse($html));

    expect(substr_count((string)$result->getBody(), 'browser-logger-active'))->toBe(1);
});

it('injects before body tag when no head tag', function (): void {
    $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <h1>Hello World</h1>
</body>
</html>
HTML;

    $result = runInjectIgnis(createInjectIgnisResponse($html));

    expect((string)$result->getBody())
        ->toContain('browser-logger-active')
        ->toMatch('/<script[^>]*browser-logger-active[^>]*>.*<\/script>\s*<\/body>/s');
});
