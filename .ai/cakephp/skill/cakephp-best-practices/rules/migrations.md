# Migration Best Practices

Book: [Migrations](https://book.cakephp.org/migrations/)

## Generate Migrations With Bake

Use bake so filenames and class stubs stay consistent.

Incorrect — hand-named file without bake conventions:
```text
config/Migrations/products_migration.php
```

Correct:
```bash
bin/cake bake migration CreateProducts name:string description:text created modified
bin/cake bake migration AddPriceToProducts price:decimal[5,2]
bin/cake bake migration AddNameIndexToProducts name:string:index
```

Empty stub when you need a blank migration:

```bash
bin/cake bake migration CreateProducts
```

## Prefer `change()` for Reversible Schema Edits

The migrations book examples use `change()` for creating/updating tables so the plugin can invert the operations on rollback when possible.

```php
use Migrations\BaseMigration;

class CreateProducts extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('products');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->create();
    }
}
```

Adding a column:

```php
public function change(): void
{
    $table = $this->table('products');
    $table->addColumn('price', 'decimal', [
        'default' => null,
        'null' => false,
        'precision' => 5,
        'scale' => 2,
    ]);
    $table->update();
}
```

Destructive removals may need explicit `up()` / `down()` (bake generates `up()` for remove-column style migrations). Implement a real reverse path when rollback must work in CI.

## Never Modify Deployed Migrations

Once a migration has run in shared or production environments, treat it as immutable. Create a new migration to alter schema.

Incorrect — editing an already-applied CreateProducts migration to add a column.

Correct:
```bash
bin/cake bake migration AddSlugToProducts slug:string
```

## Add Indexes in the Migration

Index columns used in filters, sorts, and joins when you create or alter the table — not as a forgotten follow-up weeks later.

```php
public function change(): void
{
    $table = $this->table('products');
    $table->addColumn('name', 'string', [
        'default' => null,
        'limit' => 255,
        'null' => false,
    ])
        ->addIndex(['name'])
        ->update();
}
```

Bake shortcut:

```bash
bin/cake bake migration AddNameIndexToProducts name:string:index
```

## Keep Migrations Focused

One concern per migration. Prefer separate migrations for schema vs seed data (`bin/cake bake seed` / seeding docs) so a failed data step does not leave schema half-applied without a clear recovery path.

## Apply and Rollback With the Plugin Commands

```bash
bin/cake migrations migrate
bin/cake migrations rollback
bin/cake migrations status
```

Use `bake migration_snapshot` / `bake migration_diff` when adopting migrations on an existing database, as documented in the Migrations book — do not hand-edit the schema lock/dump without understanding those commands.
