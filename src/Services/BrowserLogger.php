<?php
declare(strict_types=1);

namespace Crustum\Ignis\Services;

use Cake\Core\Configure;

/**
 * Browser console logging script injected into HTML responses.
 */
class BrowserLogger
{
    /**
     * @var array<int, string>
     */
    private const ALL_BROWSER_LOG_TYPES = [
        'log',
        'debug',
        'info',
        'warning',
        'error',
        'table',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const BROWSER_LOG_LEVEL_TYPES = [
        'error' => ['error'],
        'warning' => ['warning', 'error'],
        'info' => ['info', 'warning', 'error'],
        'debug' => self::ALL_BROWSER_LOG_TYPES,
    ];

    /**
     * Return the browser logger script tag and client code.
     *
     * @return string
     * @throws \JsonException
     */
    public static function getScript(): string
    {
        $endpoint = '/_ignis/browser-logs';
        $attributes = 'id="browser-logger-active"';
        $nonce = Configure::read('Ignis.csp_nonce');
        $captureTypes = json_encode(
            self::captureTypes(Configure::read('Ignis.browser_log_levels')),
            JSON_THROW_ON_ERROR,
        );

        if (is_string($nonce) && $nonce !== '') {
            $attributes .= ' nonce="' . h($nonce) . '"';
        }

        return <<<HTML
<script {$attributes}>
(function() {
    const ENDPOINT = '{$endpoint}';
    const logQueue = [];
    let flushTimeout = null;
    const captureTypes = {$captureTypes};

    console.log('🔍 Browser logger active (MCP server detected). Posting to: ' + ENDPOINT);

    const originalConsole = {
        log: console.log,
        debug: console.debug,
        info: console.info,
        error: console.error,
        warn: console.warn,
        table: console.table
    };

    function safeStringify(obj) {
        const seen = new WeakSet();
        return JSON.stringify(obj, (key, value) => {
            if (typeof value === 'object' && value !== null) {
                if (seen.has(value)) return '[Circular]';
                seen.add(value);
            }
            if (value instanceof Error) {
                return {
                    name: value.name,
                    message: value.message,
                    stack: value.stack
                };
            }
            return value;
        });
    }

    function normalizeType(type) {
        return type === 'warn' ? 'warning' : type;
    }

    function shouldCapture(type) {
        return captureTypes.includes(normalizeType(type));
    }

    function flushLogs() {
        if (logQueue.length === 0) return;

        const batch = logQueue.splice(0, logQueue.length);

        fetch(ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ logs: batch })
        }).catch(err => {
            originalConsole.error('Failed to send logs:', err);
        });
    }

    function scheduleFlush() {
        if (flushTimeout) clearTimeout(flushTimeout);
        flushTimeout = setTimeout(flushLogs, 100);
    }

    ['log', 'debug', 'info', 'error', 'warn', 'table'].forEach(method => {
        console[method] = function(...args) {
            originalConsole[method].apply(console, args);

            try {
                if (!shouldCapture(method)) {
                    return;
                }

                logQueue.push({
                    type: method,
                    timestamp: new Date().toISOString(),
                    data: args.map(arg => {
                        try {
                            return typeof arg === 'object' ? JSON.parse(safeStringify(arg)) : arg;
                        } catch (e) {
                            return String(arg);
                        }
                    }),
                    url: window.location.href,
                    userAgent: navigator.userAgent
                });

                scheduleFlush();
            } catch (e) {
            }
        };
    });

    const originalOnError = window.onerror;
    window.onerror = function ignisErrorHandler(errorMsg, url, lineNumber, colNumber, error) {
        try {
            if (shouldCapture('error')) {
                logQueue.push({
                    type: 'uncaught_error',
                    timestamp: new Date().toISOString(),
                    data: [{
                        message: errorMsg,
                        filename: url,
                        lineno: lineNumber,
                        colno: colNumber,
                        error: error ? {
                            name: error.name,
                            message: error.message,
                            stack: error.stack
                        } : null
                    }],
                    url: window.location.href,
                    userAgent: navigator.userAgent
                });

                scheduleFlush();
            }
        } catch (e) {
        }

        if (originalOnError && typeof originalOnError === 'function') {
            return originalOnError(errorMsg, url, lineNumber, colNumber, error);
        }

        return false;
    }
    window.addEventListener('error', (event) => {
        try {
            if (!shouldCapture('error')) {
                return false;
            }

            logQueue.push({
                type: 'window_error',
                timestamp: new Date().toISOString(),
                data: [{
                    message: event.message,
                    filename: event.filename,
                    lineno: event.lineno,
                    colno: event.colno,
                    error: event.error ? {
                        name: event.error.name,
                        message: event.error.message,
                        stack: event.error.stack
                    } : null
                }],
                url: window.location.href,
                userAgent: navigator.userAgent
            });

            scheduleFlush();
        } catch (e) {
        }

        return false;
    });
    window.addEventListener('unhandledrejection', (event) => {
        try {
            if (!shouldCapture('error')) {
                return false;
            }

            logQueue.push({
                type: 'error',
                timestamp: new Date().toISOString(),
                data: [{
                    message: 'Unhandled Promise Rejection',
                    reason: event.reason instanceof Error ? {
                        name: event.reason.name,
                        message: event.reason.message,
                        stack: event.reason.stack
                    } : event.reason
                }],
                url: window.location.href,
                userAgent: navigator.userAgent
            });

            scheduleFlush();
        } catch (e) {
        }

        return false;
    });

    window.addEventListener('beforeunload', () => {
        if (logQueue.length > 0) {
            navigator.sendBeacon(ENDPOINT, JSON.stringify({ logs: logQueue }));
        }
    });
})();
</script>
HTML;
    }

    /**
     * Resolve console capture types from configured browser log levels.
     *
     * @param mixed $levels Configured levels
     * @return array<int, string>
     */
    private static function captureTypes(mixed $levels): array
    {
        if (!is_array($levels) || $levels === []) {
            return self::ALL_BROWSER_LOG_TYPES;
        }

        $captureTypes = [];

        foreach ($levels as $level) {
            if (!is_string($level)) {
                continue;
            }

            $level = strtolower(trim($level));
            $level = $level === 'warn' ? 'warning' : $level;

            foreach (self::BROWSER_LOG_LEVEL_TYPES[$level] ?? [$level] as $type) {
                $captureTypes[] = $type;
            }
        }

        return array_values(array_unique($captureTypes));
    }
}
