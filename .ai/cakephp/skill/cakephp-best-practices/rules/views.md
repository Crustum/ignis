# Views Best Practices

Book: [Views](https://book.cakephp.org/5/en/views.html) · [View Cells](https://book.cakephp.org/5/en/views/cells.html) · [Helpers](https://book.cakephp.org/5/en/views/helpers.html)

## Escape Output by Default

Use `h()` (or helpers that escape) for untrusted strings. Never echo raw request or entity data into HTML.

Incorrect:
```php
<?= $article->title ?>
```

Correct:
```php
<?= h($article->title) ?>
```

## Pass Data From Controllers With `set()`

Variables set in the controller are available in the template, layout, and elements. Do not re-fetch Tables inside templates for page primary data.

```php
// Controller
$this->set([
    'article' => $article,
    'comments' => $comments,
]);
```

```php
// templates/Articles/view.php
<h1><?= h($article->title) ?></h1>
```

## Prefer Elements for Reusable Markup

Elements are mini-templates under `templates/element/`. Pass explicit data; do not rely on accidental shared state.

```php
echo $this->element('helpbox', [
    'content' => $helpText,
]);
```

## Prefer View Cells for Reusable UI That Needs Queries

When a sidebar/cart/badge needs its own `fetchTable()` logic, use a Cell instead of stuffing queries into AppController or the layout.

```php
namespace App\View\Cell;

use Cake\View\Cell;

class InboxCell extends Cell
{
    public function display(): void
    {
        $unread = $this->fetchTable('Messages')->find('unread');
        $this->set('unread_count', $unread->count());
    }
}
```

In a template:

```php
<?= $this->cell('Inbox') ?>
```

Bake stubs with `bin/cake bake cell Inbox` when starting new cells.

## Use View Blocks for Layout Slots

Capture scripts/styles/sidebars into named blocks instead of hard-coding them only in the layout.

```php
$this->start('sidebar');
echo $this->element('sidebar/recent_topics');
$this->end();
```

Or append:

```php
$this->append('sidebar', $this->element('sidebar/popular_topics'));
```

In the layout, `echo $this->fetch('sidebar');`.

## Load Scripts and CSS Through HtmlHelper

Prefer `HtmlHelper::script()` / `css()` so assets participate in blocks and avoid duplicate tags when elements render multiple times.

```php
$this->Html->script('app', ['block' => true]);
$this->Html->css('widgets', ['block' => true]);
```

## Keep Templates Free of Business Rules

Templates format and display. Validation, authorization, and persistence belong in Tables, controllers, or cells — not in `templates/` conditionals that mutate data or call `save()`.
