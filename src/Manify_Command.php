<?php
/**
 * Manify_Command
 *
 * @package Manify_Command
 */

namespace Nilambar\Manify_Command;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use WP_CLI;
use WP_CLI\DocParser;
use function WP_CLI\Utils\get_flag_value;

/**
 * Manify_Command Class.
 *
 * @since 1.0.0
 */
class Manify_Command {

	/**
	 * Generate markdown documentation from WP-CLI commands.
	 *
	 * ## OPTIONS
	 *
	 * [--destination=<destination>]
	 * : Destination folder for the generated markdown files.
	 * ---
	 * default: docs/
	 * ---
	 *
	 * [--dry-run]
	 * : Print file paths that would be generated without writing files.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate docs for current project's WP-CLI commands.
	 *     $ wp manify generate
	 *     Success: Documentation generated successfully.
	 *
	 *     # Generate docs with custom destination.
	 *     $ wp manify generate --destination=./generated-docs
	 *     Success: Documentation generated successfully.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args       List of the positional arguments.
	 * @param array $assoc_args List of the associative arguments.
	 *
	 * @when before_wp_load
	 */
	public function generate( $args, $assoc_args = [] ) {
		$destination = $assoc_args['destination'] ?? 'docs/';
		$dry_run     = (bool) get_flag_value( $assoc_args, 'dry-run', false );

		// Create destination directory if it doesn't exist.
		if ( ! $dry_run && ! is_dir( $destination ) ) {
			if ( ! mkdir( $destination, 0755, true ) ) {
				WP_CLI::error( "Could not create destination directory '{$destination}'." );
			}
		}

		// Get WP-CLI commands configuration from current directory.
		$commands = $this->get_wp_cli_commands();

		if ( empty( $commands ) ) {
			WP_CLI::error( 'No WP-CLI commands found in composer.json. Please ensure your composer.json contains WP-CLI command configuration in the "extra.wp-cli-commands" section.' );
		}

		$generated_files = 0;

		foreach ( $commands as $command_config ) {
			$result = $this->generate_doc_for_command( $command_config, $destination, $dry_run );
			if ( $result ) {
				++$generated_files;
			}
		}

		if ( $generated_files > 0 ) {
			WP_CLI::success( "Documentation generated successfully. {$generated_files} commands processed." );
		} else {
			WP_CLI::warning( 'No documentation was generated.' );
		}
	}

	/**
	 * Get WP-CLI commands from composer.json in current directory.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of command configurations.
	 */
	private function get_wp_cli_commands() {
		$composer_data = $this->validate_composer_file();

		if ( ! $composer_data ) {
			return [];
		}

		$commands = [];
		$extra    = $composer_data['extra'];

		// Use the current working directory where the command is run from.
		$run_path = getcwd();

		// Check for commands in extra.wp-cli-commands (object format).
		if ( isset( $extra['wp-cli-commands'] ) && is_array( $extra['wp-cli-commands'] ) ) {
			foreach ( $extra['wp-cli-commands'] as $command_name => $command_config ) {
				$command_config['command_name'] = $command_name;
				$command_config['plugin_dir']   = $run_path;
				$command_config['plugin_slug']  = basename( $run_path );

				$commands[] = $command_config;
			}
		}

		return $commands;
	}

	/**
	 * Validate composer.json file and return parsed data.
	 *
	 * @since 1.0.0
	 *
	 * @return array Composer data array.
	 */
	private function validate_composer_file() {
		// Use the current working directory where the command is run from.
		$run_path = getcwd();

		// Check for composer.json in the current directory.
		$composer_file = rtrim( $run_path, '/' ) . '/composer.json';

		if ( ! file_exists( $composer_file ) ) {
			WP_CLI::error( "No composer.json file found in: {$run_path}" );
		}

		$composer_data = json_decode( file_get_contents( $composer_file ), true );

		if ( null === $composer_data ) {
			WP_CLI::error( 'Invalid JSON in composer.json file.' );
		}

		// Check for WP-CLI commands in extra section.
		if ( ! isset( $composer_data['extra'] ) ) {
			WP_CLI::error( 'No "extra" section found in composer.json. WP-CLI commands should be defined in the "extra" section.' );
		}

		return $composer_data;
	}

