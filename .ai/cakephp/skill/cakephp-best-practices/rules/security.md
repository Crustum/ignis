# Security Best Practices

Book: [Security](https://book.cakephp.org/5/en/security.html) · [CSRF](https://book.cakephp.org/5/en/security/csrf.html) · [Form Protection](https://book.cakephp.org/5/en/controllers/components/form-protection.html) · [Entities](https://book.cakephp.org/5/en/orm/entities.html) · [Security Utility](https://book.cakephp.org/5/en/core-libraries/security.html)

## Protect Against Mass Assignment

Define which fields may be assigned via `newEntity()` / `patchEntity()`. Bake and anonymous entities do not protect you by default.

Incorrect:
```php
class Article extends Entity
{
    // no $_accessible — request data can set any column
}
```

Correct:
```php
namespace App\Model\Entity;

use Cake\ORM\Entity;

class Article extends Entity
{
    protected array $_accessible = [
        'title' => true,
        'body' => true,
        '*' => false,
    ];
}
```

Hide secrets from JSON/array exports with `$_hidden` (for example `password`).

Mass assignment controls which attributes patching may set; it does not validate values or authorize the operation.

## Prevent SQL Injection

Use array conditions / query expressions. Never interpolate request data into SQL strings or `epilog()`.

Incorrect:
```php
$query->where("name = '{$this->getRequest()->getQuery('name')}'");
```

Correct:
```php
$query->where(['Users.name' => $this->getRequest()->getQuery('name')]);
```

Bindings protect values, not identifiers such as column names or sort directions. Map user-selected identifiers to an allow-list before they reach the query:

```php
$allowed = ['created', 'title'];
$sort = $this->getRequest()->getQuery('sort', 'created');
$sort = in_array($sort, $allowed, true) ? $sort : 'created';
$direction = strtolower((string)$this->getRequest()->getQuery('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
$query->orderBy(["Articles.$sort" => $direction]);
```

## Escape Output to Prevent XSS

Use `h()` (or helpers that escape) for untrusted content in templates.

Incorrect:
```php
<?= $user->bio ?>
```

Correct:
```php
<?= h($user->bio) ?>
```

## Enable CSRF Middleware

Apply session- or cookie-based CSRF middleware in `Application::middleware()` (or to a route scope). Do not stack both CSRF middlewares together.

```php
use Cake\Http\Middleware\CsrfProtectionMiddleware;
// or SessionCsrfProtectionMiddleware

public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
{
    $middlewareQueue->add(new CsrfProtectionMiddleware([]));

    return $middlewareQueue;
}
```

Build mutating forms with FormHelper so tokens are included.

## Use FormProtection for Form Tampering Checks

Load `FormProtection` and create forms with FormHelper. Unlock only fields that are intentionally dynamic.

```php
public function initialize(): void
{
    parent::initialize();
    $this->loadComponent('FormProtection');
}
```

```php
$this->FormProtection->unlockFields(['optional_field', 'ajax_field']);
```

## Authorize Permission-Dependent Actions

Check authorization for actions that depend on the current user's permissions, using the application's authorization layer (policies, identity checks). Authentication alone does not establish permission, and validation is not authorization. Public actions intentionally available to everyone do not need a redundant authorization check.

## Enforce HTTPS in Production
```php
use Cake\Http\Middleware\HttpsEnforcerMiddleware;

$https = new HttpsEnforcerMiddleware([
    'redirect' => true,
    'disableOnDebug' => true,
    'hsts' => [
        'maxAge' => 60 * 60 * 24 * 365,
        'includeSubDomains' => true,
    ],
]);
$middlewareQueue->add($https);
```

## Keep Secrets Out of Source

Put secrets in environment / config files that are not committed. Read `Security.salt` and other secrets via Configure / `env()` wiring in `config/` — do not hard-code API keys or salts in PHP classes.

Incorrect:
```php
$key = 'hard-coded-production-secret';
```

Correct — configure once, read in app code:
```php
use Cake\Core\Configure;

$salt = Configure::read('Security.salt');
```

## Encrypt Sensitive Values When Needed

Use `Cake\Utility\Security::encrypt()` / `decrypt()` with a strong key (and matching HMAC salt) for values that must not sit in plaintext. Prefer storing hashes for passwords via your authentication stack’s documented APIs — do not roll a custom password scheme from `Security::hash()` alone without following that stack’s book.

```php
use Cake\Utility\Security;

$cipher = Security::encrypt($value, $key);
$plain = Security::decrypt($cipher, $key);
```
