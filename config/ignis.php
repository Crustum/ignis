<?php
declare(strict_types=1);

/**
 * Ignis Plugin Configuration
 *
 * Host applications copy settings into `config/ignis.php` and load via
 * `Configure::load('ignis')`. Keys are read as `Configure::read('Ignis.*')`.
 * Environment variables use the `IGNIS_*` prefix.
 */
return [
    'Ignis' => [
        /**
         * Master switch for Ignis. When false, Ignis routes are not registered
         * and the browser log watcher does not run.
         */
        'enabled' => env('IGNIS_ENABLED', true),

        /**
         * Force Ignis to run when Cake `debug` is false (MCP + browser watcher).
         * Default false. Only set true for trusted non-debug hosts that still need Ignis.
         */
        'force_enable' => env('IGNIS_FORCE_ENABLE', false),

        /**
         * Capture browser console messages so agents get frontend error context.
         * Requires Ignis to be running (`enabled` + debug or `force_enable`), disabled by default.
         */
        'browser_logs_watcher' => env('IGNIS_BROWSER_LOGS_WATCHER', false),

        /**
         * Browser console levels to capture. Trim to `['error']` when
         * warning/info/debug noise is unhelpful. Comma-separated via env.
         */
        'browser_log_levels' => array_values(array_filter(array_map(
            static fn(string $level): string => strtolower(trim($level)),
            explode(',', (string)env('IGNIS_BROWSER_LOG_LEVELS', 'error,warning,info,debug')),
        ))),

        /**
         * MCP server tool and resource registration filters, plus subprocess
         * timeout (seconds) for `execute-tool` calls.
         *
         * - `tools.exclude` / `tools.include` — tool name allow/deny lists
         * - `resources.exclude` / `resources.include` — resource allow/deny lists
         * - `tool_timeout` — default timeout when the tool call omits one
         */
        'mcp' => [
            'tools' => [
                'exclude' => [],
                'include' => [],
            ],
            'resources' => [
                'exclude' => [],
                'include' => [],
            ],
            'tool_timeout' => env('IGNIS_MCP_TOOL_TIMEOUT', 180),
        ],

        /**
         * Optional GitHub token for remote skill discovery and downloads.
         */
        'github' => [
            'token' => env('IGNIS_GITHUB_TOKEN'),
        ],

        /**
         * Optional hosted audit endpoint for remote skill risk checks.
         * When unset, `ignis add-skill` refuses to install unless `--skip-audit`
         * is passed.
         */
        'hosted' => [
            'audit_url' => env('IGNIS_HOSTED_AUDIT_URL'),
        ],

        /**
         * Enable the MCP `tinker` tool. Off by default — only enable in trusted
         * local/dev environments.
         */
        'tinker_tool_enabled' => env('IGNIS_TINKER_TOOL_ENABLED', false),

        /**
         * Project rules in `.ai/rules/`. When enabled, agents may record durable
         * decisions there. Enabling `scoped_guidelines` also moves path-scoped
         * `@scoped` blocks into `.ai/rules/ignis/`; it stays opt-in.
         */
        'rules' => [
            'enabled' => env('IGNIS_RULES_ENABLED', true),
            'scoped_guidelines' => env('IGNIS_RULES_SCOPED_GUIDELINES', false),
        ],

        /**
         * Skills Ignis ships to every project unconditionally (such as
         * `infer-conventions`). List a skill name here to opt out of installing it.
         */
        'skills' => [
            'exclude' => [],
        ],

        /**
         * Guideline compose options.
         *
         * - `dependencies` — which packages appear in Foundational Context:
         *   `direct` = composer.json / package.json requires only;
         *   `all` = full Inspector package set (previous default behavior).
         */
        'guidelines' => [
            'dependencies' => env('IGNIS_GUIDELINES_DEPENDENCIES', 'direct'),
        ],

        /**
         * When true, installed guidelines include enforce-tests guidance.
         * Null leaves the install command free to ask interactively.
         */
        'enforce_tests' => env('IGNIS_ENFORCE_TESTS'),

        /**
         * Hide Ignis ASCII / banner output in console commands (useful in CI
         * and Pest runs).
         */
        'suppress_display' => env('IGNIS_SUPPRESS_DISPLAY', false),

        /**
         * Optional absolute paths for executables Ignis invokes. When set, these
         * override PATH discovery. Leave null/empty to use defaults.
         */
        'executable_paths' => [
            'php' => env('IGNIS_PHP_EXECUTABLE_PATH'),
            'composer' => env('IGNIS_COMPOSER_EXECUTABLE_PATH'),
            'npm' => env('IGNIS_NPM_EXECUTABLE_PATH'),
            'vendor_bin' => env('IGNIS_VENDOR_BIN_EXECUTABLE_PATH'),
            'current_directory' => env('IGNIS_CURRENT_DIRECTORY_EXECUTABLE_PATH'),
        ],
    ],
];
