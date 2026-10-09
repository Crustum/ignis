<?php
declare(strict_types=1);

namespace Crustum\Ignis\Test\TestCase;

use Cake\TestSuite\TestCase;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

/**
 * Base test case for all Ignis plugin tests.
 */
abstract class IgnisTestCase extends TestCase
{
    use VerifiesDoubles;
}
