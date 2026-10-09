# Architecture Best Practices

Book: [Application](https://book.cakephp.org/5/en/development/application.html) · [Dependency Injection](https://book.cakephp.org/5/en/development/dependency-injection.html) · [Plugins](https://book.cakephp.org/5/en/plugins.html) · [Structure & Conventions](https://book.cakephp.org/5/en/intro/conventions.html)

## Keep Controllers Thin; Put Domain Work in Services or Tables

Controllers should accept the request, call a Table/service, `set()` view vars, and return a response/redirect. Extract a discrete business operation into an action or service class when doing so makes the operation easier to reuse or test — not to satisfy an arbitrary line limit. An action class has no special meaning to the framework; follow the project's naming and invocation conventions.

```php
// src/Controller/OrdersController.php
public function checkout(PaymentService $payments): void
{
    $order = $this->Orders->get($this->getRequest()->getQuery('order_id'));
    $result = $payments->processOrder($order);
    $this->set(['result' => $result]);
}
```

## Use Constructor / Action Injection

Register services in `Application::services()` (or a service provider) and type-hint them. Prefer constructor injection for dependencies needed throughout an object's lifetime; action/method injection fits dependencies needed by one action. Avoid locator/service-locator style lookups inside domain classes when DI already covers the type.

Incorrect:
```php
public function checkout(): void
{
    $payments = new PaymentService(new StripeClient(/* … */));
    $payments->processOrder($order);
}
```

Correct:
```php
use Cake\Core\ContainerInterface;

// Application::services()
public function services(ContainerInterface $container): void
{
    $container->add(UsersService::class);
}

// Controller action
public function ssoCallback(UsersService $users): void
{
    if ($this->getRequest()->is('post')) {
        $user = $users->ensureExists($this->getRequest()->getData());
    }
}
```

For Tables as constructor dependencies, the DI book documents delegating `TableContainer` — use it when the application already injects Tables that way.

## Depend on Boundaries You Can Swap

At payment, mail, or external API edges, prefer interfaces (or narrow service classes) when testability or interchangeable implementations justify the abstraction, so tests can substitute fakes. Bind implementations in `services()`.

```php
$container->add(EmailService::class)
    ->addArgument(Mailer::class);
```

## Structure Plugins the Cake Way

Plugins own a namespace, `*Plugin` class, and optional routes/bootstrap/middleware hooks. Load via `Application::bootstrap()` with `addPlugin()` / `addOptionalPlugin()`.

```php
use ContactManager\ContactManagerPlugin;

public function bootstrap(): void
{
    parent::bootstrap();
    $this->addPlugin(ContactManagerPlugin::class);
}
```

Bake new plugins with `bin/cake bake plugin ContactManager` when starting from scratch. Reference plugin classes with `PluginName.Alias` (behaviors, helpers, etc.).

## Respect Layer Boundaries

| Layer | Responsibility |
|-------|----------------|
| Controller / Middleware | HTTP, auth hooks, redirects, serialization |
| Table / Entity / Behavior | Persistence, validation, domain rules on data |
| Mailer / Command | Delivery and CLI entry points |
| View / Cell / Helper | Presentation only |
| Service (optional) | Multi-model workflows and external integrations |

Do not query the ORM from templates for primary page data. Do not put HTML generation in Tables.

## Order Lists Explicitly

Databases do not guarantee order without `ORDER BY`. Choose an order that matches the feature, and add a unique tie-breaker when stable pagination matters. Paginated indexes should set `order` in `$paginate` or on the query.

```php
protected array $paginate = [
    'order' => ['Articles.created' => 'DESC', 'Articles.id' => 'DESC'],
];
```

## Prefer Framework Locks / Transactions Over Ad Hoc Races

Use connection `transactional()` ([Database Basics](https://book.cakephp.org/5/en/orm/database-basics.html)) and documented locking/`epilog('FOR UPDATE')` patterns from the Query Builder when concurrent updates matter. Do not invent helpers that are not in the book.

```php
$connection = $this->Orders->getConnection();
$connection->transactional(function () use ($order) {
    // multiple saves that must commit together
    return true;
});
```

## Stay Inside the Application Skeleton Layout

Place code under the conventional `src/` folders (`Controller`, `Model`, `Mailer`, `Command`, `View`, …). Do not invent parallel top-level trees without an existing project precedent.

Wire HTTP cross-cutting concerns as middleware in `Application::middleware()` (CSRF, HTTPS enforcer, routing middleware) rather than duplicating checks in every controller action.

## Compose the App Through `Application`

Bootstrap, routes, middleware, and `services()` live on the Application class (and plugins). Prefer those hooks over random `require` side effects in config files when adding new infrastructure.
