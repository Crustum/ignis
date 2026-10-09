# Database Performance Best Practices

Book: [Retrieving Data & Result Sets](https://book.cakephp.org/5/en/orm/retrieving-data-and-resultsets.html) · [Query Builder](https://book.cakephp.org/5/en/orm/query-builder.html) · [CounterCache](https://book.cakephp.org/5/en/orm/behaviors/counter-cache.html) · [Migrations](https://book.cakephp.org/migrations/)

## Always Eager Load Associations You Will Read

By default, CakePHP does **not** load associated data on `find()`. Accessing unloaded associations later causes extra queries (or missing data). Use `contain()` when you will read related entities.

Incorrect (association accessed without contain):
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

foreach ($articles as $article) {
    echo $article->author->name;
}
```

Constrain contained associations and select only needed fields (include foreign keys so associations can map):

```php
$query = $articles->find()
    ->select(['Articles.id', 'Articles.title', 'Articles.author_id'])
    ->contain([
        'Authors' => [
            'fields' => ['Authors.id', 'Authors.name'],
        ],
        'Comments' => function ($q) {
            return $q
                ->select(['Comments.id', 'Comments.article_id', 'Comments.body'])
                ->where(['Comments.approved' => true])
                ->orderBy(['Comments.created' => 'DESC'])
                ->limit(10);
        },
    ]);
```

## Select Only Needed Columns

Avoid selecting every column on wide tables, especially large text or JSON fields.

Incorrect:
```php
$articles = $articlesTable->find()->contain(['Authors'])->all();
```

Correct:
```php
$articles = $articlesTable->find()
    ->select(['Articles.id', 'Articles.title', 'Articles.author_id', 'Articles.created'])
    ->contain([
        'Authors' => [
            'fields' => ['Authors.id', 'Authors.name'],
        ],
    ])
    ->all();
```

## Prefer Aggregates and Counts in SQL

Do not load full association collections just to call `count()` in PHP.

Incorrect:
```php
$articles = $articlesTable->find()->contain(['Comments'])->all();
foreach ($articles as $article) {
    echo count($article->comments);
}
```

Correct — count in the query (grouped), or keep a denormalized count with CounterCache:

```php
$query = $articles->find();
$query->select([
    'Articles.id',
    'Articles.title',
    'comment_count' => $query->func()->count('Comments.id'),
])
    ->leftJoinWith('Comments')
    ->groupBy(['Articles.id']);
```

CounterCache (belongsTo side updates a column on the parent):

```php
// On CommentsTable
$this->addBehavior('CounterCache', [
    'Articles' => ['comment_count'],
]);
```

Then list articles using the stored `comment_count` column instead of loading comments.

## Stream Large Result Sets

Buffered result sets hold rows in memory. For large read-only iteration, disable buffering (and skip hydration when entities are unnecessary).

Incorrect:
```php
$users = $usersTable->find()->all();
foreach ($users as $user) {
    // process every row while all stay buffered
}
```

Correct:
```php
$users = $usersTable->find()
    ->disableBufferedResults()
    ->all();

foreach ($users as $user) {
    // …
}
```

Or arrays without entities:

```php
$rows = $usersTable->find()
    ->enableHydration(false)
    ->disableBufferedResults()
    ->all();
```

Paginate user-facing lists with the framework pagination APIs instead of loading unbounded sets into a template.

## Cache Stable Query Results

For data that changes infrequently, use query result caching from the Query Builder book:

```php
$query = $articles->find()
    ->where(['Articles.published' => true])
    ->orderBy(['Articles.created' => 'DESC'])
    ->limit(10)
    ->cache('recent_articles', 'dbResults');
```

Dynamic keys from the query:

```php
$query->cache(function ($q) {
    return 'articles-' . md5(serialize($q->clause('where')));
});
```

Invalidate or shorten TTL when the underlying data changes (see caching rules).

## Add Indexes for Filter, Sort, and Join Columns

Index columns used in `WHERE`, `ORDER BY`, `JOIN`, and `GROUP BY` — but a column appearing in those clauses does not automatically need its own index. Weigh selectivity and write cost, prefer composite indexes matching common filter + sort patterns, and avoid redundant indexes whose leading columns duplicate an existing index. Migrations support `addIndex()` (and bake can generate indexed columns).

Incorrect:
```php
$table->addColumn('status', 'string', [
    'default' => null,
    'limit' => 32,
    'null' => false,
]);
```

Correct:
```php
$table->addColumn('status', 'string', [
    'default' => null,
    'limit' => 32,
    'null' => false,
])
    ->addIndex(['status'])
    ->addIndex(['user_id'])
    ->addIndex(['status', 'created']);
```

Composite indexes should match common filter + sort patterns (for example status then created).

## No Queries in Templates

Pass data from controllers (or view cells that encapsulate their own contained finds). Do not run Table finds inside `.php` templates.

Incorrect (in a template):
```php
<?php foreach ($this->fetchTable('Users')->find()->all() as $user): ?>
    <?= h($user->profile->name) ?>
<?php endforeach; ?>
```

Correct — controller:
```php
$users = $this->fetchTable('Users')
    ->find()
    ->contain(['Profiles'])
    ->all();
$this->set(['users' => $users]);
```

Template:
```php
<?php foreach ($users as $user): ?>
    <?= h($user->profile->name) ?>
<?php endforeach; ?>
```
