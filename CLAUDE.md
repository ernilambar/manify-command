# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A WP-CLI package (`wp manify`) that reads `composer.json` from a target plugin directory and generates markdown documentation from PHPDoc on public methods of WP-CLI command classes.

## Commands

```bash
# Lint (PHPCS + linter)
composer lint

# Fix coding standards
composer format

# PHPStan static analysis
composer phpstan

# Behat integration tests (requires prepare-tests first)
composer prepare-tests
composer behat

# Rerun only failed Behat scenarios
composer behat-rerun
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

## Quality gate

Every task must end with:

- `composer lint` — 0 errors, 0 warnings (run `composer format` to auto-fix first)

## Coding standards

- WordPress Coding Standards + WP-CLI CS via PHPCS (`phpcs.xml.dist`)
- Short array syntax enforced (`[]` not `array()`)
- `use` imports must be alphabetically sorted, no grouped use, no unused imports
- Global namespace must be prefixed with `wpcli_manify` or `Nilambar\Manify_Command`
- PHP 7.4 minimum
