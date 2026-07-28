<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

use Cake\Core\Configure;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Browser log watcher enablement checks.
 */
class BrowserWatcher
{
    /**
     * Determine whether browser log ingestion and script injection are active.
     *
     * Active when Ignis should run, the watcher flag is on. Cake `debug` (or
     * `Ignis.force_enable`) is enforced via {@see IgnisRuntime::shouldRun()}.
     * Tests set Configure debug to true in tests/bootstrap.php like a local dev app.
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (!IgnisRuntime::shouldRun()) {
            return false;
        }

        return (bool)Configure::read('Ignis.browser_logs_watcher', true);
    }

    /**
     * Determine whether CSRF validation should be skipped for a request.
     *
     * Host apps must also allow unauthenticated access to
     * `Crustum/Ignis.BrowserLogs::store` (RBAC `bypassAuth` or equivalent).
     * See docs/index.md — Browser Logs on the Website.
     *
     * Pass to the host application CsrfProtectionMiddleware skipCheckCallback:
     *
     * new CsrfProtectionMiddleware([
     *     'skipCheckCallback' => static function (ServerRequestInterface $request): bool {
     *         return BrowserWatcher::shouldSkipCsrf($request)
     *             || $yourExistingSkipLogic($request);
     *     },
     * ])
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return bool
     */
    public static function shouldSkipCsrf(ServerRequestInterface $request): bool
    {
        return $request->getMethod() === 'POST'
            && rtrim($request->getUri()->getPath(), '/') === '/_ignis/browser-logs';
    }
}
