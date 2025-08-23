# Manify Command

A WP-CLI command to generate markdown documentation from WP-CLI commands.

## Installation

```bash
wp package install nilambar/manify-command:@stable
```

## Usage

Generate documentation for all WP-CLI commands:

```bash
wp manify generate
```

Generate documentation with custom destination:

```bash
wp manify generate --destination=./generated-docs
```

## Options

- `--destination=<destination>` - Destination folder for generated markdown files (default: `docs/`)

## Examples

```bash
# Generate docs for all plugins
wp manify generate

# Generate docs with custom destination
wp manify generate --destination=./generated-docs
```

## Requirements

- WordPress
- WP-CLI
- PHP 7.4+

## Configuration

To make your WP-CLI commands discoverable by this package, add the following to your plugin's `composer.json`:

```json
{
    "extra": {
        "wp-cli-commands": {
            "my-command": {
                "class": "MyPlugin\\My_Command",
                "file": "src/My_Command.php"
            }
        }
    }
}
```
