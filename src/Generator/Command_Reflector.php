<?php
/**
 * Command_Reflector
 *
 * @package Manify_Command
 */

namespace Nilambar\Manify_Command\Generator;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Command_Reflector Class.
 *
 * @since 1.0.0
 */
class Command_Reflector {

	/**
	 * Returns documented public methods for a command class.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $class_name    Fully qualified class name.
	 * @param string|null $file_path     Absolute path to the file defining the class.
	 * @param string|null $single_method If set, return only this method.
	 * @return array[]|null Array of method data, or null if the class cannot be loaded.
	 */
	public function get_documented_methods( string $class_name, ?string $file_path, ?string $single_method ): ?array {
		if ( $file_path && file_exists( $file_path ) ) {
			include_once $file_path;
		}

		try {
			$reflection_class = new ReflectionClass( $class_name );
		} catch ( ReflectionException $e ) {
			return null;
		}

		$methods = [];

		foreach ( $reflection_class->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
			$name      = $method->getName();
			$is_invoke = '__invoke' === $name;

			if ( $single_method && $name !== $single_method ) {
				continue;
			}

			if ( ! $is_invoke && str_starts_with( $name, '__' ) ) {
				continue;
			}

			$doc_comment = $method->getDocComment();

			if ( empty( $doc_comment ) ) {
				continue;
			}

			$methods[] = [
				'name'        => $name,
				'doc_comment' => $doc_comment,
				'is_invoke'   => $is_invoke,
				'subcommand'  => $this->get_subcommand_name( $doc_comment, $name ),
			];
		}

		return $methods;
	}

	/**
	 * Resolves the WP-CLI subcommand name for a method.
	 *
	 * @since 1.0.0
	 *
	 * @param string $doc_comment The PHPDoc comment.
	 * @param string $method_name The method name.
	 * @return string The subcommand name.
	 */
	private function get_subcommand_name( string $doc_comment, string $method_name ): string {
		if ( preg_match( '/@subcommand\s+(\S+)/', $doc_comment, $matches ) ) {
			return $matches[1];
		}

		return str_replace( '_', '-', $method_name );
	}
}
