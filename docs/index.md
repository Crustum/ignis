# CakePHP Ignis Plugin

- [Introduction](#introduction)
- [Quickstart](#quickstart)
    - [Set Up Your Agents](#set-up-your-agents)
    - [Keeping Ignis Resources Updated](#keeping-ignis-resources-updated)
    - [Browser Logs on the Website](#browser-logs-on-the-website)
- [MCP Server](#mcp-server)
    - [Available MCP Tools](#available-mcp-tools)
    - [Manually Registering the MCP Server](#manually-registering-the-mcp-server)
- [AI Guidelines](#ai-guidelines)
    - [Available AI Guidelines](#available-ai-guidelines)
    - [Versioned Guidelines](#versioned-guidelines)
    - [Path-Scoped Guidelines](#path-scoped-guidelines)
    - [Adding Custom AI Guidelines](#adding-custom-ai-guidelines)
    - [Overriding Ignis AI Guidelines](#overriding-ignis-ai-guidelines)
    - [Plugin and Third-Party Package AI Guidelines](#plugin-and-third-party-package-ai-guidelines)
- [Agent Skills](#agent-skills)
    - [Available Skills](#available-skills)
    - [Custom Skills](#custom-skills)
    - [Overriding Skills](#overriding-skills)
    - [Third-Party Package Skills](#third-party-package-skills)
- [Skill Packs](#skill-packs)
    - [Pack Layout](#pack-layout)
    - [Major-Version Gating](#major-version-gating)
    - [First-Party vs Third-Party Targets](#first-party-vs-third-party-targets)
- [Guidelines vs. Skills](#guidelines-vs-skills)
- [Documentation](#documentation)
- [Extending Ignis](#extending-ignis)
    - [Adding Support for Other IDEs / AI Agents](#adding-support-for-other-ides-ai-agents)
        - [Writing the Agent](#writing-the-agent)
        - [Registering the Agent](#registering-the-agent)

<a name="introduction"></a>
## Introduction

CakePHP Ignis accelerates AI-assisted development by providing the essential guidelines and agent skills that help AI agents write high-quality CakePHP applications that adhere to CakePHP and Crustum best practices.

Package: `crustum/ignis` (`Crustum\Ignis`).

Ignis ships:

- Composable **AI guidelines** (Twig templates) for CakePHP, PHP, Pest, MCP, and ecosystem packages
- On-demand **agent skills** discovered from Ignis, installed plugins, and skill packs
- An **MCP server** so agents can inspect the application (schema, logs, routes, config, tinker)
- **Path-scoped rules** extracted from `@scoped` guideline blocks into `.ai/rules/ignis`

<a name="quickstart"></a>
## Quickstart

### Installing the Plugin

Install via Composer (`crustum/mcp` and `crustum/plugin-manifest` are hard dependencies):

```bash
composer require crustum/ignis --dev
```

Load **PluginManifest**, **Mcp**, and **Ignis**. Ignis does not auto-load Mcp; MCP tools need the Mcp plugin loaded. PluginManifest is required for `manifest install`.

```bash
bin/cake plugin load Crustum/PluginManifest
bin/cake plugin load Crustum/Mcp
bin/cake plugin load Crustum/Ignis
```

> [!NOTE]
> Register the plugins in `config/plugins.php` (or `Application::bootstrap()`). Mcp must be loaded before `bin/cake ignis mcp` / agent tool calls will work.

> [!TIP]
> **After the plugins are loaded**, install assets with PluginManifest. Ignis declares `Crustum/Mcp` as a required manifest dependency — use `--with-dependencies` so Mcp config/bootstrap are installed too:

```bash
bin/cake manifest install --plugin Crustum/Ignis --with-dependencies
```

That publishes Ignis's `config/ignis.php` and bootstrap append, and installs Mcp's declared assets (`config/mcp.php` + bootstrap load). Use `--all-deps` to skip optional-dependency prompts, or `--no-dependencies` if Mcp assets are already installed.

Alternatively, you can load the plugins in your `Application.php`:

```php
// In src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin('Crustum/PluginManifest');
    $this->addPlugin('Crustum/Mcp');
    $this->addPlugin('Crustum/Ignis');
}
```

Ignis discovers and installs agent guidelines, skills, and MCP client config with:

```bash
bin/cake ignis install
```

The `ignis install` command generates the relevant agent guideline and skill files for the coding agents you select during installation, and writes MCP client configuration.

Once Ignis has been installed, you're ready to start coding with Cursor, Claude Code, or your AI agent of choice.

> [!NOTE]
> Feel free to add the generated MCP configuration file (`.mcp.json` / `.cursor/mcp.json`), guideline files (`CLAUDE.md`, `AGENTS.md`, `junie/`, etc.), and the Ignis configuration file to your application's `.gitignore`, as these files are automatically regenerated when running `ignis install` and `ignis update`.

Configuration lives in `config/ignis.php` (enabled flag, browser log watcher, rules, conventions, MCP, GitHub skills, tinker, enforce-tests, and related keys), published by `manifest install` from the plugin's `config/ignis.php`.

When `debug` is on and `Ignis.browser_logs_watcher` is enabled (default), Ignis injects a small script that posts console output to `POST /_ignis/browser-logs`. That endpoint is public by design. The host must allow it through **both** CSRF and authentication — see [Browser Logs on the Website](#browser-logs-on-the-website).

<a name="set-up-your-agents"></a>
### Set Up Your Agents

```text tab=Cursor
1. Open the command palette (`Cmd+Shift+P` or `Ctrl+Shift+P`)
2. Press `enter` on "/open MCP Settings"
3. Turn the toggle on for `cake-ignis` (or the server name written by install)
```

```text tab=Claude Code
Claude Code support is typically enabled automatically. If you find it isn't, open a shell in the project's directory and run:

claude mcp add -s local -t stdio cake-ignis php bin/cake.php ignis mcp
```

```text tab=Codex
Codex support is typically enabled automatically. If you find it isn't, open a shell in the project's directory and run:

codex mcp add cake-ignis -- php "bin/cake.php" "ignis" "mcp"
```

```text tab=Gemini CLI
Gemini CLI support is typically enabled automatically. If you find it isn't, open a shell in the project's directory and run:

gemini mcp add -s project -t stdio cake-ignis php bin/cake.php ignis mcp
```

```text tab=GitHub Copilot (VS Code)
1. Open the command palette (`Cmd+Shift+P` or `Ctrl+Shift+P`)
2. Press `enter` on "MCP: List Servers"
3. Arrow to `cake-ignis` and press `enter`
4. Choose "Start server"
```

```text tab=Junie
1. Press `shift` twice to open the command palette
2. Search "MCP Settings" and press `enter`
3. Check the box next to `cake-ignis`
4. Click "Apply" at the bottom right
```

<a name="keeping-ignis-resources-updated"></a>
### Keeping Ignis Resources Updated

Periodically update local Ignis resources (AI guidelines and skills) so they reflect the packages you have installed:

```bash
bin/cake ignis update
```

You may automate this in Composer `post-update-cmd` scripts:

```json
{
  "scripts": {
    "post-update-cmd": [
      "@php bin/cake.php ignis update"
    ]
  }
}
```

By default, `ignis update` also scans for newly installed packages and prompts to publish their guidelines and skills (nothing is added without confirmation). Use `--no-discover` to refresh only resources already published in the application. `--discover` remains accepted and is the default behavior:

```bash
bin/cake ignis update
bin/cake ignis update --no-discover
```

<a name="browser-logs-on-the-website"></a>
### Browser Logs on the Website

When Cake `debug` is true (or `Ignis.force_enable` is set) and `Ignis.browser_logs_watcher` is enabled, Ignis injects a script that posts browser console output to:

```text
POST /_ignis/browser-logs
```

That URL maps to plugin `Crustum/Ignis`, controller `BrowserLogs`, action `store`. The ingest endpoint is intentionally unauthenticated. Configure the host pieces below.

When the host has already loaded the Authentication component on this controller, Ignis calls `addUnauthenticatedActions(['store'])` (no component load; do not use `Plugin::isLoaded('Authentication')` — hosts often use the package via CakeDC Users without registering Authentication as a Cake plugin). CakeDC Auth RBAC still needs a host `bypassAuth` rule.

#### 1. Skip CSRF for the ingest endpoint

In `Application::middleware()`, call `BrowserWatcher::shouldSkipCsrf()` from your `CsrfProtectionMiddleware` skip callback (keep any existing skips):

```php
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\ServerRequest;
use Crustum\Ignis\Support\BrowserWatcher;

$csrf = new CsrfProtectionMiddleware();
$csrf->skipCheckCallback(function (ServerRequest $request) {
    if (BrowserWatcher::shouldSkipCsrf($request)) {
        return true;
    }

    // …existing host skip logic…
    return false;
});
```

Without this, posts fail with `InvalidCsrfTokenException`.

#### 2. Allow unauthenticated access (CakeDC Auth RBAC)

When using CakeDC Auth request authorization / permissions, add a global rule (RBAC is separate from Authentication):

```php
[
    'prefix' => false,
    'plugin' => 'Crustum/Ignis',
    'controller' => 'BrowserLogs',
    'action' => 'store',
    'bypassAuth' => true,
],
```

Or allow an administrator role to access the Ignis plugin:

```php
[
    'role' => 'admin',
    'prefix' => false,
    'plugin' => 'Crustum/Ignis',
    'controller' => '*',
    'action' => '*',
],
```

> [!WARNING]
> Leave browser logs enabled only in local/debug. The endpoint accepts unauthenticated POSTs by design; do not expose it on production without additional controls.

<a name="mcp-server"></a>
## MCP Server

Ignis provides an MCP (Model Context Protocol) server that exposes tools for AI agents to interact with your CakePHP application. These tools give agents the ability to inspect structure, query the database, execute code in context, and more.

Start the server:

```bash
bin/cake ignis mcp
```

This delegates to the Crustum MCP stack (`bin/cake mcp start cake-ignis`).

<a name="available-mcp-tools"></a>
### Available MCP Tools

| Name | Notes |
|------|-------|
| Application Info | PHP & CakePHP versions, database engine, ecosystem packages with versions |
| Browser Logs | Read logs and errors from the browser watcher |
| Database Connections | Inspect available database connections, including the default |
| Database Query | Execute a query against the database |
| Database Schema | Read the database schema |
| Get Absolute URL | Convert relative path URIs to absolute URLs |
| Current Time | Host clock in `App.defaultTimezone` as one formatted timestamp; prefer over shell date commands |
| Last Error | Read the last error from the application's log files |
| Read Log Entries | Read the last N log entries |
| List / Get Routes | Inspect application routes |
| Config Read | Read Configure / config values |
| Tinker / Execute | Run PHP in the application context |
| List Commands | Discover Cake console commands |

Agents should prefer package docs, skills, and guidelines shipped with Ignis and your plugins when they need library-specific guidance.

<a name="manually-registering-the-mcp-server"></a>
### Manually Registering the MCP Server

Sometimes you may need to manually register the Ignis MCP server with your editor. Use:

| Field | Value |
|-------|-------|
| **Command** | `php` |
| **Args** | `bin/cake.php ignis mcp` |

JSON example:

```json
{
    "mcpServers": {
        "cake-ignis": {
            "command": "php",
            "args": ["bin/cake.php", "ignis", "mcp"]
        }
    }
}
```

<a name="ai-guidelines"></a>
## AI Guidelines

AI guidelines are composable instruction files loaded upfront to give AI agents essential context about CakePHP and ecosystem packages. They contain core conventions, best practices, and framework-specific patterns.

Templates use **Twig** (`.twig`), compiled as plain text via `twig/twig` for guideline composition.

<a name="available-ai-guidelines"></a>
### Available AI Guidelines

Ignis includes bundled guidelines under `.ai/` for foundation, Ignis core, CakePHP, PHP, Pest, PHPUnit, and MCP. The `core` guidelines provide generic advice for a package that applies across versions. Major- and minor-specific trees add version deltas.

| Area | Notes |
|------|-------|
| Foundation & Ignis | Always composed |
| CakePHP | `core` + major folders (e.g. `.ai/cakephp/5/`) |
| PHP | `core` + cumulative minor folders (e.g. `8.2`…runtime) |
| Pest / PHPUnit | Testing guidelines |
| MCP | MCP development guidelines |
| Plugin / pack guidelines | From `resources/ignis/guidelines` and skill packs when packages are installed |

> [!NOTE]
> To keep guidelines up to date, see [Keeping Ignis Resources Updated](#keeping-ignis-resources-updated).

<a name="versioned-guidelines"></a>
### Versioned Guidelines

Ignis composes guidelines by installed package **major** (and for PHP, cumulative **minor**):

| Layer | Behavior |
|-------|----------|
| CakePHP | `.ai/cakephp/{major}/` — e.g. `5`, `6`. Minor deltas use Twig `assist.uses('cakephp/cakephp', '>=5.4')` inside the major file |
| PHP | `.ai/php/core.twig` plus cumulative `.ai/php/{X.Y}/` up to the running PHP version (empty files skipped) |
| Packs | Shared base under the target, then `{major}/` when the installed major matches — see [Skill Packs](#skill-packs) |

Guideline keys for versioned pack content use the `{pkg}/v{major}` pattern (same idea as `cakephp/v5`).

Foundation dependency listing respects `Ignis.guidelines.dependencies`: `direct` (default) or `all`.

<a name="path-scoped-guidelines"></a>
### Path-Scoped Guidelines

Guidelines may wrap path-specific rules in `@scoped([...])` … `@endscoped` blocks. Extraction into managed rules under `.ai/rules/ignis` is **opt-in** via `Ignis.rules.scoped_guidelines` / `IGNIS_RULES_SCOPED_GUIDELINES` (default `false`). When enabled (and `Ignis.rules.enabled` is true), install/update extracts those blocks via `RuleComposer`. Agents then load those rules only when editing matching paths. When disabled, `@scoped` content stays inline in the composed guidelines.

| Surface | Path | Role |
|---------|------|------|
| Skills | `resources/ignis/skills/*/SKILL.twig` or pack `…/skills/` | On-demand orientation |
| Guidelines + `@scoped` | `resources/ignis/guidelines/**/*.twig` or pack `…/guidelines/` | Path-activated managed rules |

> [!IMPORTANT]
> Put `@scoped` blocks in **guidelines**, not skills. Skill writers do not promote skill `@scoped` into managed rules.

Prefer tight globs (controllers, config, specific namespaces). Avoid broad `@scoped(['src/**'])` for one-off bootstrap notes — fold those into the skill or unscoped guideline body.

<a name="adding-custom-ai-guidelines"></a>
### Adding Custom AI Guidelines

To augment Ignis with your own guidelines, add `.twig` or `.md` files under your application's `.ai/guidelines/*` directory. These files are included when you run `ignis install` / `ignis update`.

<a name="overriding-ignis-ai-guidelines"></a>
### Overriding Ignis AI Guidelines

Override built-in guidelines by creating custom files with matching paths. When a custom guideline matches an existing Ignis path, Ignis uses your version instead of the bundled one.

<a name="plugin-and-third-party-package-ai-guidelines"></a>
### Plugin and Third-Party Package AI Guidelines

If you maintain a CakePHP plugin and want Ignis to include guidelines for it, add:

```text
resources/ignis/guidelines/core.twig
```

When users install Ignis and have your package installed, Ignis discovers and composes those guidelines (first-party Crustum/CakePHP targets auto-compose; other vendors may be offered at install time).

Guidelines should briefly describe what the package does, required structure/conventions, and how to use main features (with short snippets). Prefer `@ignissnippet` blocks for code samples. Keep them concise and actionable.

Example shape:

```twig
## Package Name

This package provides [brief description].

### Features

- Feature 1: [short description].
- Feature 2: [short description].

@scoped(['src/Model/Table/**/*.php'])
- Prefer existing table methods over ad hoc queries in this package.
@endscoped
```

<a name="agent-skills"></a>
## Agent Skills

[Agent Skills](https://agentskills.io/home) are lightweight, targeted knowledge modules that agents activate on demand when working in a specific domain. Unlike guidelines (loaded upfront), skills load only when relevant — reducing context bloat.

When you run `ignis install` and select skills, Ignis installs skills based on packages detected in the project (Composer + Inspector). For example, if the project includes `crustum/broadcasting`, the `broadcasting-development` skill is available.

Skills use `SKILL.twig` (Twig) with YAML frontmatter (`name`, `description`). Optional `references/` files hold deeper material. Prefer a strong **description** for when to apply; do not rely on a body section titled “When to Apply” as the only discovery signal.

<a name="available-skills"></a>
### Available Skills

Skills ship from three places:

1. **Bundled Ignis** — e.g. CakePHP best practices, infer-conventions, MCP/AI companion skills under `.ai/`
2. **Installed plugins** — `plugins/{Plugin}/resources/ignis/skills/{skill-name}/SKILL.twig`
3. **Skill packs** — `crustum/cakephp-skills` under `resources/ignis/pack/…` — see [Skill Packs](#skill-packs)

| Example skill | Source |
|---------------|--------|
| `broadcasting-development` | `crustum/broadcasting` |
| `blazecast-development` | `crustum/blazecast` |
| `notification-development` | `crustum/notification` |
| `tessera-development` | `crustum/tessera` |
| `scheduling-development` | `crustum/cakephp-scheduling` |
| `search-filter-development` | `cakedc/search-filter` |
| `plum-search-development` | `skie/cakephp-search` |
| `mcp-development` | `crustum/mcp` |
| `queue-development` | skill pack (`cakephp/queue`) |
| `migrations-development` | skill pack (`cakephp/migrations`) |
| `users-development` | skill pack (`cakedc/users`) |
| `cakephp-api-development` | skill pack (`cakedc/cakephp-api`) |

> [!NOTE]
> For several auth/API packs, **scoped guidelines are primary** and skills stay intentionally thin.

<a name="custom-skills"></a>
### Custom Skills

Create custom skills under:

```text
.ai/skills/{skill-name}/SKILL.twig
```

They are installed alongside Ignis skills on `ignis update`.

<a name="overriding-skills"></a>
### Overriding Skills

Override a built-in skill by creating a custom skill with the same name. Your version wins on install/update.

<a name="third-party-package-skills"></a>
### Third-Party Package Skills

If you maintain a CakePHP plugin and would like Ignis to include skills for it, add a `resources/ignis/skills/{skill-name}/SKILL.twig` file to your package. When users of your package run `bin/cake ignis install`, Ignis installs your skills based on user preference and whether the package is present.

Ignis skills follow the [Agent Skills](https://agentskills.io/what-are-skills) format: a folder containing a `SKILL.twig` file with YAML frontmatter and Markdown instructions. Required frontmatter is `name` and `description`. You may include scripts, templates, and `references/` materials.

Skills should outline required file structure or conventions and explain how to create or use main features (with example commands or code snippets). Keep them concise, actionable, and focused on best practices:

```markdown
---
name: package-name-development
description: Build and work with PackageName features, including components and workflows.
---

# Package Name Development

## Features

- Feature 1: [clear & short description].
- Feature 2: [clear & short description]. Example usage:

$result = PackageName::featureTwo($param1, $param2);
```

Put path-specific standing rules in guidelines with `@scoped`, not in the skill body.

<a name="skill-packs"></a>
## Skill Packs

Skill packs let ecosystem packages share Ignis guidelines and skills from a dedicated Composer package instead of (or in addition to) each plugin shipping its own tree.

`crustum/cakephp-skills` is an Ignis dependency that holds pack assets for many CakePHP and CakeDC targets. Discovery still gates on whether the **target** package is installed in the host (via Inspector / `installed.json`, including transitive packages).

Plugins may also ship assets directly under `resources/ignis/skills` and `resources/ignis/guidelines`. Packs cover targets that benefit from a shared, versioned monorepo of Ignis assets.

<a name="pack-layout"></a>
### Pack Layout

```text
resources/ignis/pack/{vendor}/{package}/
  guidelines/…          # optional shared base (all majors)
  skills/…              # optional shared base
  {major}/              # e.g. 16, 4, 2 — gated by installed major
    guidelines/…
    skills/…
```

`SkillPackDiscovery` treats the composer folder as the target root (`cakedc/users`), not a major-suffixed path (`cakedc/users/16`). Numeric directories are majors, not separate composer targets.

<a name="major-version-gating"></a>
### Major-Version Gating

Major folders let several package majors sit side by side (for example Users 15 and 16, or Authentication 3 and 4). A flat `guidelines/core.twig` alone cannot express that.

Behavior:

1. Emit shared **base** `skills/` and `guidelines/` (if present)
2. If the installed package major matches a `{major}/` directory, emit that tree as well
3. Skills: later entry wins on name (major overrides base)
4. Guidelines keys: `{pkg}/…` for base, `{pkg}/v{major}/…` for versioned

Examples currently versioned in `cakephp-skills`:

| Target | Major folder |
|--------|--------------|
| `cakephp/queue` | `2` |
| `cakephp/migrations` | `4` |
| `cakephp/authentication` | `4` |
| `cakephp/authorization` | `3` |
| `cakedc/users` | `16` |
| `cakedc/auth` | `10` |
| `cakedc/cakephp-api` | `10` |

The CakeDC API pack documents the Service → Action → Renderer (+ Transformer) stack for API routes.

<a name="first-party-vs-third-party-targets"></a>
### First-Party vs Third-Party Targets

| Kind | Examples | Install behavior |
|------|----------|------------------|
| First-party for Ignis | `cakephp/*`, allowlisted `crustum/*` | Auto-compose when installed |
| Third-party | e.g. `cakedc/*` | Offered / selected at install for guidelines |

Host monorepos may need a Composer path repository to `plugins/cakephp-skills` until the pack is on Packagist; Ignis itself requires the pack package.

<a name="guidelines-vs-skills"></a>
## Guidelines vs. Skills

Ignis provides two ways to give AI agents context:

**Guidelines** load upfront and supply foundational CakePHP / package conventions.

**Skills** activate on demand for focused domains (Broadcasting, Tessera, Queue, and others).

| Aspect | Guidelines | Skills |
|--------|------------|--------|
| **Loaded** | Upfront, always present | On-demand, when relevant |
| **Scope** | Broad, foundational | Focused, task-specific |
| **Purpose** | Core conventions & best practices | Detailed implementation patterns |
| **Path rules** | `@scoped` → `.ai/rules/ignis` | Orientation only — do not put managed scopes here |
| **Templates** | `.twig` / `.md` | `SKILL.twig` (+ optional `references/`) |

<a name="documentation"></a>
## Documentation

Agents receive package and framework guidance from Ignis through:

1. **Composed AI guidelines** — conventions and patterns loaded into agent guideline files
2. **Agent skills** — on-demand modules for focused domains
3. **MCP tools** — live application context (schema, routes, config, logs, tinker)
4. **Published docs** — the [CakePHP Book](https://book.cakephp.org) and each plugin's own `docs/`

Guidelines and skills should point agents at those sources when implementing features.

<a name="extending-ignis"></a>
## Extending Ignis

Ignis works with popular IDEs and AI agents out of the box. If your coding tool is not supported yet, you can create your own agent and integrate it with Ignis.

<a name="adding-support-for-other-ides-ai-agents"></a>
### Adding Support for Other IDEs / AI Agents

To add support for a new IDE or AI agent, create a class that extends `Crustum\Ignis\Install\Agents\Agent` and implement one or more of the following contracts depending on what you need:

- `Crustum\Ignis\Contracts\SupportsGuidelines` — Adds support for AI guidelines.
- `Crustum\Ignis\Contracts\SupportsMcp` — Adds support for MCP.
- `Crustum\Ignis\Contracts\SupportsSkills` — Adds support for Agent Skills.

<a name="writing-the-agent"></a>
#### Writing the Agent

```php
<?php
declare(strict_types=1);

namespace App\Ignis;

use Crustum\Ignis\Contracts\SupportsGuidelines;
use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Contracts\SupportsSkills;
use Crustum\Ignis\Install\Agents\Agent;

class CustomAgent extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    // Your implementation...
}
```

For an example implementation, see `src/Install/Agents/ClaudeCode.php` in the Ignis plugin.

<a name="registering-the-agent"></a>
#### Registering the Agent

Register your custom agent on the shared `IgnisManager` so it appears when running `bin/cake ignis install`. Call `registerAgent` from a plugin `services()` method that loads **after** `Crustum/Ignis` (plugin order in `config/plugins.php`):

```php
use Cake\Core\ContainerInterface;
use Crustum\Ignis\IgnisManager;
use App\Ignis\CustomAgent;

public function services(ContainerInterface $container): void
{
    $container->get(IgnisManager::class)
        ->registerAgent('customagent', CustomAgent::class);
}
```

Once registered, your agent will be available for selection when running `bin/cake ignis install`.
