<?php
/**
 * Stylesheet tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use PHPUnit\Framework\TestCase;

/**
 * CSS has no errors, so a stylesheet is where a mistake lives longest.
 *
 * This library styles core's own list table rather than drawing one, so most
 * of what could go wrong is fighting core rather than failing on its own.
 */
final class StylesheetTest extends TestCase {

	/**
	 * The stylesheet.
	 *
	 * @return string
	 */
	private function css(): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/admin-tables.css' );
	}

	/**
	 * Every brace is closed.
	 */
	public function test_the_braces_balance(): void {
		$css = $this->css();

		$this->assertSame( substr_count( $css, '{' ), substr_count( $css, '}' ) );
	}

	/**
	 * No comment sits between a comma and an opening brace.
	 *
	 * That merges two rules: the selectors before it keep matching but take
	 * the next rule's declarations.
	 */
	public function test_no_comment_sits_inside_a_selector_list(): void {
		$merged = [];

		preg_match_all( '/(?:^|\})([^{}]*)\{/', $this->css(), $rules );

		foreach ( $rules[1] as $selector ) {
			$before = explode( '/*', $selector )[0];

			if ( str_contains( $selector, '/*' ) && str_contains( $before, ',' ) ) {
				$merged[] = trim( explode( "\n", trim( $before ) )[0] );
			}
		}

		$this->assertSame( [], $merged, implode( ', ', $merged ) );
	}

	/**
	 * Row actions are core's: no stylesheet here selects them.
	 *
	 * Core keeps a row's line of actions under its title at all times, off
	 * to the left until the row is pointed at or focused, so a row is as
	 * tall hovered as not. Taking that line away while hidden made every
	 * row grow when the pointer crossed it; hiding it with `visibility`
	 * kept the keyboard from ever reaching an Edit link. A primary column
	 * too narrow for its actions is fixed by the column widths instead.
	 */
	public function test_row_actions_are_left_to_core(): void {
		$selecting = [];

		foreach ( (array) glob( dirname( __DIR__ ) . '/assets/css/*.css' ) as $file ) {
			$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( (string) $file ) );

			preg_match_all( '/([^{}]+)\{/', $css, $rules );

			foreach ( $rules[1] as $selector ) {
				if ( str_contains( $selector, 'row-actions' ) ) {
					$selecting[] = basename( (string) $file ) . ': ' . trim( preg_replace( '/\s+/', ' ', $selector ) );
				}
			}
		}

		$this->assertNotEmpty( glob( dirname( __DIR__ ) . '/assets/css/*.css' ) );
		$this->assertSame( [], $selecting, implode( "\n", $selecting ) );
	}

	/**
	 * A row's title is drawn as a post's is: core's rule names only its own
	 * Title column, and a primary column here is named after its field.
	 * Without it a row was a few pixels shorter than one on Posts.
	 */
	public function test_a_title_is_drawn_as_core_draws_a_post_s(): void {
		$this->assertMatchesRegularExpression(
			'/\n\.wp-list-table \.column-primary > strong \{\s*display: block;\s*margin-bottom: \.2em;\s*font-size: 14px;\s*\}/',
			$this->css()
		);
	}

	/**
	 * On a phone the filter bar's selects are a line wide, but not the
	 * one a search filter keeps behind its combobox for a screen reader:
	 * at full width it hung past the screen and the page scrolled sideways.
	 */
	public function test_a_hidden_select_is_not_made_a_line_wide(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/admin-tables.css' );

		$this->assertStringContainsString( '.sugarcommerce-table-filters select:not(.screen-reader-text) {', $css );
		$this->assertDoesNotMatchRegularExpression( '/\.sugarcommerce-table-filters select\s*\{/', $css );
		$this->assertMatchesRegularExpression( '/\.field-kit__combobox select\.screen-reader-text \{\s*left: 0;\s*min-width: 0;\s*padding: 0;/', $css );
	}
}
