# Query Builder Best Practices

Book: [Query Builder](https://book.cakephp.org/5/en/orm/query-builder.html) · [Retrieving Data & Result Sets](https://book.cakephp.org/5/en/orm/retrieving-data-and-resultsets.html)

## Start From `Table::find()`

Build ORM queries from the Table so aliases, hydration, and associations stay consistent.

```php
$articles = $this->fetchTable('Articles');

$query = $articles
    ->find()
    ->select(['id', 'title'])
    ->where(['Articles.published' => true])
    ->orderBy(['Articles.created' => 'DESC']);

foreach ($query->all() as $article) {
    // …
}
```

## Use `contain()` for Associated Data You Will Read

Incorrect (N+1 / missing associations when touching related data later):
```php
$articles = $articlesTable->find()->all();
foreach ($articles as $article) {
    echo $article->author->name;
}
```

Correct:
```php
$articles = $articlesTable->find()
    ->contain(['Authors', 'Comments'])
    ->all();
```

Restrict contained queries with a callable when needed:

```php
$query = $articles->find()->contain('Comments', function ($q) {
    return $q->where(['Comments.approved' => true]);
});
```

Nested associations:

```php
$query = $articles->find()->contain([
    'Authors' => ['Addresses'],
    'Comments' => ['Authors'],
]);
```

## Prefer `matching()` to Filter by Associated Data

Use `matching()` when primary rows must be restricted by related data (for example articles that have a tag). Use `contain()` when you only need to *load* related rows.

```php
$query = $articles->find()
    ->matching('Tags', function ($q) {
        return $q->where(['Tags.name' => 'CakePHP']);
    });
```

## Select Only Required Columns

Avoid selecting every column on wide tables for list endpoints.

```php
$query = $articles->find()
    ->select(['Articles.id', 'Articles.title']);
```

## Aggregate in SQL, Not in PHP Loops

Use `select()` with functions and `groupBy()` for counts and sums instead of loading full collections and reducing in PHP.

```php
$query = $articles->find();
$query->select([
    'Articles.user_id',
    'count' => $query->func()->count('*'),
])
    ->groupBy(['Articles.user_id']);
```

Use `CASE` expressions from the Query Builder when classifying rows in SQL:

```php
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Query\SelectQuery;

$query = $cities->find()
    ->where(function (QueryExpression $exp, SelectQuery $q) {
        return $exp->addCase(
            [
                $q->expr()->lt('population', 100000),
                $q->expr()->between('population', 100000, 999000),
                $q->expr()->gte('population', 999001),
            ],
            ['SMALL', 'MEDIUM', 'LARGE'],
            ['string', 'string', 'string'],
        );
    });
```

## Prefer Bound Conditions Over Interpolated SQL

Use array conditions or expressions. Do not concatenate untrusted input into SQL fragments.

Incorrect:
```php
$query->where("title = '$title'");
```

Correct:
```php
$query->where(['Articles.title' => $title]);
```

## Use Subqueries Instead of Loading Intermediate Sets

Compose queries as expressions (`IN`, joins) so you do not pull ID lists into PHP first. Prefer `Table::subquery()` when you need a subquery without generated aliases:

```php
$comments = $articles->getAssociation('Comments')->getTarget();

$matchingComment = $comments->subquery()
    ->select(['article_id'])
    ->distinct()
    ->where(['comment LIKE' => '%CakePHP%']);

$query = $articles->find()
    ->where(['id IN' => $matchingComment]);
```

## Sometimes Two Simple Queries Beat One Complex Query

A small selective query whose IDs feed a second condition can be clearer and faster than a deeply nested join — when join/`matching()` options become hard to reason about. They also add a round trip, can transfer a large identifier list, and do not provide a single-query consistency snapshot. Prefer the `IN`-subquery form above when the ID set is large, and decide from query plans and production-like measurements.

```php
$tagIds = $tagsTable->find()
    ->select(['id'])
    ->where(['Tags.name LIKE' => $term . '%'])
    ->all()
    ->extract('id')
    ->toList();

$articles = $articlesTable->find()
    ->matching('Tags', function ($q) use ($tagIds) {
        return $q->where(['Tags.id IN' => $tagIds]);
    })
    ->all();
```

## Cache Stable Result Sets on the Query

```php
$query = $articles->find()
    ->where(['Articles.published' => true])
    ->cache('recent_articles', 'dbResults');
```

See also database performance rules for buffering, CounterCache, and indexes.
