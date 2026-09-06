# AGENTS.md

## Overview

WP-CLI package that generates markdown documentation from WP-CLI command definitions. PHP 8.2+ with WP-CLI v3.0+, using WordPress Coding Standards and Slevomat.

## Setup

```bash
composer install
```

## Commands

```bash
composer install        # Install dependencies
composer test           # Run full test suite (lint, phpcs, phpunit, behat)
composer lint           # Run linter + PHPCS
composer phpcs          # Run PHP_CodeSniffer
composer format         # Auto-fix code style with PHPCBF
composer phpunit        # Run PHPUnit unit tests
composer behat          # Run Behat feature tests
composer phpstan        # Run PHPStan static analysis
composer readme         # Regenerate README.md
```

## Architecture

**Entry point:** `command.php` — bootstraps autoload and registers `manify` with `WP_CLI::add_command`.

**Core logic:** `src/Manify_Command.php` — single class, `Nilambar\Manify_Command\Manify_Command`.

Flow inside `generate()`:
1. `get_wp_cli_commands()` → reads target project's `composer.json` from `getcwd()`, returns normalized command config array
2. `generate_doc_for_command()` → uses PHP `ReflectionClass` to iterate public methods, parses PHPDoc via `WP_CLI\DocParser`, writes one `.md` file per command slug

**Two composer.json config formats** the tool reads from target projects:

```json
// Simple array format (extra.commands)
"extra": { "commands": ["my-command"] }

// Object format (extra.wp-cli-commands) — supports class, file, method
"extra": {
    "wp-cli-commands": {
        "myplugin run": { "class": "...", "file": "...", "method": "run" }
    }
}
```

The `method` key (object format only) signals a single-method callable — heading is generated as `# wp {command_slug}` without appending the method name. Without `method`, all public methods with PHPDoc become subcommands: `# wp {command_slug} {subcommand}`.

## Conventions

- **Tabs for indentation** (4 spaces wide); spaces only for `.md`, `.json`, `.yml`, `.feature`, `.txt`.
- **Short array syntax** (`[]`) is required; long array syntax (`array()`) is forbidden.
- **Import rules**: Use statements must be alphabetically sorted, no grouped use declarations, no leading backslash, no unused imports, and no imports from the same namespace.
- **Global namespace**: Everything must be namespaced under `Nilambar\Manify_Command\` or prefixed with `wpcli_manify`.
- **Architecture**: Command logic in `src/Manify_Command.php`, config parsing in `src/Config/`, doc generation in `src/Generator/` (Command_Reflector, Markdown_Renderer).

## Quality Gate

Run this exact sequence before declaring a task complete. All commands must exit with code 0.

```bash
composer lint
composer phpcs
composer phpunit
composer behat
```
