# Caching Best Practices

Book: [Caching](https://book.cakephp.org/5/en/core-libraries/caching.html) · [Query Builder — Caching Results](https://book.cakephp.org/5/en/orm/query-builder.html#caching-loaded-results)

## Prefer `Cache::remember()` Over Manual Read/Write

`remember()` returns the cached value or invokes the callable, stores the result, and returns it.

Incorrect:
```php
use Cake\Cache\Cache;

$val = Cache::read('stats');
if ($val === null) {
    $val = $this->computeStats();
    Cache::write('stats', $val);
}
```

Correct:
```php
use Cake\Cache\Cache;

$val = Cache::remember('stats', function () {
    return $this->computeStats();
});
```

Named config:

```php
$val = Cache::remember('stats', function () {
    return $this->computeStats();
}, 'short');
```

Compare against `null`, not truthiness — a loose `if (!$val)` mistreats valid falsy values such as `false` or `0` as misses. `remember()` also does not prevent concurrent requests from computing the same missing value; use a lock when duplicate computation must be prevented.

## Configure Engines Explicitly

Define durations and engines in `Cache::setConfig()` (often from `config/app.php`) so code chooses a named config instead of inventing TTLs ad hoc.

```php
Cache::setConfig('short', [
    'className' => 'File',
    'duration' => '+1 hours',
    'path' => CACHE,
    'prefix' => 'cake_short_',
]);

Cache::setConfig('long', [
    'className' => 'File',
    'duration' => '+1 week',
    'prefix' => 'cake_long_',
    'path' => CACHE,
]);
```

Read/write against the intended config:

```php
Cache::write('cloud', $cloud, 'short');
$cloud = Cache::read('cloud', 'short');
```

## Use Groups to Invalidate Related Keys

Declare `groups` on a cache config, then `Cache::clearGroup()` when shared data changes.

```php
Cache::setConfig('site_home', [
    'className' => 'Redis',
    'duration' => '+999 days',
    'groups' => ['comment', 'article'],
]);
```

```php
public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
{
    if ($entity->isNew()) {
        Cache::clearGroup('article', 'site_home');
    }
}
```

Clear the group across every config that declares it:

```php
$configs = Cache::groupConfigs('article');
foreach ($configs['article'] as $config) {
    Cache::clearGroup('article', $config);
}
```

## Cache Stable ORM Results on the Query

For infrequently changing finds, use the query’s `cache()` method (see Query Builder book) instead of reimplementing serialization yourself.

```php
$query = $articles->find()
    ->where(['Articles.published' => true])
    ->orderBy(['Articles.created' => 'DESC'])
    ->limit(10)
    ->cache('recent_articles', 'long');
```

## Delete Keys You Own When Data Changes

```php
Cache::delete('recent_articles', 'long');
Cache::deleteMany(['home', 'sidebar'], 'site_home');
```

Use `Cache::clear()` only when wiping an entire config is intentional — it is broader than group or key deletes.

## Atomic Add When Supported

`Cache::add()` writes only if the key is missing (engine permitting). Prefer it over check-then-write races when implementing short-lived locks or “create once” markers. FileEngine has limitations called out in the Caching book — pick Redis/Memcached/APCu when atomicity matters.
