<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;

/**
 * Test application for Ignis plugin tests.
 */
class Application extends BaseApplication
{
    /**
     * @return void
     */
    #[\Override]
    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('Crustum/Ignis');
    }

    /**
     * @param \Cake\Routing\RouteBuilder $routes Route builder.
     * @return void
     */
    #[\Override]
    public function routes(RouteBuilder $routes): void
    {
    }

    /**
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue Middleware queue.
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }
}
