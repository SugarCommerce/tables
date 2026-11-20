<?php
/**
 * The three things kept for a person on a list.
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Seen;

defined( 'ABSPATH' ) || exit;

/**
 * A person's marker on a list, as user meta named for the list.
 *
 * Three things, and on a list that is kept apart in sets (test and live)
 * once for each set:
 *
 * - `seen`: the newest row there was when they last opened the list;
 * - `shown`: what `seen` was when the list they are looking at was drawn,
 *   so a row redrawn after a quick edit is lit the same as its neighbours;
 * - `own`: rows they made by hand since, which are not news to them.
 *
 * Named from the table's id -- `{table_id}_seen_{set}` -- so two plugins'
 * lists never share one, and from `meta_prefix` where a table says.
 *
 * @since 1.0.0
 */
final class Markers {

	/**
	 * How many rows made by hand are remembered.
	 *
	 * Nobody makes that many between two looks at the list, and a list that
	 * could only grow would be one.
	 */
	public const int OWN = 50;

	/**
	 * The name one of the three things is kept under.
	 *
	 * `shop_orders_seen_test`, `shop_customers_seen`.
	 *
	 * @param string $table_id The table's id.
	 * @param string $what     `seen`, `shown` or `own`.
	 * @param string $scope    The set; empty for a list that has one.
	 *
	 * @return string A user meta key.
	 * @since 1.0.0
	 */
	public static function key( string $table_id, string $what, string $scope = '' ): string {
		$prefix = (string) ( Seen::get( $table_id )['meta_prefix'] ?? $table_id . '_' );

		return $prefix . $what . ( '' !== $scope ? '_' . $scope : '' );
	}

	/**
	 * The newest row of a list somebody has seen.
	 *
	 * Nought for somebody who has never opened it, and nothing is written
	 * for them: the window is what keeps nought from meaning "everything".
	 *
	 * @param string $table_id The table's id.
	 * @param int    $user_id  Whose.
	 * @param string $scope    The set; empty for a list that has one.
	 *
	 * @return int A row's id; 0 when they have never looked.
	 * @since 1.0.0
	 */
	public static function seen( string $table_id, int $user_id, string $scope = '' ): int {
		return (int) get_user_meta( $user_id, self::key( $table_id, 'seen', $scope ), true );
	}

	/**
	 * What `seen` was when the list in front of somebody was drawn.
	 *
	 * @param string $table_id The table's id.
	 * @param int    $user_id  Whose.
	 * @param string $scope    The set; empty for a list that has one.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function shown( string $table_id, int $user_id, string $scope = '' ): int {
		return (int) get_user_meta( $user_id, self::key( $table_id, 'shown', $scope ), true );
	}

	/**
	 * Remember the marker a page was drawn from.
	 *
	 * Written only when it moves: most visits find nothing new.
	 *
	 * @param string $table_id The table's id.
	 * @param int    $user_id  Whose.
	 * @param string $scope    The set.
	 * @param int    $from     The marker.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function show( string $table_id, int $user_id, string $scope, int $from ): void {
		if ( self::shown( $table_id, $user_id, $scope ) !== $from ) {
			update_user_meta( $user_id, self::key( $table_id, 'shown', $scope ), $from );
		}
	}

	/**
	 * Move somebody's marker to a row, forwards only.
	 *
	 * Forwards only is what makes two tabs and the back button safe: an older
	 * page finishing after a newer one cannot bring back rows that were
	 * already seen.
	 *
	 * @param string $table_id The table's id.
	 * @param int    $user_id  Whose.
	 * @param string $scope    The set; empty for a list that has one.
	 * @param int    $row      The newest row they have now seen.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function mark( string $table_id, int $user_id, string $scope, int $row ): void {
		if ( $user_id < 1 || ! Seen::has_marker( $table_id ) || $row <= self::seen( $table_id, $user_id, $scope ) ) {
			return;
		}

		update_user_meta( $user_id, self::key( $table_id, 'seen', $scope ), $row );

		// Their own rows up to here are behind the marker now, and the list
		// of them is only for what is in front of it.
		$own = array_values( array_filter( self::own( $table_id, $user_id, $scope ), static fn( int $id ): bool => $id > $row ) );

		if ( [] === $own ) {
			delete_user_meta( $user_id, self::key( $table_id, 'own', $scope ) );
		} else {
			update_user_meta( $user_id, self::key( $table_id, 'own', $scope ), $own );
		}
	}

	/**
	 * The rows of a list somebody made by hand since they last looked.
	 *
	 * @param string $table_id The table's id.
	 * @param int    $user_id  Whose.
	 * @param string $scope    The set; empty for a list that has one.
	 *
	 * @return array<int, int> Ids.
	 * @since 1.0.0
	 */
	public static function own( string $table_id, int $user_id, string $scope = '' ): array {
		$own = get_user_meta( $user_id, self::key( $table_id, 'own', $scope ), true );

		return array_values( array_filter( array_map( 'intval', is_array( $own ) ? $own : [] ) ) );
	}

	/**
	 * Remember that somebody made a row themselves.
	 *
	 * @param string $table_id The table's id.
	 * @param int    $user_id  Who made it.
	 * @param string $scope    The set; empty for a list that has one.
	 * @param int    $row      What they made.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function add_own( string $table_id, int $user_id, string $scope, int $row ): void {
		if ( $user_id < 1 || $row < 1 || ! Seen::has_marker( $table_id ) ) {
			return;
		}

		$own = array_slice( array_unique( array_merge( self::own( $table_id, $user_id, $scope ), [ $row ] ) ), -self::OWN );

		update_user_meta( $user_id, self::key( $table_id, 'own', $scope ), array_values( $own ) );
	}
}
