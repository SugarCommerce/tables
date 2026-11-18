<?php
/**
 * The count of new rows beside a list's menu item.
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Seen;

use SugarCommerce\Tables\Request;

defined( 'ABSPATH' ) || exit;

/**
 * A number in a bubble that says there is something to look at, and on the
 * list the rows that are the something -- the way Comments does it.
 *
 * Turned on by a table's `seen.menu_count`. Hooked to the admin menu, so a
 * visitor's request, a webhook and a REST call run none of it and no query
 * is made for them.
 *
 * @since 1.0.0
 */
final class MenuCount {

	/**
	 * Put each count beside its list, and beside the menu the list is under.
	 *
	 * Core's own markup for the bubble on Comments, so it is drawn in
	 * whichever admin colour scheme somebody has chosen and collapses with
	 * the menu as core's does. Nothing at all at nought.
	 *
	 * On the list itself the count is nought. The menu is drawn before the
	 * list is, so the alternative is a bubble saying "3" on the very screen
	 * that is showing the three -- with the rows lit, the bubble has nothing
	 * left to say.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function badge(): void {
		global $menu, $submenu;

		$page = Request::key( 'page' );

		foreach ( Seen::lists() as $table_id => $list ) {
			if ( ! $list['on'] || false === $list['menu_count'] || '' === $list['menu_slug'] || $page === $list['menu_slug'] ) {
				continue;
			}

			$count = Seen::count( (string) $table_id );

			if ( $count < 1 ) {
				continue;
			}

			$bubble = ' ' . self::bubble( (string) $table_id, $count );
			$parent = $list['parent_slug'];

			// The list's own item: under its parent, or at the top level
			// when it has none.
			if ( '' !== $parent ) {
				foreach ( (array) ( $submenu[ $parent ] ?? [] ) as $index => $item ) {
					if ( $list['menu_slug'] === ( $item[2] ?? '' ) ) {
						// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- A count in a menu title is written into the menu, as core writes its own; there is no function for it.
						$submenu[ $parent ][ $index ][0] .= $bubble;
					}
				}
			}

			$top = '' !== $parent ? ( $list['menu_count']['parent'] ? $parent : '' ) : $list['menu_slug'];

			if ( '' === $top ) {
				continue;
			}

			foreach ( (array) $menu as $index => $item ) {
				if ( $top === ( $item[2] ?? '' ) ) {
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
					$menu[ $index ][0] .= $bubble;
				}
			}
		}
	}

	/**
	 * A count, as the bubble core draws beside Comments.
	 *
	 * "99+" past ninety-nine: the bubble is for "there are some", and a
	 * four-figure number in it pushes the menu's words out of the menu. What
	 * is read aloud is the real number, in the table's own words.
	 *
	 * @param string $table_id The table's id.
	 * @param int    $count    How many.
	 *
	 * @return string Markup; empty at nought.
	 * @since 1.0.0
	 */
	public static function bubble( string $table_id, int $count ): string {
		if ( $count < 1 ) {
			return '';
		}

		$number = number_format_i18n( $count );
		$text   = Seen::get( $table_id )['menu_count']['text'] ?? null;

		$said = is_callable( $text )
			? (string) call_user_func( $text, $count, $number )
			: sprintf(
				/* translators: %s: a number of rows. */
				_n( '%s new item', '%s new items', $count, 'sugarcommerce' ),
				$number
			);

		return sprintf(
			'<span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%2$s</span><span class="screen-reader-text">%3$s</span></span>',
			$count,
			esc_html( $count > 99 ? '99+' : $number ),
			esc_html( $said )
		);
	}
}
