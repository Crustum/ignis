<?php
declare(strict_types=1);

namespace Crustum\Ignis\Middleware;

use Cake\Http\CallbackStream;
use Crustum\Ignis\Services\BrowserLogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Injects the Ignis browser logger script into HTML responses.
 */
class InjectIgnis implements MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Request handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->shouldInject($response)) {
            return $response;
        }

        $content = (string)$response->getBody();
        $injected = $this->injectScript($content);

        return $response->withBody(new CallbackStream(static fn(): string => $injected));
    }

    /**
     * Determine whether the browser logger script should be injected.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Response
     * @return bool
     */
    protected function shouldInject(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() >= 300 && $response->getStatusCode() < 400) {
            return false;
        }

        $contentType = $response->getHeaderLine('content-type');

        if ($contentType !== '' && !str_contains($contentType, 'html')) {
            return false;
        }

        $content = (string)$response->getBody();

        if (preg_match('/<(html|head)[\s>]/', $content) !== 1) {
            return false;
        }

        return !str_contains($content, 'browser-logger-active');
    }

    /**
     * Inject the browser logger script into HTML content.
     *
     * @param string $content HTML content
     * @return string
     */
    protected function injectScript(string $content): string
    {
        $script = BrowserLogger::getScript();

        if (str_contains($content, '</head>')) {
            return str_replace('</head>', $script . "\n</head>", $content);
        }

        if (str_contains($content, '</body>')) {
            return str_replace('</body>', $script . "\n</body>", $content);
        }

        return $content . $script;
    }
}
