# Configuration Best Practices

Book: [Configuration](https://book.cakephp.org/5/en/development/configuration.html)

## Read Environment Variables in Config Files

Use `env()` inside `config/app.php` (and related config) to populate Configure. Application code should read `Configure::read()` / `Configure::readOrFail()` rather than scattering `env()` calls deep in domain classes.

Incorrect — `env()` buried in a Table/service:
```php
$key = env('API_KEY');
```

Correct — wire once in config, read later:
```php
// config/app.php (or config/app_local.php)
'MyService' => [
    'apiKey' => env('API_KEY'),
],

// Application code
use Cake\Core\Configure;

$key = Configure::read('MyService.apiKey');
```

## Use Configure for Runtime Values

```php
Configure::write('Company.name', 'Pizza, Inc.');
Configure::write('Company.slogan', 'Pizza for your body and soul');

Configure::read('Company.name');
Configure::read('Company.slogan');
Configure::readOrFail('Company.name');
```

Load additional files with `Configure::load()` when the application already structures config that way.

## Keep Secrets Out of Version Control

Do not commit production secrets. Prefer `config/app_local.php`, environment variables, or the host’s secret store. `Security.salt` must be unique per deployment.

Use scanner-safe placeholders in documented examples — never values that look like real secrets:

```bash
# A plaintext .env value committed to the repository
STRIPE_SECRET=<your-stripe-secret>
AWS_SECRET_ACCESS_KEY=<your-aws-secret>
```

## Check `debug` via Configure

Incorrect:
```php
if (env('APP_DEBUG')) {
```

Correct:
```php
use Cake\Core\Configure;

if (Configure::read('debug')) {
    // development-only behavior
}
```

`debug` drives error display vs logging — keep it `false` in production.

## Prefer Constants / Enums for Domain States

Avoid magic strings for statuses and types when the codebase already uses class constants or PHP enums. User-facing copy can use `__()` when the app already localizes strings — do not introduce language files solely for English-only apps.

```php
return $this->type === Article::TYPE_NORMAL;
```
