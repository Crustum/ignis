# HTTP Client Best Practices

Book: [Http Client](https://book.cakephp.org/5/en/core-libraries/httpclient.html)

## Always Set Explicit Timeouts

Request options include `timeout` (seconds to wait before timing out). Prefer setting it on the client or per request so slow APIs fail fast.

Incorrect:
```php
$http = new \Cake\Http\Client();
$response = $http->get('https://api.example.com/users');
```

Correct:
```php
use Cake\Http\Client;

$http = new Client([
    'timeout' => 5,
]);

$response = $http->get('https://api.example.com/users');
```

Or override per request:

```php
$response = $http->get('https://api.example.com/users', [], [
    'timeout' => 5,
]);
```

## Prefer Scoped Clients for a Service

Repeating host, scheme, auth, and timeout is error-prone. Create a scoped client once.

Incorrect:
```php
$http = new Client();
$response = $http->get('https://api.example.com/v1/users', [], [
    'auth' => ['username' => 'mark', 'password' => 'secret'],
    'timeout' => 10,
]);
```

Correct:
```php
use Cake\Http\Client;

$http = new Client([
    'host' => 'api.example.com',
    'scheme' => 'https',
    'auth' => ['username' => 'mark', 'password' => 'secret'],
    'timeout' => 10,
]);

$response = $http->get('/v1/users');
```

`Client::createFromUrl()` can set protocol, host, and basePath from a URL:

```php
$http = Client::createFromUrl('https://api.example.com/v1/test');
```

## Handle Response Status Explicitly

Read the body only after you understand the status. Use response helpers from the book (`isOk()`, `isSuccess()`, `isRedirect()`, etc.) instead of assuming JSON is always a success payload.

Incorrect:
```php
$response = $http->get('https://api.example.com/users/1');
$user = $response->getJson();
```

Correct:
```php
$response = $http->get('https://api.example.com/users/1');

if ($response->isOk()) {
    return $response->getJson();
}

if ($response->getStatusCode() === 404) {
    return null;
}

throw new \RuntimeException(sprintf(
    'API error HTTP %s',
    $response->getStatusCode(),
));
```

## Send JSON Bodies With the `type` Option

REST APIs often need a non-form body. Use `type` and pass a string body (or `_content` on GET).

```php
use Cake\Http\Client;

$http = new Client(['timeout' => 10]);
$response = $http->post(
    'https://example.com/tasks',
    json_encode($data),
    ['type' => 'json'],
);
```

## Mock HTTP in Tests With `HttpClientTrait`

Never hit real networks in unit/integration tests. Use Cake’s test helpers.

Incorrect:
```php
public function testCheckout(): void
{
    $this->post('/cart/checkout');
}
```

Correct:
```php
use Cake\Http\TestSuite\HttpClientTrait;
use Cake\TestSuite\TestCase;

class CartControllerTest extends TestCase
{
    use HttpClientTrait;

    public function testCheckout(): void
    {
        $this->mockClientPost(
            'https://example.com/process-payment',
            $this->newClientResponse(200, [], json_encode(['ok' => true])),
        );

        $this->post('/cart/checkout');
    }
}
```

Mock other verbs with `mockClientGet`, `mockClientPut`, `mockClientPatch`, and `mockClientDelete`. Build responses with `newClientResponse($code, $headers, $body)` where headers are a list of strings such as `'Content-Type: application/json'`.
