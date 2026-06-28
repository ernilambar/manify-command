<?php
/**
 * Manify_Command
 *
 * @package Manify_Command
 */

namespace Nilambar\Manify_Command;

use Nilambar\Manify_Command\Config\Config_Reader;
use Nilambar\Manify_Command\Generator\Command_Reflector;
use Nilambar\Manify_Command\Generator\Markdown_Renderer;
use WP_CLI;
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

		if ( ! $dry_run && ! is_dir( $destination ) ) {
			if ( ! mkdir( $destination, 0755, true ) ) {
				WP_CLI::error( "Could not create destination directory '{$destination}'." );
			}
		}

		$commands = ( new Config_Reader() )->read( getcwd() );

		if ( empty( $commands ) ) {
			WP_CLI::error( 'No WP-CLI commands found in composer.json. Please ensure your composer.json contains WP-CLI command configuration in the "extra.wp-cli-commands" section.' );
		}

		$reflector       = new Command_Reflector();
		$renderer        = new Markdown_Renderer();
		$generated_files = 0;

		foreach ( $commands as $command_config ) {
			if ( $this->generate_doc_for_command( $command_config, $destination, $dry_run, $reflector, $renderer ) ) {
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
	 * Generate documentation for a single WP-CLI command.
	 *
	 * @since 1.0.0
	 *
	 * @param array             $command_config Command configuration.
	 * @param string            $destination    Destination directory for markdown files.
	 * @param bool              $dry_run        Whether to print paths without writing.
	 * @param Command_Reflector $reflector      Reflector instance.
	 * @param Markdown_Renderer $renderer       Renderer instance.
	 * @return bool Whether documentation was generated successfully.
	 */
	private function generate_doc_for_command( array $command_config, string $destination, bool $dry_run, Command_Reflector $reflector, Markdown_Renderer $renderer ): bool {
		$command_slug  = $command_config['command_name'];
		$command_class = $command_config['class'] ?? null;
		$command_file  = $command_config['file'] ?? null;
		$single_method = $command_config['method'] ?? null;
		$plugin_dir    = $command_config['plugin_dir'];

		if ( ! $command_class ) {
			WP_CLI::warning( "No class specified for command '{$command_slug}'" );
			return false;
		}

		$file_path = $command_file ? $plugin_dir . '/' . $command_file : null;
		$methods   = $reflector->get_documented_methods( $command_class, $file_path, $single_method );

		if ( null === $methods ) {
			WP_CLI::warning( "Class not found: {$command_class}" );
			return false;
		}

		$markdown_content = $renderer->render( $command_slug, null !== $single_method, $methods );

		if ( '' === $markdown_content ) {
			WP_CLI::warning( "No documented methods on {$command_class}; skipping." );
			return false;
		}

		$filename    = preg_replace( '/[^a-z0-9_-]+/i', '-', $command_slug );
		$output_file = rtrim( $destination, '/' ) . "/{$filename}.md";

		if ( $dry_run ) {
			WP_CLI::line( "Would generate: {$output_file}" );
			return true;
		}

		if ( file_put_contents( $output_file, $markdown_content ) ) {
			WP_CLI::line( "Generated: {$output_file}" );
			return true;
		}

		WP_CLI::warning( "Could not write file: {$output_file}" );
		return false;
	}
}
