---
name: cakephp-best-practices
description: "Apply this skill whenever writing, reviewing, or refactoring CakePHP PHP code. This includes creating or modifying controllers, tables, entities, migrations, validators, middleware, commands, mailers, HTTP client usage, and ORM queries. Triggers for N+1 and query performance issues, caching strategies, security patterns, validation, error handling, route definitions, views, and architectural decisions. Also use for CakePHP code reviews and refactoring existing CakePHP code to follow best practices. Covers any task involving CakePHP backend PHP code patterns."
license: MIT
metadata:
  author: cakephp
---

# CakePHP Best Practices

Best practices for CakePHP, organized as an index of rule files. Each rule file teaches what to do and why. For exact API syntax, verify with the [CakePHP 5 Book](https://book.cakephp.org/5/en/contents.html).

## Consistency First

Before applying any rule, check what the application already does. CakePHP offers multiple valid approaches, and the best choice is the one the codebase already uses, even if another pattern would be theoretically better. Inconsistency is worse than a suboptimal pattern.

Check sibling files, related controllers, tables, entities, or tests for established patterns. If one exists, follow it. Don't introduce a second way. These rules are defaults for when no pattern exists yet, not overrides.

## How to Apply

1. Check the changed files, nearby code, project configuration, and relevant tests for established patterns. Deviate only for a correctness or security defect, and call the deviation out.
2. Map every affected concern to the rule index below. Read each mapped rule file before editing. Skip unrelated rule files.
3. Make the smallest coherent change. Keep the application's architecture and naming instead of introducing a second pattern for the same job.
4. Verify version-sensitive CakePHP APIs against the [CakePHP 5 Book](https://book.cakephp.org/5/en/contents.html) for the installed major version, or inspect the installed `cakephp/cakephp` package when docs are unavailable.
5. Run the narrowest relevant PHPUnit / `bin/cake test` filter first, then the project's formatting and static-analysis checks when the change warrants them.
6. Re-read the diff against every mapped rule before finishing.

## Rule Index

Cross-cutting changes often need more than one rule file.

| Concern | Read |
| --- | --- |
| Query count, contain, select limits, indexes, large datasets, buffered results | [`rules/db-performance.md`](rules/db-performance.md) |
| Subqueries, aggregates, matching, CASE expressions, complex finds | [`rules/query-builder.md`](rules/query-builder.md) |
| Tables, entities, associations, behaviors, finders, mass assignment | [`rules/orm-tables-entities.md`](rules/orm-tables-entities.md) |
| Mass assignment, CSRF, FormProtection, HTTPS, SQL injection, XSS, secrets | [`rules/security.md`](rules/security.md) |
| Table validators, rules checker, patchEntity, named validation sets | [`rules/validation.md`](rules/validation.md) |
| Thin actions, `getRequest()`/`getResponse()`, `set` arrays, pagination, JSON views, action return types | [`rules/controllers.md`](rules/controllers.md) |
| Named routes, scopes, prefixes, resources, middleware routing | [`rules/routing.md`](rules/routing.md) |
| Schema changes, columns, foreign keys, indexes, `change()` vs `up()`/`down()` | [`rules/migrations.md`](rules/migrations.md) |
| Cache lifetime, invalidation, groups, `remember()`, query result caching | [`rules/caching.md`](rules/caching.md) |
| Outbound requests, timeouts, scoped clients, status handling, fakes | [`rules/http-client.md`](rules/http-client.md) |
| Exceptions, ErrorTrap/ExceptionTrap, reporting, rendering, `skipLog` | [`rules/error-handling.md`](rules/error-handling.md) |
| Domain events, Model callbacks, `afterSaveCommit` listeners | [`rules/events.md`](rules/events.md) |
| Mailer profiles, templates, `MailerAwareTrait`, reusable emails | [`rules/mail.md`](rules/mail.md) |
| `extract`, `combine`, map/filter, result set transforms | [`rules/collections.md`](rules/collections.md) |
| Templates, elements, cells, helpers, escaping, view blocks | [`rules/views.md`](rules/views.md) |
| Environment values, `Configure`, `Security.salt`, `debug` flags | [`rules/config.md`](rules/config.md) |
| Tests: coverage, fixtures, fakes, and assertions | the `testing-best-practices` skill |
| Naming conventions, `Text`/`Inflector` helpers, file boundaries, PHP style | [`rules/style.md`](rules/style.md) |
| Services, DI container, plugins, transactions, layer boundaries | [`rules/architecture.md`](rules/architecture.md) |

## Decision Rules

- Prefer framework features and existing application abstractions over new helpers or dependencies.
- Avoid speculative abstractions. Extract code when it creates a clear domain boundary, removes meaningful duplication, or makes behavior independently testable.
- Keep database access out of templates and prevent hidden N+1 queries across controllers, cells, jobs, and serialization.
