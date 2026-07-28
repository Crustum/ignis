# Conventions & Style

Book: [Structure & Conventions](https://book.cakephp.org/5/en/intro/conventions.html) · [Text](https://book.cakephp.org/5/en/core-libraries/text.html) · [Inflector](https://book.cakephp.org/5/en/core-libraries/inflector.html)

## Follow CakePHP Naming Conventions

| What | Convention | Good | Bad |
|------|------------|------|-----|
| Controller class | Plural + `Controller` | `UsersController` | `UserController`, `user` |
| Controller action | camelBack | `viewMe()` | `view_me()` |
| URL for action | dashed lowercase | `/users/view-me` | `/users/viewMe` |
| Table class | Plural + `Table` | `UsersTable` | `UserTable` |
| Entity class | Singular | `User` | `Users` |
| Database table | plural underscored | `users`, `article_comments` | `user`, `articleComments` |
| Fixture | `*Fixture` under `tests/Fixture` | `ArticlesFixture` | `ArticleFixtures` |
| Template | underscored action name | `templates/Users/view_me.php` | `viewMe.php` |
| Helper / Component / Behavior | CamelCase class | `FormHelper` | `form_helper` |
| Mailer | `*Mailer` | `UserMailer` | `UsersMail` |
| Command | `*Command` | `CleanupCommand` | `cleanup` |
| Plugin | CamelCase namespace + Plugin class | `ContactManagerPlugin` | `contact_manager` |

Controllers are **plural** in CakePHP.

```php
// Correct
class UsersController extends AppController
{
    public function viewMe(): void
    {
    }
}

// Incorrect
class user extends AppController
{
    public function view_me(): void
    {
    }
}
```

Tables vs entities:

```php
// src/Model/Table/UsersTable.php
class UsersTable extends Table {}

// src/Model/Entity/User.php
class User extends Entity {}
```

Treat acronyms as words: `CmsController`, not `CMSController`.

## Prefer Framework Utilities Over Ad Hoc PHP String Code

Use `Cake\Utility\Text` for slugs, truncation, and UUIDs instead of brittle `str_replace` / `substr` stacks.

Incorrect:
```php
$slug = strtolower(str_replace(' ', '-', $title));
$short = substr($text, 0, 100) . '...';
```

Correct:
```php
use Cake\Utility\Text;

$slug = Text::slug($title);
$short = Text::truncate($text, 100);
$id = Text::uuid();
```

Use Inflector when converting between Cake naming forms (pluralize, camelize, underscore) rather than hand-rolled inflection.

## Keep Templates Free of Inline Business Logic and Raw JS Strings

Do not embed large JS/CSS blocks or JSON-encode entities into inline scripts from templates. Prefer helpers, cells, asset pipelines, and `h()`-escaped output.

Incorrect:
```php
<script>let article = <?= json_encode($article) ?>;</script>
```

Correct — pass data through data attributes or a dedicated JS bootstrap the app already uses:
```php
<button type="button" class="js-fav-article" data-id="<?= h($article->id) ?>">
    <?= h($article->title) ?>
</button>
```

## No Unnecessary Comments

Prefer descriptive method and variable names. Docblocks belong on classes/methods per project PHP standards. Config files may keep explanatory comments; PHP/JS application code should not use inline “narrating” comments.

Incorrect:
```php
// Check if published
if ($article->published) {
```

Correct:
```php
if ($article->isPublished()) {
```

## Match Neighboring Files

Before introducing a new helper, service layout, or naming pattern, open sibling controllers/tables/tests and match them. Consistency beats theoretical purity.
