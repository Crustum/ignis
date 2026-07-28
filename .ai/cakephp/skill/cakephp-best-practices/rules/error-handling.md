# Error Handling Best Practices

Book: [Error & Exception Handling](https://book.cakephp.org/5/en/development/errors.html)

## Configure Traps in `config/app.php`

CakePHP uses `ErrorTrap` / `ExceptionTrap`. Tune logging and rendering via the `Error` config keys — do not invent parallel global handlers unless the application already does.

Useful options from the book:

- `log` — log exceptions and stack traces
- `skipLog` — exception class names that should not flood logs (for example common not-found cases)
- `trace` — include stack traces in error logs
- `exceptionRenderer` — custom renderer class under `src/Error`

```php
'Error' => [
    'errorLevel' => E_ALL,
    'skipLog' => [
        'Cake\Http\Exception\NotFoundException',
    ],
    'log' => true,
    'trace' => true,
],
```

## Choose One Customization Style and Stay Consistent

The Errors book lists progressive options: listen to trap events, custom templates, custom error controller methods, or a custom `ExceptionRenderer` / trap. Pick the approach the codebase already uses.

**Event-based** — listen on the trap instance as shown in the Errors book:

```php
use Cake\Core\Configure;
use Cake\Error\ErrorTrap;
use Cake\Error\PhpError;
use Cake\Event\EventInterface;

$errorTrap = new ErrorTrap(Configure::read('Error'));
$errorTrap->getEventManager()->on(
    'Error.beforeRender',
    function (EventInterface $event, PhpError $error) {
        // customize or stop rendering per book options
    },
);
```

`Exception.beforeRender` follows the same pattern on `ExceptionTrap` (stop event, replace exception data, or return a response).

**Exception-specific controller actions** — map `MissingWidgetException` to `missingWidget()` on the error controller when that pattern is already in the app.

**Custom templates** — place templates under `templates/Error/` for framework-rendered pages.

## Skip Noisy Exceptions From Logs

Use `skipLog` for expected client errors instead of catching and swallowing them everywhere.

Incorrect — empty catch that hides failures:
```php
try {
    $article = $this->Articles->get($id);
} catch (RecordNotFoundException $e) {
    // ignored
}
```

Correct — let HTTP exceptions render, and keep them out of logs via config when appropriate:
```php
use Cake\Http\Exception\NotFoundException;

throw new NotFoundException(__('Article not found'));
```

## Prefer Framework HTTP Exceptions for Status Codes

Throw `Cake\Http\Exception\*` types (or application exceptions that extend Cake’s hierarchy) so status codes and rendering stay consistent. Attach context in the exception message / constructor data the renderer already understands — do not invent undocumented reporting interfaces.

## Manage Deprecations Deliberately

Trigger deprecations with `deprecationWarning()` in your own APIs. When upgrading, prefer `Error.ignoredDeprecationPaths` for temporary noise control over disabling all `E_USER_DEPRECATED` long term.

```php
deprecationWarning('5.0', 'The example() method is deprecated. Use getExample() instead.');
```

## Keep `debug` Off in Production

With `debug` false, users see generic error pages while details go to logs (when `log` is enabled). Do not leave verbose exception pages exposed publicly.