	/**
	 * Generate documentation for a single WP-CLI command.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $command_config Command configuration.
	 * @param string $destination    Destination directory for markdown files.
	 * @param bool   $dry_run        Whether to print paths without writing.
	 * @return bool Whether documentation was generated successfully.
	 */
	private function generate_doc_for_command( $command_config, $destination, $dry_run = false ) {
		$command_slug = $command_config['command_name'];
		$plugin_dir   = $command_config['plugin_dir'];

		// Get class and file from command config.
		$command_class = $command_config['class'] ?? null;
		$command_file  = $command_config['file'] ?? null;

		if ( ! $command_class ) {
			WP_CLI::warning( "No class specified for command '{$command_slug}'" );
			return false;
		}

		// Include the command file if specified.
		if ( $command_file ) {
			$file_path = $plugin_dir . '/' . $command_file;
			if ( file_exists( $file_path ) ) {
				include_once $file_path;
			}
		}

		try {
			$reflection_class = new ReflectionClass( $command_class );
		} catch ( ReflectionException $e ) {
			WP_CLI::warning( "Class not found: {$command_class}" );
			return false;
		}

		$markdown_content = '';
		$single_method    = $command_config['method'] ?? null;
		$methods          = $reflection_class->getMethods( ReflectionMethod::IS_PUBLIC );

		foreach ( $methods as $method ) {
			$method_name = $method->getName();

			if ( $single_method && $method_name !== $single_method ) {
				continue;
			}

			$is_invoke = '__invoke' === $method_name;

			if ( ! $is_invoke && str_starts_with( $method_name, '__' ) ) {
				continue;
			}

			$doc_comment = $method->getDocComment();

			if ( empty( $doc_comment ) ) {
				continue;
			}

			if ( $single_method || $is_invoke ) {
				$markdown_content .= "# wp {$command_slug}\n";
			} else {
				// Extract subcommand from @subcommand annotation or convert method name.
				$subcommand        = $this->get_subcommand_name( $doc_comment, $method_name );
				$markdown_content .= "# wp {$command_slug} {$subcommand}\n";
			}
			$markdown_content .= "\n";

			$parser = new DocParser( $doc_comment );

			$short_description = $parser->get_shortdesc();

			$markdown_content .= "{$short_description}\n\n";

			$sections = $this->split_into_sections( $parser->get_longdesc() );

			foreach ( $sections as $section_name => $section_content ) {
				if ( 'EXAMPLES' === $section_name ) {
					$markdown_content .= $this->render_section( $section_name, $this->dedent( $section_content ), true );
				} else {
					$markdown_content .= $this->render_section( $section_name, $section_content );
				}
			}

			$markdown_content .= "\n";
		}

		if ( '' === $markdown_content ) {
			WP_CLI::warning( "No documented methods on {$command_class}; skipping." );
			return false;
		}

		// Write markdown file.
		$filename    = preg_replace( '/[^a-z0-9_-]+/i', '-', $command_slug );
		$output_file = rtrim( $destination, '/' ) . "/{$filename}.md";

		if ( $dry_run ) {
			WP_CLI::line( "Would generate: {$output_file}" );
			return true;
		}

		if ( file_put_contents( $output_file, $markdown_content ) ) {
			WP_CLI::line( "Generated: {$output_file}" );
			return true;
		} else {
			WP_CLI::warning( "Could not write file: {$output_file}" );
			return false;
		}
	}

	/**
	 * Get subcommand name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $doc_comment The PHPDoc comment.
	 * @param string $method_name The method name.
	 * @return string The subcommand name.
	 */
	private function get_subcommand_name( $doc_comment, $method_name ) {
		// Try to extract from @subcommand annotation.
		if ( preg_match( '/@subcommand\s+(\S+)/', $doc_comment, $matches ) ) {
			return $matches[1];
		}

		return str_replace( '_', '-', $method_name );
	}

	/**
	 * Splits a longdesc string into named sections keyed by ## HEADING.
	 *
	 * @since 1.0.0
	 *
	 * @param string $longdesc The long description from DocParser.
	 * @return array<string, string> Map of section name to content.
	 */
	private function split_into_sections( string $longdesc ): array {
		$sections = [];
		$current  = null;

		foreach ( explode( "\n", $longdesc ) as $line ) {
			if ( preg_match( '/^## ([A-Z][A-Z ]*)$/', trim( $line ), $m ) ) {
				$current              = trim( $m[1] );
				$sections[ $current ] = '';
			} elseif ( null !== $current ) {
				$sections[ $current ] .= $line . "\n";
			}
		}

		return $sections;
	}

	/**
	 * Renders a single documentation section with consistent spacing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name   Section heading (e.g. OPTIONS).
	 * @param string $content Section body.
	 * @param bool   $fenced Whether to wrap the body in a code fence.
	 * @return string Rendered section, or empty string if content is blank.
	 */
	private function render_section( string $name, string $content, bool $fenced = false ): string {
		$content = trim( $content );

		if ( '' === $content ) {
			return '';
		}

		$body = $fenced ? "```\n{$content}\n```" : $content;

		return "## {$name}\n\n{$body}\n\n";
	}

	/**
	 * Dedents a block of text by stripping the minimum leading whitespace.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to dedent.
	 * @return string Dedented text.
	 */
	private function dedent( string $text ): string {
		$lines   = explode( "\n", $text );
		$indents = [];

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			preg_match( '/^( *)/', $line, $m );
			$indents[] = strlen( $m[1] );
		}

		if ( empty( $indents ) ) {
			return $text;
		}

		$min = min( $indents );

		return implode(
			"\n",
			array_map(
				fn( $l ) => '' === trim( $l ) ? $l : substr( $l, $min ),
				$lines
			)
		);
	}
}
