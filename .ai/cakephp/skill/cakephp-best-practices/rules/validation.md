# Validation & Application Rules Best Practices

Book: [Validating Data](https://book.cakephp.org/5/en/orm/validation.html) · [Validation](https://book.cakephp.org/5/en/core-libraries/validation.html)

## Define Validators on the Table

Put field validation in `validationDefault()` (and additional named sets) on the Table. Validate when building entities from request data — not with ad hoc checks scattered in controllers.

Incorrect:
```php
public function add(): void
{
    $data = $this->getRequest()->getData();
    if (empty($data['title'])) {
        $this->Flash->error('Title required');

        return;
    }
    $article = $this->Articles->newEntity($data);
    $this->Articles->save($article);
}
```

Correct:
```php
use Cake\Validation\Validator;

public function validationDefault(Validator $validator): Validator
{
    $validator
        ->requirePresence('title', 'create')
        ->notEmptyString('title');

    $validator
        ->allowEmptyString('link')
        ->add('link', 'valid-url', ['rule' => 'url']);

    return $validator;
}

// Controller
$article = $this->Articles->newEntity($this->getRequest()->getData());
if ($article->getErrors()) {
    // redisplay form with errors
}
```

## Use Named Validation Sets When Contexts Differ

Create/update/signup often need different presence rules. Pass `validate` to `newEntity()` / `patchEntity()`.

```php
public function validationUpdate(Validator $validator): Validator
{
    $validator
        ->notEmptyString('title', __('You need to provide a title'))
        ->notEmptyString('body', __('A body is required'));

    return $validator;
}

$article = $articles->patchEntity($article, $data, [
    'validate' => 'update',
]);
```

Per-association validators:

```php
$article = $articles->patchEntity($article, $data, [
    'validate' => 'update',
    'associated' => [
        'Users' => ['validate' => 'signup'],
        'Comments' => ['validate' => 'custom'],
    ],
]);
```

## Combine Validators Instead of Duplicating Rules

```php
public function validationHardened(Validator $validator): Validator
{
    $validator = $this->validationDefault($validator);
    $validator->add('password', 'length', [
        'rule' => ['minLength', 12],
    ]);

    return $validator;
}
```

## Prefer Application Rules for Domain Constraints

Validators check request shape. RulesChecker enforces uniqueness, foreign keys, and workflow against application state on `save()` / `delete()`.

```php
use Cake\ORM\RulesChecker;

public function buildRules(RulesChecker $rules): RulesChecker
{
    $rules->add($rules->isUnique(['email']));
    $rules->add($rules->existsIn('article_id', 'Articles'));

    $rules->add([$this, 'isValidState'], 'validState', [
        'errorField' => 'status',
        'message' => 'This invoice cannot be moved to that status.',
    ]);

    return $rules;
}
```

## Never Mass-Assign Unvalidated Request Bags Blindly

Pass request data through `newEntity()` / `patchEntity()` so validation, accessible fields, and association options apply. Do not bypass the Table and write arbitrary request keys onto entities without those guards.

Incorrect:
```php
$article = $this->Articles->get($id);
$article->set($this->getRequest()->getData());
$this->Articles->save($article);
```

Correct:
```php
$article = $this->Articles->get($id);
$article = $this->Articles->patchEntity($article, $this->getRequest()->getData());
$this->Articles->save($article);
```

Check `$entity->getErrors()` after patch/save failures and surface them in the form.
