<?php
declare(strict_types=1);

namespace Crustum\Ignis\Tinker;

use JsonSerializable;
use Throwable;

/**
 * Executes PHP code in the bootstrapped CakePHP application context.
 */
class TinkerExecutor
{
    /**
     * Execute PHP code and return a structured JSON-safe result.
     *
     * @param string $code PHP code to execute without opening tags
     * @param int $timeout Maximum execution time in seconds
     * @return array<string, mixed>
     */
    public function execute(string $code, int $timeout = 30): array
    {
        $timeout = min(max(1, $timeout), 180);

        if (trim($code) === '') {
            return [
                'success' => false,
                'error' => 'No code provided',
                'type' => 'InvalidArgumentException',
            ];
        }

        ini_set('memory_limit', '256M');
        set_time_limit($timeout);

        $code = str_replace(['<?php', '<?', '?>'], '', $code);
        $code = $this->prepareCode($code);

        ob_start();

        try {
            // phpcs:disable Squiz.PHP.Eval.Discouraged
            $result = eval($code);
            // phpcs:enable Squiz.PHP.Eval.Discouraged
            $output = ob_get_contents();

            $response = [
                'success' => true,
                'result' => $this->serializeResult($result),
                'output' => $output !== '' ? $output : null,
                'type' => get_debug_type($result),
            ];

            if (is_object($result)) {
                $response['class'] = $result::class;
            }

            if (is_array($result)) {
                $response['count'] = count($result);
            }

            return $response;
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'error' => $throwable->getMessage(),
                'type' => $throwable::class,
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ];
        } finally {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
    }

    /**
     * Serialize a runtime value for JSON output.
     *
     * @param mixed $result Runtime value
     * @return mixed
     */
    protected function serializeResult(mixed $result): mixed
    {
        if ($result === null || is_scalar($result)) {
            return $result;
        }

        if (is_array($result)) {
            return array_map($this->serializeResult(...), $result);
        }

        if (is_object($result)) {
            if (method_exists($result, 'toArray')) {
                return $result->toArray();
            }

            if ($result instanceof JsonSerializable) {
                return $result->jsonSerialize();
            }

            if (method_exists($result, '__toString')) {
                return (string)$result;
            }

            return [
                '__class' => $result::class,
                '__properties' => get_object_vars($result),
            ];
        }

        if (is_resource($result)) {
            return [
                '__type' => 'resource',
                '__resource_type' => get_resource_type($result),
            ];
        }

        return null;
    }

    /**
     * Wrap bare expressions so their value is returned from eval().
     *
     * @param string $code Sanitized PHP code
     * @return string
     */
    protected function prepareCode(string $code): string
    {
        $trimmed = trim($code);

        if (preg_match('/^return\b/i', $trimmed)) {
            return $trimmed;
        }

        if (str_contains($trimmed, '{') || substr_count($trimmed, ';') > 1) {
            return $trimmed;
        }

        if ($this->isStatementCode($trimmed)) {
            return $trimmed;
        }

        $expression = rtrim($trimmed, ';');

        return 'return (' . $expression . ');';
    }

    /**
     * Determine whether code must run as statements instead of a returned expression.
     *
     * @param string $code Sanitized PHP code
     * @return bool
     */
    protected function isStatementCode(string $code): bool
    {
        return (bool)preg_match(
            '/^\s*(echo|print|unset|throw|if|while|for|foreach|switch|try|do|function|class|interface|trait|namespace|use|declare|include|require|include_once|require_once|goto|global)\b/i',
            $code,
        );
    }
}
