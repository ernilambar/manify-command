<?php
/**
 * Command
 *
 * @package Manify_Command
 */

use Nilambar\Manify_Command\Manify_Command;

if ( ! class_exists( 'WP_CLI', false ) ) {
	return;
}

$wpcli_manify_autoload = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpcli_manify_autoload ) ) {
	require_once $wpcli_manify_autoload;
}

WP_CLI::add_command( 'manify', Manify_Command::class );
