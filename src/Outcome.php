<?php
/**
 * Action Outcome
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables;

/**
 * The rows an action could not change, and why, while it runs.
 *
 * A bulk action is answered by hooks that return nothing -- which is how an
 * add-on adds one -- so there is no return value to say "two of these did
 * not move". A handler says it here instead, row by row, in its own words:
 *
 *     Outcome::fail( $id, sprintf( 'Order #%d is refunded', $id ) );
 *
 * and the notice the list shows afterwards says so: "2 orders marked as
 * complete. 1 could not be changed: Order #98 is refunded." Emptied before
 * each action, so one cannot report another's rows.
 *
 * @since 1.0.0
 */
final class Outcome {

	/**
	 * Why each row failed, by id. An empty reason is a failure with none.
	 *
	 * @var array<int, string>
	 */
	private static array $failed = [];

	/**
	 * Say a row could not be changed.
	 *
	 * @param int    $id     The row.
	 * @param string $reason Why, as a phrase naming it: "Order #98 is
	 *                       refunded". Not escaped. Empty to have the row
	 *                       named for you.
	 *
	 * @return void
	 */
	public static function fail( int $id, string $reason = '' ): void {
		self::$failed[ $id ] = $reason;
	}

	/**
	 * The rows that failed, and why.
	 *
	 * @return array<int, string>
	 */
	public static function failed(): array {
		return self::$failed;
	}

	/**
	 * Start again, before an action runs.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$failed = [];
	}
}
