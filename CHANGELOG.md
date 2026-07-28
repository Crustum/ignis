# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0]

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
