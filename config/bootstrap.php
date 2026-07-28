<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Log\Engine\FileLog;
use Cake\Log\Log;
use Crustum\Ignis\Support\BrowserWatcher;

Configure::load('Crustum/Ignis.ignis', 'default');

if (BrowserWatcher::isEnabled() && Log::getConfig('browser') === null) {
    Log::setConfig('browser', [
        'className' => FileLog::class,
        'path' => LOGS,
        'file' => 'browser',
        'levels' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
        'scopes' => ['browser'],
    ]);
}
