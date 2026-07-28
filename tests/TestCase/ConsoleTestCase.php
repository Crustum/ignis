<?php
declare(strict_types=1);

namespace Crustum\Ignis\Test\TestCase;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;

/**
 * Base test case for Ignis console command feature tests.
 */
abstract class ConsoleTestCase extends IgnisTestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setAppNamespace('TestApp');
        $this->configApplication(
            \TestApp\Application::class,
            [ROOT . DS . 'tests' . DS . 'TestApp' . DS . 'config'],
        );
    }
}
