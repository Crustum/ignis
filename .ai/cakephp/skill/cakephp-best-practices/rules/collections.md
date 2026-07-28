# Collection Best Practices

Book: [Collections](https://book.cakephp.org/5/en/core-libraries/collections.html) · [Result Sets](https://book.cakephp.org/5/en/orm/retrieving-data-and-resultsets.html#working-with-result-sets)

## Prefer Collection Methods Over Manual Loops for Transforms

Wrap arrays with `collection()` / `new Collection()` (ResultSets already are collections) and use `map`, `filter`, `extract`, `combine`, and friends.

Incorrect:
```php
$names = [];
foreach ($articles as $article) {
    $names[] = $article->author->name;
}
```

Correct:
```php
$names = collection($articles)->extract('author.name');
```

## Use `extract()` and `combine()` for Common Shapes

```php
$names = $collection->extract('name');

$combined = (new Collection($items))->combine('id', 'name');
// [1 => 'foo', 2 => 'bar']

$grouped = (new Collection($items))->combine('id', 'name', 'parent');
```

Nested paths and matchers:

```php
$numbers = (new Collection($data))->extract('phone_numbers.{*}.number');
```

## Chain Lazily; Materialize Deliberately

Many collection methods return lazy iterators. Call `toArray()` / `toList()` / `compile()` when you need a concrete structure — especially before serializing or counting after filters.

```php
$filtered = $results->filter(function ($row) {
    return $row->is_recent;
});

$list = $filtered->extract('id')->toList();
```

## Watch Memory on Large Result Sets

Collection operations on buffered ORM results can hold the full set in memory. For large finds, disable buffering (and optionally hydration) on the query first — see database performance rules — then iterate.

```php
$results = $articles->find()
    ->disableBufferedResults()
    ->all();
```

## Keep Domain Mutations on Entities/Tables

Collections are for shaping data. Prefer entity methods or Table updates for persistence; do not hide `save()` calls inside opaque `each()` chains without a clear application pattern.

```php
$collection->each(function ($article) {
    // format / gather — avoid buried persistence unless that is the established style
});
```
