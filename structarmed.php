<?php
declare(strict_types=1);

use Boundwize\StructArmed\Architecture;

return Architecture::define()
    ->layerPattern('Foundation', [
        '/^Crustum\\\\Ignis\\\\(Contracts|Support)(\\\\.*)?$/',
    ])
    ->layerPattern('Trait', '/^Crustum\\\\Ignis\\\\Trait\\\\.*$/')
    ->layerPattern('Install', '/^Crustum\\\\Ignis\\\\Install(\\\\.*)?$/')
    ->layerPattern('Skills', '/^Crustum\\\\Ignis\\\\Skills\\\\.*$/')
    ->layerPattern('Rules', '/^Crustum\\\\Ignis\\\\Rules\\\\.*$/')
    ->layerPattern('Tinker', '/^Crustum\\\\Ignis\\\\Tinker\\\\.*$/')
    ->layerPattern('BrowserLog', '/^Crustum\\\\Ignis\\\\Services\\\\.*$/')
    ->layerPattern('Middleware', '/^Crustum\\\\Ignis\\\\Middleware\\\\.*$/')
    ->layerPattern('Mcp', '/^Crustum\\\\Ignis\\\\Mcp\\\\.*$/')
    ->layerPattern('Command', '/^Crustum\\\\Ignis\\\\Command\\\\.*$/')
    ->layerPattern('Controller', '/^Crustum\\\\Ignis\\\\Controller\\\\.*$/')
    ->layerPattern('Plugin', [
        '/^Crustum\\\\Ignis\\\\(IgnisPlugin|IgnisManager|ServiceProvider)(\\\\.*)?$/',
    ])
    ->ruleset([
        'Foundation' => [],
        'Trait' => ['Foundation'],
        'Install' => ['Foundation', 'Trait'],
        'Skills' => ['Foundation'],
        'Rules' => ['Foundation'],
        'Tinker' => ['Foundation'],
        'BrowserLog' => ['Foundation'],
        'Middleware' => ['BrowserLog'],
        'Mcp' => ['Install', 'Rules', 'Tinker', 'Trait', 'Foundation'],
        'Command' => ['Install', 'Skills', 'Rules', 'Mcp', 'Trait', 'Foundation'],
        'Controller' => ['Foundation'],
        'Plugin' => ['+Command', '+Mcp', 'Middleware', 'Controller'],
    ]);
