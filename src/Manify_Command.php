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

		// Create destination directory if it doesn't exist.
		if ( ! is_dir( $destination ) ) {
			if ( ! mkdir( $destination, 0755, true ) ) {
				WP_CLI::error( "Could not create destination directory '{$destination}'." );
			}
		}

		// Get WP-CLI commands configuration from current directory.
		$commands = $this->get_wp_cli_commands();

		if ( empty( $commands ) ) {
			WP_CLI::error( 'No WP-CLI commands found in composer.json. Please ensure your composer.json contains WP-CLI command configuration in the "extra" section.' );
			return;
		}

		$generated_files = 0;

		foreach ( $commands as $command_config ) {
			$result = $this->generate_doc_for_command( $command_config, $destination );
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

		// Check for commands in extra.commands (array format).
		if ( isset( $extra['commands'] ) && is_array( $extra['commands'] ) ) {
			foreach ( $extra['commands'] as $command_name ) {
				$commands[] = [
					'command_name' => $command_name,
					'plugin_dir'   => $run_path,
					'plugin_slug'  => basename( $run_path ),
				];
			}
		}

		// Check for commands in extra.wp-cli-commands (object format).
		if ( isset( $extra['wp-cli-commands'] ) && is_array( $extra['wp-cli-commands'] ) ) {
			foreach ( $extra['wp-cli-commands'] as $command_name => $command_config ) {
				$command_config['command_name'] = $command_name;
				$command_config['plugin_dir']   = $run_path;
				$command_config['plugin_slug']  = basename( $run_path );

				$commands[] = $command_config;
			}
		}

		if ( empty( $commands ) ) {
			WP_CLI::error( 'No WP-CLI commands found in composer.json. Please add commands to "extra.commands" or "extra.wp-cli-commands" section.' );
		}

		return $commands;
	}

	/**
	 * Validate composer.json file and return parsed data.
	 *
	 * @since 1.0.0
	 *
	 * @return array|false Composer data array or false on validation failure.
	 */
	private function validate_composer_file() {
		// Use the current working directory where the command is run from.
		$run_path = getcwd();

		// Check for composer.json in the current directory.
		$composer_file = rtrim( $run_path, '/' ) . '/composer.json';

		if ( ! file_exists( $composer_file ) ) {
			WP_CLI::error( "No composer.json file found in: {$run_path}" );
			return false;
		}

		$composer_data = json_decode( file_get_contents( $composer_file ), true );

		if ( null === $composer_data ) {
			WP_CLI::error( 'Invalid JSON in composer.json file.' );
			return false;
		}

		// Check for WP-CLI commands in extra section.
		if ( ! isset( $composer_data['extra'] ) ) {
			WP_CLI::error( 'No "extra" section found in composer.json. WP-CLI commands should be defined in the "extra" section.' );
			return false;
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
	 * @return bool Whether documentation was generated successfully.
	 */
	private function generate_doc_for_command( $command_config, $destination ) {
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
		$methods          = $reflection_class->getMethods( ReflectionMethod::IS_PUBLIC );

		foreach ( $methods as $method ) {
			$doc_comment = $method->getDocComment();

			if ( empty( $doc_comment ) ) {
				continue;
			}

			$method_name = $method->getName();

			// Extract subcommand from @subcommand annotation or convert method name.
			$subcommand = $this->get_subcommand_name( $doc_comment, $method_name );

			$markdown_content .= "# wp {$command_slug} {$subcommand}\n";
			$markdown_content .= "\n";

			$parser = new DocParser( $doc_comment );

			$short_description = $parser->get_shortdesc();

			$markdown_content .= "{$short_description}\n\n";

			$options  = '';
			$examples = '';

			$exploded = explode( '## EXAMPLES', $parser->get_longdesc() );

			if ( 1 === count( $exploded ) ) {
				$options = reset( $exploded );
			} elseif ( 2 === count( $exploded ) ) {
				$options  = $exploded[0];
				$examples = $exploded[1];
			}

			if ( ! empty( $options ) ) {
				$markdown_content .= '## OPTIONS';
				$options           = str_replace( '## OPTIONS', '', $options );
				$markdown_content .= $this->get_wrapped( trim( $options ) );
			}

			if ( ! empty( $examples ) ) {
				$markdown_content .= '## EXAMPLES';
				$markdown_content .= $this->get_wrapped( $this->get_clean_examples( $examples ) );
			}

			$markdown_content .= "\n";
		}

		// Write markdown file.
		$output_file = rtrim( $destination, '/' ) . '/' . $command_slug . '.md';
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

		// Use method name as-is when no @subcommand tag is present.
		return $method_name;
	}

	/**
	 * Returns cleaned examples.
	 *
	 * @since 1.0.0
	 *
	 * @param string $examples The content.
	 * @return string Cleaned up examples.
	 */
	private function get_clean_examples( $examples ) {
		$temp_examples = explode( "\n", $examples );
		$temp_examples = array_map( 'trim', $temp_examples );
		return implode( "\n", $temp_examples );
	}

	/**
	 * Returns content wrapped with Markdown code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The content.
	 * @return string Wrapped content.
	 */
	private function get_wrapped( $content ) {
		return "\n```\n" . trim( $content ) . "\n```\n\n";
	}
}
