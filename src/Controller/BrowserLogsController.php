<?php
declare(strict_types=1);

namespace Crustum\Ignis\Controller;

use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Receives browser console log batches from injected client script.
 */
class BrowserLogsController extends AppController
{
    /**
     * Initialize controller state.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->autoRender = false;

        if ($this->components()->has('Authentication')) {
            /** @var \Authentication\Controller\Component\AuthenticationComponent $authentication */
            $authentication = $this->components()->get('Authentication');
            $authentication->addUnauthenticatedActions(['store']);
        }
    }

    /**
     * Persist browser log entries posted by the injected client script.
     *
     * @return \Cake\Http\Response
     */
    public function store(): Response
    {
        $this->request->allowMethod(['post']);

        foreach ($this->extractLogs($this->request) as $log) {
            if (!is_array($log)) {
                continue;
            }

            $context = array_filter([
                'url' => $log['url'] ?? null,
                'user_agent' => empty($log['userAgent']) ? null : $log['userAgent'],
                'timestamp' => $log['timestamp'] ?? DateTime::now()->format('c'),
            ], static fn(mixed $value): bool => $value !== null && $value !== '');

            $message = self::buildLogMessageFromData($log['data'] ?? []);

            if ($context !== []) {
                $encodedContext = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if (is_string($encodedContext)) {
                    $message .= ' ' . $encodedContext;
                }
            }

            Log::write(
                $this->mapLogLevel($log['type'] ?? 'log'),
                $message,
                [
                    'scope' => ['browser'],
                ],
            );
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode(['status' => 'logged']));
    }

    /**
     * Extract browser log entries from the request body.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return array<int, mixed>
     */
    protected function extractLogs(ServerRequestInterface $request): array
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody) && isset($parsedBody['logs']) && is_array($parsedBody['logs'])) {
            return $parsedBody['logs'];
        }

        $rawBody = (string)$request->getBody();

        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        if (!is_array($decoded)) {
            return [];
        }

        $logs = $decoded['logs'] ?? [];

        return is_array($logs) ? $logs : [];
    }

    /**
     * Map browser log types to PSR-3 levels.
     *
     * @param string $type Browser log type
     * @return string
     */
    protected function mapLogLevel(string $type): string
    {
        return match ($type) {
            'warn' => 'warning',
            'log', 'table' => 'debug',
            'window_error', 'uncaught_error', 'unhandled_rejection' => 'error',
            default => $type,
        };
    }

    /**
     * Build a string message from browser log data.
     *
     * @param array<int|string, mixed> $data Browser log data
     * @return string
     */
    protected static function buildLogMessageFromData(array $data): string
    {
        $messages = [];

        foreach ($data as $value) {
            $messages[] = self::formatLogValue($value);
        }

        return implode(' ', $messages);
    }

    /**
     * Format a single browser log value for file output.
     *
     * @param mixed $value Browser log value
     * @return string
     */
    protected static function formatLogValue(mixed $value): string
    {
        return match (true) {
            is_array($value) => self::formatArrayLogValue($value),
            is_string($value), is_numeric($value) => (string)$value,
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_object($value) => self::encodeJson($value) ?? $value::class,
            default => (string)$value,
        };
    }

    /**
     * Format an array browser log value, preserving object structure as JSON.
     *
     * @param array<int|string, mixed> $value Browser log array value
     * @return string
     */
    protected static function formatArrayLogValue(array $value): string
    {
        if ($value === []) {
            return '[]';
        }

        if (!array_is_list($value)) {
            return self::encodeJson($value) ?? '';
        }

        return implode(' ', array_map(self::formatLogValue(...), $value));
    }

    /**
     * JSON-encode a browser log value when possible.
     *
     * @param mixed $value Value to encode
     * @return string|null
     */
    protected static function encodeJson(mixed $value): ?string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : null;
    }
}
