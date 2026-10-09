<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

beforeEach(function (): void {
    Router::reload();
    Configure::write('Ignis.enabled', true);
    Configure::write('Ignis.force_enable', false);
    Configure::write('debug', true);
});

afterEach(function (): void {
    Router::reload();
    Configure::write('Ignis.enabled', true);
    Configure::write('Ignis.force_enable', false);
    Configure::write('Ignis.browser_logs_watcher', true);
    Configure::write('debug', true);
});

/**
 * Load the plugin routes file into the default route collection.
 *
 * @return void
 */
function loadIgnisRoutes(): void
{
    $loader = require pluginSourceFile('config/routes.php');
    $loader(Router::createRouteBuilder('/'));
}

/**
 * Whether the browser-logs route is connected.
 *
 * @return bool
 */
function hasBrowserLogsRoute(): bool
{
    foreach (Router::getRouteCollection()->routes() as $route) {
        if ($route->template === '/_ignis/browser-logs') {
            return true;
        }
    }

    return false;
}

it('registers the browser-logs route when the watcher is enabled', function (): void {
    Configure::write('Ignis.browser_logs_watcher', true);

    loadIgnisRoutes();

    expect(hasBrowserLogsRoute())->toBeTrue();
});

it('skips the browser-logs route when the watcher is disabled', function (): void {
    Configure::write('Ignis.browser_logs_watcher', false);

    loadIgnisRoutes();

    expect(hasBrowserLogsRoute())->toBeFalse();
});
