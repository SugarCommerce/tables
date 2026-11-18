<?php
/**
 * The bars down a list's lit rows.
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Seen;

use SugarCommerce\Plugin\Assets;
use SugarCommerce\Tables\Request;
use SugarCommerce\Tables\Utils\Runtime;

defined( 'ABSPATH' ) || exit;

/**
 * The stylesheet, and the few rules each table's states add to it.
 *
 * Loaded on the screen of a table that lights rows and nowhere else: admin
 * CSS on every screen is how a library becomes something people remove.
 *
 * @since 1.0.0
 */
final class Bars {

	/**
	 * The stylesheet's handle, named for this copy of the library.
	 *
	 * @return string
	 */
	public static function handle(): string {
		return Runtime::handle( 'highlights' );
	}

	/**
	 * Whether the admin hooks are on.
	 *
	 * @var bool
	 */
	private static bool $listening = false;

	/**
	 * Load the bars and mark the screen, on the lists that light rows.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function listen(): void {
		if ( self::$listening ) {
			return;
		}

		self::$listening = true;

		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_filter( 'admin_body_class', [ self::class, 'body_class' ] );
	}

	/**
	 * The rules that colour a table's bars.
	 *
	 * The stylesheet draws a bar on every lit row; what colour, whether the
	 * row is tinted, and how a high-contrast mode tells it apart are each
	 * state's own, so they are set here as custom properties on the state's
	 * class. A state an add-on adds is drawn the same way, and ships nothing.
	 *
	 * The tint is worked out here as a colour of its own -- the bar's, a
	 * tenth of it on white -- rather than left to `color-mix()`, so a browser
	 * without that has the same tint and not none.
	 *
	 * @param string $table_id The table's id.
	 *
	 * @return string CSS.
	 * @since 1.0.0
	 */
	public static function css( string $table_id ): string {
		$css = '';

		foreach ( Highlights::states( $table_id ) as $state => $highlight ) {
			if ( '' === $highlight['color'] ) {
				continue;
			}

			$rules = '--sugarcommerce-row-bar:' . $highlight['color'];

			if ( $highlight['tint'] ) {
				$rules .= ';--sugarcommerce-row-tint:' . self::tint( $highlight['color'] );
			}

			$rules .= ';--sugarcommerce-row-forced-style:' . ( 'highlight' === $highlight['forced'] ? 'solid' : $highlight['forced'] );
			$rules .= ';--sugarcommerce-row-forced-color:' . ( 'highlight' === $highlight['forced'] ? 'Highlight' : 'CanvasText' );

			$css .= '.wp-list-table tbody tr.' . Highlights::class_name( $table_id, $state ) . '{' . $rules . '}';
		}

		return $css;
	}

	/**
	 * Load the bars on a table's screen, if it lights rows.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function enqueue(): void {
		$table_id = self::current();

		if ( '' === $table_id ) {
			return;
		}

		Assets::enqueue_style( self::handle(), __FILE__, 'css/highlights.css' );

		$css = self::css( $table_id );

		if ( '' !== $css ) {
			wp_add_inline_style( self::handle(), $css );
		}
	}

	/**
	 * Mark a screen whose table lights rows, for the stylesheet.
	 *
	 * @param string $classes Body classes.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function body_class( $classes ): string {
		return '' !== self::current() ? $classes . ' sugarcommerce-table-highlights' : (string) $classes;
	}

	/**
	 * The table whose screen this is, if it lights rows.
	 *
	 * @return string Its id, or empty.
	 */
	private static function current(): string {
		$page = Request::key( 'page' );

		foreach ( Seen::lists() as $table_id => $list ) {
			if ( '' !== $page && $list['menu_slug'] === $page && [] !== Highlights::states( (string) $table_id ) ) {
				return (string) $table_id;
			}
		}

		return '';
	}

	/**
	 * A tenth of a colour on white.
	 *
	 * @param string $hex `#rrggbb` or `#rgb`.
	 *
	 * @return string `#rrggbb`.
	 */
	private static function tint( string $hex ): string {
		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$mixed = '#';

		foreach ( str_split( $hex, 2 ) as $channel ) {
			$mixed .= sprintf( '%02x', (int) round( hexdec( $channel ) * 0.1 + 255 * 0.9 ) );
		}

		return $mixed;
	}

	/**
	 * Forget that the admin hooks are on. For tests.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function reset(): void {
		self::$listening = false;
	}
}
