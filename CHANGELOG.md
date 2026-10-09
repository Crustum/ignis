# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0]

### Added

- Plugin install-path support (`Install\InstallPath`, `ProjectRoot`): `ignis install` / `update` sync project `.ai` assets for the plugin path
- Clickable console file links (`Support\FileLink`)
- `Ignis.guidelines.exclude` config key to skip guideline keys shown in the install summary
- MCP configuration support for Antigravity and Pi agents (`.pi/mcp.json` default via `Ignis.agents.pi.mcp_config_path`)
- `ignis index-rules` command regenerating `.ai/rules/index.md` with conflict detection and skipped-file warnings, plus `RuleRepository::exists()/conflictedFiles()/unindexedFiles()`
- `NullSchemaDriver::getTables()` listing tables through the Cake schema collection with empty-list fallback
- `ClaudeCode::guidelinesPath()` keeps `CLAUDE.md` for existing projects unless explicitly configured
- OpenCode `.opencode` directory detection and container registration via event
- Third-party NPM package guidelines and skills (`PackageRegistry::composerNameFromSkillPackFolder()`, fluent `ThirdPartyPackage` discovery)
- `testing-best-practices` skill (assertions, endpoint tests, isolation, naming, performance, review, security, test-data) and `cakephp-best-practices` refresh (routing, validation, caching, collections, config, mail, migrations, query-builder)
- Skill parse-failure reporting (`Support\SkillParseFailures`, `ReportsSkillParseFailuresTrait`): broken/unclosed/missing frontmatter is reported during `install` / `update` / skill sync instead of failing silently
- Fenced-code safety for composed guidelines (`Support\Fences`, `MarkdownFormatter`): nested/tilde/indented fences and backend/frontend `api.twig` nesting fixtures
- `database-connections` lists per-connection engine types (explicit `engine` key wins, Cake core drivers keep canonical names, others derive from driver short name; default resolves via `ConnectionManager` aliases)
- DB schema tooling: check-constraint fallback, MySQL driver fixes, `ApplicationInfo` driver engine name
- Browser-logs cluster: route gating, channel log path, truncated fragments, watcher serialization
- `Rules` glob areas and `RuleRepository` skill preservation
- `record-rule` defaults to enabled with description pointing at the shared `.ai/rules` notes
- Slimmed always-loaded guidelines (`foundation.twig`, `php/8.4`–`8.5/core.twig`, `infer-conventions` checklist with `grep --` hints, corrected testing assertions guidance)

### Changed

- Agents are sorted alphabetically in `IgnisManager`
- Vendor guidelines that fail to render are skipped with bundled fallback (`Support\RenderFailures`)
- MCP config writers rewritten for robustness (`Install\Mcp\FileWriter`, `TomlFileWriter`); JSON5 writer rejects non-object configuration roots
- Agent detection paths and config keys refreshed (Antigravity, Claude Code, Junie)
- `UpdateCommand` reordered for silent MCP-only updates; package discovery prompt is skipped when run via composer script
- `DatabaseQuery` hardened: state-machine read-only parser, literal-safe prefixes, CTE case-insensitivity
- `GitHubSkillProvider` / `GitHubRepository`: UTF-8-safe rules, SSH remote validation, Blade and PHP files rejected from remote skills, `SKILL.md` preserved on rejected download
- `Config` handling rejects non-object roots; blank executable paths treated as unset; browser log channel registered from plugin
- `browser_logs_watcher` is disabled by default (`config/ignis.php`)
- Test suite migrated from Mockery to `jasonmccreary/double` (`VerifiesDoubles`)

### Fixed

- Read-only bypass in `DatabaseQuery` via state-machine parser
- Signaled-process crash when detecting test enforcement in `InstallCommand`
- Missing project rules directory no longer breaks the `ignis` guideline render

## Prerelease

Initial release of `crustum/ignis` (`Crustum\Ignis`).

CakePHP 5 plugin for AI-assisted development: guidelines, agent skills, MCP tooling, and install/update commands. See `docs/index.md`.

### Added

- `bin/cake ignis install` / `update` — discover and sync MCP, guidelines, and skills for installed packages
- Agent adapters (Cursor, Claude Code, Codex, Gemini CLI, Copilot, Junie, and related) with MCP registration helpers
- Composable AI guidelines (Twig text compile) for CakePHP, PHP, Pest, MCP, and ecosystem packages
- Versioned guidelines (package major / PHP minor trees) and path-scoped `@scoped` rules → `.ai/rules/ignis`
- Agent skills from Ignis core, installed plugins (`resources/ignis`), and multi-target skill packs
- Skill pack discovery (`SkillPackDiscovery`) via Composer `installed.json` (transitive packs; provider ≠ target)
- Major-version gating for pack skills/guidelines (`resources/ignis/pack/{vendor}/{package}/{major}/…`)
- MCP server (`cake-ignis`) tools for application inspection on `crustum/mcp`
- Package detection via `crustum/inspector`
- Commands: install, update, add-skill, list-skills, MCP helpers, and related console UX
- Config: `config/ignis.php`
- Public docs: `docs/index.md`, `docs/Versions.md`

### Dependencies

- `crustum/mcp`, `crustum/inspector`, CakePHP 5, Symfony Finder/Filesystem/Yaml, Twig
- Optional companion packs (e.g. `crustum/cakephp-skills`) are discoverable when installed
