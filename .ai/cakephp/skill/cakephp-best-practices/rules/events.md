# Events Best Practices

Book: [Events System](https://book.cakephp.org/5/en/core-libraries/events.html) · [Table Objects — Callbacks](https://book.cakephp.org/5/en/orm/table-objects.html#lifecycle-callbacks)

## Dispatch Domain Events Instead of Hard-Wiring Side Effects

When a Table or service completes a meaningful action, dispatch an event so mail, stock, analytics, and other concerns can listen without bloating the core method.

```php
use Cake\Event\Event;
use Cake\ORM\Table;

public function place(Order $order): bool
{
    if ($this->save($order)) {
        $this->Cart->remove($order);
        $event = new Event('Order.afterPlace', $this, [
            'order' => $order,
        ]);
        $this->getEventManager()->dispatch($event);

        return true;
    }

    return false;
}
```

## Register Listeners Explicitly

CakePHP does not auto-discover event listeners. Attach callables, listener objects, or `implementedEvents()` maps via `on()` on the instance or global manager.

```php
use Cake\Event\EventManager;

EventManager::instance()->on('Order.afterPlace', $aCallback);

// Or on a table instance
$this->Orders->getEventManager()->on($statistics);
```

Application / plugin `events()` hooks are appropriate when the book’s application listener pattern is already used in the project.

## Prefer Local Managers for Table-Scoped Work

Each Table has its own `EventManager`. Controllers and Views share one. Use the local manager for model lifecycle coupling; use `EventManager::instance()` for cross-cutting application events.

## Use Built-In Model Callbacks When They Fit

Tables already emit `Model.beforeSave`, `Model.afterSave`, `Model.afterSaveCommit`, delete variants, marshal/rules events, and more. Prefer implementing `afterSave` / `afterSaveCommit` (or listening to those names) over inventing parallel event names for ordinary persistence hooks.

```php
public function afterSaveCommit(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
{
    // side effects that should run only after the transaction commits
}
```

`afterSaveCommit` / `afterDeleteCommit` are the book’s answer to “run after the DB commit” — use them for listeners that must not see rolled-back data.

## Keep Listeners Focused

Listeners should do one job (send mail, update stock). Do not reintroduce fat controllers inside closures. Stop propagation only when the Events book’s stop semantics are required and documented for that event.

## Do Not Treat Notifications as Core Events

CakePHP’s Events chapter is not a notification/channel stack. Email belongs in Mailers; queue/notification plugins are out of scope for this core skill.
