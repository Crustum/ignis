# Routing Best Practices

Book: [Routing](https://book.cakephp.org/5/en/development/routing.html) · [Middleware](https://book.cakephp.org/5/en/controllers/middleware.html)

## Prefer Named Routes for URLs You Generate Often

Connect routes with `_name` and generate URLs with `Router::url(['_name' => …])` so path changes do not scatter through templates and redirects.

```php
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

$routes->connect(
    '/login',
    ['controller' => 'Users', 'action' => 'login'],
    ['_name' => 'login'],
);

$url = Router::url(['_name' => 'login']);
```

Use `_namePrefix` inside scopes/plugins when many named routes share a namespace.

## Use Scopes, Prefixes, and Resources

Group URL prefixes and defaults with `scope()` / `prefix()`. Use `resources()` for REST collections instead of hand-wiring every verb.

```php
$routes->scope('/api', function (RouteBuilder $routes) {
    $routes->setExtensions(['json']);
    $routes->resources('Articles');
});

$routes->prefix('Admin', function (RouteBuilder $routes) {
    $routes->fallbacks(DashedRoute::class);
});
```

Nested resources when comments belong under articles:

```php
$routes->resources('Articles', function (RouteBuilder $routes) {
    $routes->resources('Comments');
});
```

## Redirect and Link With Routing Arrays

Prefer routing arrays (or named routes) over hard-coded path strings.

```php
return $this->redirect(['action' => 'view', $article->id]);
return $this->redirect(['_name' => 'login']);

echo $this->Html->link('Login', ['_name' => 'login']);
```

Apply CSRF and other middleware to scopes with `registerMiddleware()` / `applyMiddleware()` when protection should not be global — see the Controllers and Security rules for request handling inside actions.
