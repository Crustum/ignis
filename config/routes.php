<?php
declare(strict_types=1);

use Cake\Routing\RouteBuilder;
use Crustum\Ignis\Support\BrowserWatcher;

return static function (RouteBuilder $routes): void {
    if (!BrowserWatcher::isEnabled()) {
        return;
    }

    $routes->scope('/', static function (RouteBuilder $builder): void {
        $builder->connect(
            '/_ignis/browser-logs',
            [
                'plugin' => 'Crustum/Ignis',
                'controller' => 'BrowserLogs',
                'action' => 'store',
            ],
            [
                '_name' => 'ignis.browser-logs',
            ],
        );
    });
};
