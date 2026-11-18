<?php
/**
 * Flyout Link
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables;

use SugarCommerce\Flyouts\Registry;
use SugarCommerce\Jobs\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * A link that opens a row's panel, made by the flyout library's own class.
 *
 * This used to call the flyout library's global `get_flyout_link()`, and a
 * global function is the one thing a bundled copy cannot rely on. Strauss
 * prefixes a library's namespace -- `Shop\Vendor\SugarCommerce\Flyouts`
 * -- and leaves its functions alone, and those functions are guarded with
 * `function_exists()`: on a site with two plugins that bundle both libraries
 * the first plugin's copy defines the function, and it asks the first
 * plugin's registry for the second plugin's panel. On a site where the
 * function was never loaded at all, every Edit link and every linked title
 * drew nothing.
 *
 * A class name, by contrast, is rewritten with the rest of the namespace, so
 * the `Registry` below is always the copy bundled beside this one. It is a
 * soft dependency: without the flyout library there is no panel to open, and
 * the link is not drawn rather than drawn pointing nowhere.
 *
 * @since 1.0.0
 */
final class FlyoutLink {

	/**
	 * The link, or an empty string when there is no panel to open.
	 *
	 * @param string $flyout  The panel's full id: the flyout manager's prefix,
	 *                       an underscore, and the panel's own id.
	 * @param int    $id     The row the panel is to open on.
	 * @param string $text   What the link says.
	 * @param string $classes Classes for the link, beside the library's own.
	 *
	 * @return string Markup the flyout library escaped as it built it.
	 * @since 1.0.0
	 */
	public static function get( string $flyout, int $id, string $text, string $classes = '' ): string {
		if ( '' === $flyout || '' === $text || ! class_exists( Registry::class ) ) {
			return '';
		}

		$resolved = Registry::resolve( $flyout );

		if ( ! is_array( $resolved ) || ! isset( $resolved['manager'], $resolved['flyout_id'] ) ) {
			return '';
		}

		return (string) $resolved['manager']->link(
			(string) $resolved['flyout_id'],
			$text,
			[ 'id' => $id ],
			'' !== $classes ? [ 'class' => $classes ] : []
		);
	}

	/**
	 * A button that opens a panel with no row: the Add button, and the one
	 * in an empty list.
	 *
	 * @param string $flyout The panel's full id.
	 * @param array  $args   `text`, `class` and `icon`, as the flyout
	 *                       library's button takes them.
	 *
	 * @return string Markup the flyout library escaped as it built it.
	 * @since 1.0.0
	 */
	public static function button( string $flyout, array $args ): string {
		if ( '' === $flyout || '' === (string) ( $args['text'] ?? '' ) || ! class_exists( Registry::class ) ) {
			return '';
		}

		$resolved = Registry::resolve( $flyout );

		if ( ! is_array( $resolved ) || ! isset( $resolved['manager'], $resolved['flyout_id'] ) ) {
			return '';
		}

		return (string) $resolved['manager']->get_button(
			(string) $resolved['flyout_id'],
			[],
			array_intersect_key( $args, array_flip( [ 'text', 'class', 'icon' ] ) )
		);
	}

	/**
	 * A sync button from sugarcommerce/jobs, by the same reasoning: its class,
	 * which a bundled copy prefixes, and not its global function.
	 *
	 * @param string $id The sync's id.
	 *
	 * @return string Markup the sync library escaped as it built it.
	 * @since 1.0.0
	 */
	public static function sync_button( string $id ): string {
		if ( '' === $id || ! class_exists( Sync::class ) ) {
			return '';
		}

		return (string) Sync::get( $id )?->button();
	}
}
