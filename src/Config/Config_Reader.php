<?php
/**
 * Config_Reader
 *
 * @package Manify_Command
 */

namespace Nilambar\Manify_Command\Config;

use WP_CLI;

/**
 * Config_Reader Class.
 *
 * @since 1.0.0
 */
class Config_Reader {

	/**
	 * Reads composer.json at the given path and returns normalized command configs.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Project directory containing composer.json.
	 * @return array Array of command configurations.
	 */
	public function read( string $path ): array {
		$composer_file = rtrim( $path, '/' ) . '/composer.json';

		if ( ! file_exists( $composer_file ) ) {
			WP_CLI::error( "No composer.json file found in: {$path}" );
		}

		$composer_data = json_decode( file_get_contents( $composer_file ), true );

		if ( null === $composer_data ) {
			WP_CLI::error( 'Invalid JSON in composer.json file.' );
		}

		if ( ! isset( $composer_data['extra'] ) ) {
			WP_CLI::error( 'No "extra" section found in composer.json. WP-CLI commands should be defined in the "extra" section.' );
		}

		$commands = [];

		if ( isset( $composer_data['extra']['wp-cli-commands'] ) && is_array( $composer_data['extra']['wp-cli-commands'] ) ) {
			foreach ( $composer_data['extra']['wp-cli-commands'] as $command_name => $command_config ) {
				$command_config['command_name'] = $command_name;
				$command_config['plugin_dir']   = $path;
				$command_config['plugin_slug']  = basename( $path );

				$commands[] = $command_config;
			}
		}

		return $commands;
	}
}
