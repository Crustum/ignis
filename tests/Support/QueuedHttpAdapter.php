<?php
declare(strict_types=1);

namespace Crustum\Ignis\Test\Support;

use Cake\Http\Client\AdapterInterface;
use Cake\Http\Client\Exception\MissingResponseException;
use Cake\Http\Client\Response;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Sequential HTTP adapter for remote-skill unit tests.
 */
class QueuedHttpAdapter implements AdapterInterface
{
    /**
     * @param array<int, \Cake\Http\Client\Response|\Throwable> $queue Queued responses or exceptions
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Cake\Http\Client\Response|null, options: array<string, mixed>}>|null $history Optional request history
     */
    public function __construct(
        protected array $queue,
        protected ?array &$history = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(RequestInterface $request, array $options): array
    {
        if ($this->queue === []) {
            throw new MissingResponseException([
                'method' => $request->getMethod(),
                'url' => (string)$request->getUri(),
            ]);
        }

        $next = array_shift($this->queue);

        if ($next instanceof Throwable) {
            if ($this->history !== null) {
                $this->history[] = [
                    'request' => $request,
                    'response' => null,
                    'options' => $options,
                ];
            }

            throw $next;
        }

        if ($this->history !== null) {
            $this->history[] = [
                'request' => $request,
                'response' => $next,
                'options' => $options,
            ];
        }

        return [$next];
    }
}
