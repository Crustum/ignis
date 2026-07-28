<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

use Cake\Core\Configure;

/**
 * Runtime enablement checks for Ignis.
 */
class IgnisRuntime
{
    /**
     * Determine whether Ignis MCP and debug-oriented HTTP features should run.
     *
     * Requires `Ignis.enabled`. Then requires Cake `debug`, unless
     * `Ignis.force_enable` explicitly opts in (shared/prod footgun escape hatch).
     *
     * @return bool
     */
    public static function shouldRun(): bool
    {
        if (!(bool)Configure::read('Ignis.enabled', true)) {
            return false;
        }

        if ((bool)Configure::read('debug', false)) {
            return true;
        }

        return (bool)Configure::read('Ignis.force_enable', false);
    }
}
