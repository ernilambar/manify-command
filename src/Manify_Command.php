<?php
/**
 * Manify_Command
 *
 * @package Manify_Command
 */

namespace Nilambar\Manify_Command;

use WP_CLI;
use WP_CLI\Utils;

/**
 * Manify_Command Class.
 *
 * @since 1.0.0
 */
class Manify_Command {

	/**
	 * Test command.
	 *
	 * ## OPTIONS
	 *
	 * No options available.
	 *
	 * ## EXAMPLES
	 *
	 *     # Test the command.
	 *     $ wp manify test
	 *     Success: Test.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args       List of the positional arguments.
	 * @param array $assoc_args List of the associative arguments.
	 *
	 * @when after_wp_load
	 * @subcommand test
	 */
	public function test_( $args, $assoc_args = [] ) {
		WP_CLI::success( 'Test.' );
	}
}
