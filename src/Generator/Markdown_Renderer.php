<?php
/**
 * Markdown_Renderer
 *
 * @package Manify_Command
 */

namespace Nilambar\Manify_Command\Generator;

use WP_CLI\DocParser;

/**
 * Markdown_Renderer Class.
 *
 * @since 1.0.0
 */
class Markdown_Renderer {

	/**
	 * Renders markdown documentation for a command's methods.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $command_slug       Command slug (e.g. "myplugin run").
	 * @param bool    $single_method_mode Whether the command uses a single-method callable.
	 * @param array[] $methods            Method data from Command_Reflector.
	 * @return string Rendered markdown content.
	 */
	public function render( string $command_slug, bool $single_method_mode, array $methods ): string {
		$content = '';

		foreach ( $methods as $method ) {
			if ( $single_method_mode || $method['is_invoke'] ) {
				$content .= "# wp {$command_slug}\n\n";
			} else {
				$content .= "# wp {$command_slug} {$method['subcommand']}\n\n";
			}

			$parser   = new DocParser( $method['doc_comment'] );
			$content .= $parser->get_shortdesc() . "\n\n";

			foreach ( $this->split_into_sections( $parser->get_longdesc() ) as $name => $body ) {
				$fenced   = in_array( $name, [ 'OPTIONS', 'EXAMPLES' ], true );
				$content .= $this->render_section(
					$name,
					'EXAMPLES' === $name ? $this->dedent( $body ) : $body,
					$fenced
				);
			}

			$content .= "\n";
		}

		return $content;
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
	 * @param string $name    Section heading (e.g. OPTIONS).
	 * @param string $content Section body.
	 * @param bool   $fenced  Whether to wrap the body in a code fence.
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
