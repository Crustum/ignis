# Controllers Best Practices

Book: [Controllers](https://book.cakephp.org/5/en/controllers.html) · [Pagination](https://book.cakephp.org/5/en/controllers/pagination.html) · [Request & Response](https://book.cakephp.org/5/en/controllers/request-response.html)

## Access Request and Response Through Getters

Always use `$this->getRequest()` and `$this->getResponse()` (and `setResponse()` when replacing the response). Do not read `$this->request` / `$this->response` properties in new code.

Incorrect:
```php
public function edit(): void
{
    if ($this->request->is('post')) {
        $data = $this->request->getData();
    }
}
```

Correct:
```php
public function edit(): void
{
    if ($this->getRequest()->is('post')) {
        $data = $this->getRequest()->getData();
    }
}
```

## Declare Action Return Types

Every controller action must declare a return type. Use `: void` when the action renders a view. Use `\Cake\Http\Response` (or a nullable Response) when the action returns `$this->redirect()` or another response object.

Incorrect:
```php
public function add()
{
    // …
}
```

Correct:
```php
use Cake\Http\Response;

public function index(): void
{
    // renders a view
}

public function delete($id): ?Response
{
    $this->getRequest()->allowMethod(['post', 'delete']);

    return $this->redirect(['action' => 'index']);
}
```

## Pass View Vars With Arrays — Not `compact()`

Prefer an explicit array (or individual `$this->set('name', $value)` calls). Do not use `compact()`.

Incorrect:
```php
$this->set(compact('article', 'comments'));
```

Correct:
```php
$this->set([
    'article' => $article,
    'comments' => $comments,
]);
```

## Keep Controllers Thin

Controller actions convert the request into a response. Load data, call Table/services, `set()` view vars, and `redirect()` — move domain work into Tables, entities, or application services.

Incorrect:
```php
public function add(): ?Response
{
    $article = $this->Articles->newEmptyEntity();
    if ($this->getRequest()->is('post')) {
        $data = $this->getRequest()->getData();
        if (!empty($data['image'])) {
            // move upload, resize, write filesystem…
        }
        $article = $this->Articles->patchEntity($article, $data);
        // sync tags, fire side effects, build email bodies…
        if ($this->Articles->save($article)) {
            return $this->redirect(['action' => 'view', $article->id]);
        }
    }
    $this->set(['article' => $article]);

    return null;
}
```

Correct — validate/persist through the Table; extract side effects:

```php
use Cake\Http\Response;

public function add(): ?Response
{
    $article = $this->Articles->newEmptyEntity();
    if ($this->getRequest()->is('post')) {
        $article = $this->Articles->patchEntity(
            $article,
            $this->getRequest()->getData(),
        );
        if ($this->Articles->save($article)) {
            $this->Flash->success(__('The article has been saved.'));

            return $this->redirect(['action' => 'view', $article->id]);
        }
        $this->Flash->error(__('The article could not be saved.'));
    }
    $this->set(['article' => $article]);

    return null;
}
```

## Load Tables With `fetchTable()`

Use `fetchTable()` for ORM access. Prefer the controller’s default table property when the action is about that resource.

```php
$recent = $this->fetchTable('Articles')->find()
    ->where(['Articles.published' => true])
    ->orderBy(['Articles.created' => 'DESC'])
    ->limit(5)
    ->all();
```

## Paginate Lists Instead of Unbounded Finds

```php
protected array $paginate = [
    'limit' => 25,
    'order' => ['Articles.created' => 'DESC'],
];

public function index(): void
{
    $query = $this->Articles->find()->contain(['Authors']);
    $articles = $this->paginate($query);
    $this->set(['articles' => $articles]);
}
```

## Negotiate JSON Explicitly When Needed

Register view classes and return serializable data rather than echoing JSON by hand.

```php
use Cake\View\JsonView;

public function viewClasses(): array
{
    return [JsonView::class];
}

public function view($id): void
{
    $article = $this->Articles->get($id, contain: ['Authors']);
    $this->set(['article' => $article]);
    $this->viewBuilder()->setOption('serialize', ['article']);
}
```

## Redirect With Routing Arrays

Prefer routing arrays (or named routes) over hard-coded path strings. See also [`routing.md`](routing.md).

```php
return $this->redirect(['action' => 'view', $article->id]);
return $this->redirect(['_name' => 'login']);
```
