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

## Use Resource Routes for Resourceful Actions

Use `resources()` when the endpoint follows standard resource actions. Define explicit routes when the behavior does not fit that vocabulary.

Organize each controller around one resource where the app does so. When a controller needs a custom action such as `publish`, `approve`, or `archive`, first consider whether that behavior represents a separate resource — a focused controller gives the behavior its own authorization, validation, and middleware boundary:

```php
// Custom action on the primary controller:
$routes->post('/podcasts/{id}/publish', ['controller' => 'Podcasts', 'action' => 'publish']);

// The published podcast modeled as a resource:
$routes->post('/published-podcasts/{id}', ['controller' => 'PublishedPodcasts', 'action' => 'add']);
$routes->delete('/published-podcasts/{id}', ['controller' => 'PublishedPodcasts', 'action' => 'delete']);
```

Treat a custom verb as a design signal, not proof that another controller is required. Use query parameters for simple filtering, and keep an explicit action route when modeling the operation as a resource would obscure the domain or conflict with established project conventions.

When the operation extracts into an action class, call its entry point `handle()`:

```php
// src/Action/PublishPodcast.php
$result = (new PublishPodcast())->handle($podcast);
```
