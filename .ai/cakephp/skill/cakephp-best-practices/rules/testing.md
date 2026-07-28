# Testing Best Practices

Book: [Testing](https://book.cakephp.org/5/en/development/testing.html)

This rule is **PHPUnit + CakePHP TestSuite only**. Do not mix Pest APIs into this skill.

## Use Cake TestCase Conventions

Put tests under `tests/TestCase/…`, name them `*Test.php`, and extend `Cake\TestSuite\TestCase` (or PHPUnit’s `TestCase` when no Cake helpers are needed).

```php
namespace App\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;

class ArticlesTableTest extends TestCase
{
    protected array $fixtures = ['app.Articles'];

    public function testFindPublished(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $query = $table->find('published');
        $this->assertFalse($query->all()->isEmpty());
    }
}
```

Run focused suites with PHPUnit / `bin/cake test` as the project already does.

## Load Fixtures Explicitly

Declare every fixture a test will query. Prefer `getFixtures()` or `$fixtures` as the book shows.

```php
public function getFixtures(): array
{
    return [
        'app.Articles',
        'app.Comments',
    ];
}
```

Create fixture classes under `tests/Fixture/` extending `TestFixture`. Keep schema aligned with migrations / test DB setup documented in the Testing book.

## Prefer Transaction Fixture Strategy When It Fits

Default truncate-per-test can get expensive. `TransactionStrategy` wraps each test in a rolled-back transaction — use it when tests do not depend on resetting auto-increment between methods.

```php
use Cake\TestSuite\Fixture\FixtureStrategyInterface;
use Cake\TestSuite\Fixture\TransactionStrategy;
use Cake\TestSuite\TestCase;

class ArticlesTableTest extends TestCase
{
    protected function getFixtureStrategy(): FixtureStrategyInterface
    {
        return new TransactionStrategy();
    }
}
```

## Controller Tests Use `IntegrationTestTrait`

```php
namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ArticlesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Articles'];

    public function testIndex(): void
    {
        $this->get('/articles');
        $this->assertResponseOk();
    }

    public function testIndexPostData(): void
    {
        $data = [
            'user_id' => 1,
            'published' => 1,
            'slug' => 'new-article',
            'title' => 'New Article',
            'body' => 'New Body',
        ];
        $this->post('/articles', $data);
        $this->assertResponseSuccess();
    }
}
```

## Enable CSRF / FormProtection Tokens in Integration Tests

When middleware or FormProtection is on, enable tokens before POSTing:

```php
$this->enableCsrfToken();
$this->enableSecurityToken();
$this->post('/posts/add', ['title' => 'Exciting news!']);
```

## Assert Through Cake Helpers When Available

Use `assertResponseOk()`, `assertResponseContains()`, redirects, and session assertions from `IntegrationTestTrait` before inventing raw response string checks. For Table unit tests, assert on query results and entity state rather than dumping SQL.

## Keep External I/O Out of Unit Tests

Mock HTTP clients, mail transports, and remote APIs. Prefer fixture-backed persistence for ORM behavior. Use Cake mocks and fixtures from the Testing book.
