# ORM: Tables & Entities Best Practices

Book: [ORM](https://book.cakephp.org/5/en/orm.html) · [Table Objects](https://book.cakephp.org/5/en/orm/table-objects.html) · [Entities](https://book.cakephp.org/5/en/orm/entities.html) · [Associations](https://book.cakephp.org/5/en/orm/associations.html)

## Use Correct Association Types

Define associations in `Table::initialize()` with `belongsTo`, `hasMany`, `hasOne`, or `belongsToMany`.

```php
namespace App\Model\Table;

use Cake\ORM\Table;

class ArticlesTable extends Table
{
    public function initialize(array $config): void
    {
        $this->belongsTo('Authors');
        $this->hasMany('Comments');
    }
}
```

Customize when conventions are not enough:

```php
$this->belongsTo('Authors', [
    'className' => 'Publishing.Authors',
    'foreignKey' => 'author_id',
    'propertyName' => 'author',
]);
```

## Prefer Custom Finders Over Copied Where Clauses

Extract reusable query constraints into custom finders on the Table (book: custom finders / `find('list')` patterns) instead of repeating the same `where()` in controllers.

Incorrect:
```php
$active = $users->find()
    ->where(['verified' => true, 'activated_at IS NOT' => null])
    ->all();
```

Correct:
```php
use Cake\ORM\Query\SelectQuery;

// In UsersTable
public function findActive(SelectQuery $query): SelectQuery
{
    return $query->where([
        'Users.verified' => true,
        'Users.activated_at IS NOT' => null,
    ]);
}

// Usage
$active = $users->find('active')->all();
```

## Keep Mass Assignment Explicit on Entities

Use `$_accessible` (or `setAccess`) so request data cannot overwrite privileged fields.

Incorrect:
```php
$article = $articles->patchEntity($article, $this->getRequest()->getData());
// role / user_id can be overwritten if accessible
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
        'user_id' => false,
    ];
}
```

## Create Entities Through the Table

Prefer `newEntity()` / `newEmptyEntity()` / `patchEntity()` from the Table so marshaling and validation run.

Incorrect:
```php
$article = new Article($this->getRequest()->getData());
```

Correct:
```php
$articles = $this->fetchTable('Articles');
$article = $articles->newEntity($this->getRequest()->getData());
```
## Load Tables via `fetchTable()`

In controllers, use `$this->fetchTable('Articles')` (or the conventional `$this->Articles` property). Do not scatter ad hoc SQL for routine CRUD.

## Use Behaviors for Cross-Cutting Table Concerns

Timestamp and other shared persistence behavior belong in behaviors configured on the Table, not copy-pasted `beforeSave` blocks. See [Behaviors](https://book.cakephp.org/5/en/orm/behaviors.html).

```php
public function initialize(array $config): void
{
    $this->addBehavior('Timestamp');
    $this->belongsTo('Authors');
}
```

## Save With Explicit Association Options

When patching nested data, pass `associated` so only intended relations marshal and validate.

```php
$article = $articles->patchEntity($article, $data, [
    'associated' => ['Tags', 'Comments'],
]);
$articles->save($article, ['associated' => ['Tags', 'Comments']]);
```

## Prefer Entity Methods for Derived State

Put presentation/domain helpers on the entity (`$_virtual`, accessors) instead of repeating conditionals in every template. Keep persistence side effects in Table callbacks/behaviors.

## Avoid Hardcoded Table Name Strings in App Queries

Prefer Table/Query builder APIs so aliases stay consistent. When raw SQL is unavoidable, document why. Migrations may use explicit table names as frozen snapshots.
