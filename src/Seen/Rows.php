<?php
/**
 * Which rows are new, and how many.
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Seen;

use SugarCommerce\Tables\Row;
use SugarCommerce\Tables\Utils\Runtime;

defined( 'ABSPATH' ) || exit;

/**
 * The questions asked of a list's rows: is this one new, how many are, which
 * is the newest.
 *
 * Asking whether a row is new reads nothing: the marker is read once, as the
 * list is opened, and everything else is a column of the row. Counting is
 * one query, kept for a minute.
 *
 * @since 1.0.0
 */
final class Rows {

	/**
	 * The object cache group the counts are kept in, named for this copy of
	 * the library: another plugin's copy counts its own tables.
	 *
	 * @return string
	 */
	public static function cache_group(): string {
		return Runtime::handle( 'seen' );
	}

	/**
	 * How long a count is believed, in seconds.
	 */
	public const LIFETIME = 60;

	/**
	 * The moment before which nothing is new, by window, once worked out.
	 *
	 * @var array<int, string>
	 */
	private static array $cutoffs = [];

	/**
	 * How many days a row of a list stays new for, at the most.
	 *
	 * A row is new to somebody when it has arrived since they last opened
	 * its list, and is no older than this. The second half is for the two
	 * people the first is wrong for: back from a fortnight away, they see the
	 * last week lit and not two hundred rows; and never having opened the
	 * list at all, they see the last week lit and not everything there has
	 * ever been. The count beside the menu is held to the same days, so the
	 * number and the rows agree.
	 *
	 * @param string $table_id The table's id.
	 *
	 * @return int Days; 0 for no limit, which is "everything since they last looked".
	 * @since 1.0.0
	 */
	public static function window( string $table_id ): int {
		$window = Seen::get( $table_id )['window'] ?? Config::WINDOW;
		$days   = is_callable( $window ) ? call_user_func( $window ) : $window;

		return max( 0, (int) $days );
	}

	/**
	 * The moment before which nothing is new, as a date is stored.
	 *
	 * Worked out once a request, so the rows of a page and the count beside
	 * the menu are held to the same second.
	 *
	 * @param string $table_id The table's id.
	 *
	 * @return string `Y-m-d H:i:s`, UTC; empty when there is no limit.
	 * @since 1.0.0
	 */
	public static function cutoff( string $table_id ): string {
		$days = self::window( $table_id );

		if ( ! isset( self::$cutoffs[ $days ] ) ) {
			self::$cutoffs[ $days ] = $days > 0 ? gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) : '';
		}

		return self::$cutoffs[ $days ];
	}

	/**
	 * Whether a row is new to whoever is looking.
	 *
	 * Above the marker the page was drawn from, no older than the window,
	 * in the set the site is showing, not one they made themselves, and not
	 * one of the list's exceptions.
	 *
	 * @param string       $table_id The table's id.
	 * @param object|array $row      The row.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_new( string $table_id, $row ): bool {
		$list    = Seen::get( $table_id );
		$user_id = (int) get_current_user_id();

		if ( null === $list || ! $list['on'] || $user_id < 1 ) {
			return false;
		}

		$id = (int) Row::get( $row, $list['id_column'], 0 );

		if ( $id < 1 ) {
			return false;
		}

		$scope = Seen::scope( $table_id );

		// A real order is not news on the test list, nor the other way about.
		if ( '' !== $scope && ! self::in( $row, $list['scopes'][ $scope ] ) ) {
			return false;
		}

		// Stored dates are all one shape, so they compare as they are
		// written. A row that does not say when it was made is not held to
		// the window.
		$created = (string) Row::get( $row, $list['created'], '' );
		$cutoff  = self::cutoff( $table_id );

		if ( '' !== $cutoff && '' !== $created && $created < $cutoff ) {
			return false;
		}

		foreach ( $list['except'] as $column => $not ) {
			if ( in_array( (string) Row::get( $row, $column, '' ), $not, true ) ) {
				return false;
			}
		}

		return $id > Seen::from( $table_id, $user_id, $scope )
			&& ! in_array( $id, Markers::own( $table_id, $user_id, $scope ), true );
	}

	/**
	 * Whether a row is one its list calls blocked.
	 *
	 * By a column of the row and nothing else, so it asks the database for
	 * nothing; and it is the same answer for everybody on every visit, having
	 * nothing to do with what anybody has seen.
	 *
	 * @param string       $table_id The table's id.
	 * @param object|array $row      The row.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_blocked( string $table_id, $row ): bool {
		foreach ( Seen::get( $table_id )['blocked'] ?? [] as $column => $values ) {
			if ( in_array( (string) Row::get( $row, $column, '' ), $values, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a blocked row is let through all the same.
	 *
	 * Asked of whatever the list names for it; a list that names nothing
	 * excuses nobody. Only ever true of a row that is blocked.
	 *
	 * @param string       $table_id The table's id.
	 * @param object|array $row      The row.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_excused( string $table_id, $row ): bool {
		$ask = Seen::get( $table_id )['excused'] ?? null;

		return null !== $ask && self::is_blocked( $table_id, $row ) && (bool) call_user_func( $ask, $row );
	}

	/**
	 * How many rows of a list are new to somebody.
	 *
	 * One count, kept for a minute. Above their marker, which is the primary
	 * key, and inside the window, which is the index on the created column;
	 * MySQL takes whichever is the shorter walk -- the key for somebody who
	 * looked this morning, the date for somebody who never has. The key the
	 * answer is kept under names their marker, the window, and the moment a
	 * row last arrived or went, so a new row and a visit to the list each
	 * make the old answer unfindable; nothing has to be deleted.
	 *
	 * The object cache and not a transient. On a site with a persistent
	 * cache this is no queries at all for a minute. On one without, a
	 * transient would be two reads of the options table and a write to save
	 * one count -- dearer than what it saved.
	 *
	 * The same rows `is_new()` says yes to, clause for clause: what is
	 * counted here is what the list lights.
	 *
	 * @param string $table_id The table's id.
	 * @param int    $user_id  Whose.
	 * @param string $scope    The set; empty for a list that has one.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count( string $table_id, int $user_id, string $scope = '' ): int {
		$list = Seen::get( $table_id );

		// Before anything is read: somebody who may not see the list costs
		// the database nothing.
		if ( null === $list || ! $list['on'] || $user_id < 1 || ! user_can( $user_id, Seen::capability( $table_id ) ) || ! self::ready( $list ) ) {
			return 0;
		}

		$seen = Markers::seen( $table_id, $user_id, $scope );
		$own  = Markers::own( $table_id, $user_id, $scope );
		$key  = implode( ':', [ $table_id, $user_id, $scope, $seen, md5( implode( ',', $own ) ), self::window( $table_id ), self::last_changed( $table_id ) ] );

		$count = wp_cache_get( $key, self::cache_group() );

		if ( false === $count ) {
			$query = [
				'after'  => $seen,
				'since'  => self::cutoff( $table_id ),
				'not_in' => $own,
				'except' => $list['except'],
				'where'  => '' !== $scope ? $list['scopes'][ $scope ] : [],
			];

			$count = null !== $list['count']
				? (int) call_user_func( $list['count'], $query )
				: Query::count( $list, $query );

			wp_cache_set( $key, $count, self::cache_group(), self::LIFETIME );
		}

		return (int) $count;
	}

	/**
	 * The newest row a list has, in a set.
	 *
	 * @param string $table_id The table's id.
	 * @param string $scope    The set; empty for a list that has one.
	 *
	 * @return int Its id; 0 when there is none.
	 * @since 1.0.0
	 */
	public static function newest( string $table_id, string $scope ): int {
		$list = Seen::get( $table_id );

		if ( null === $list || ! self::ready( $list ) ) {
			return 0;
		}

		$where = '' !== $scope ? ( $list['scopes'][ $scope ] ?? [] ) : [];

		return null !== $list['newest']
			? (int) call_user_func( $list['newest'], $where )
			: Query::newest( $list, $where );
	}

	/**
	 * A row of a list arrived or went: any count kept for it is out of date.
	 *
	 * @param string $table_id The table's id.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function touch( string $table_id ): void {
		wp_cache_set( 'last_changed_' . $table_id, microtime(), self::cache_group() );
	}

	/**
	 * Forget the cutoffs worked out on this request.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function reset(): void {
		self::$cutoffs = [];
	}

	/**
	 * Whether a row is in a set: every column the set names, as it names it.
	 *
	 * A number is compared as a number, since a row read from the database
	 * says "1" where the set says 1, and a row that does not say at all is
	 * nought -- a row with no `is_test` is a real one.
	 *
	 * @param object|array          $row   The row.
	 * @param array<string, scalar> $where The set.
	 *
	 * @return bool
	 */
	private static function in( $row, array $where ): bool {
		foreach ( $where as $column => $value ) {
			$actual = Row::get( $row, (string) $column, '' );

			$same = is_int( $value ) || is_bool( $value )
				? (int) $actual === (int) $value
				: (string) $actual === (string) $value;

			if ( ! $same ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether there is anything to ask yet: the list's own tables are there.
	 *
	 * @param array<string, mixed> $config The list.
	 *
	 * @return bool
	 */
	private static function ready( array $config ): bool {
		return null === $config['ready'] || (bool) call_user_func( $config['ready'] );
	}

	/**
	 * When a row of a list last arrived or went, as far as the cache knows.
	 *
	 * @param string $table_id The table's id.
	 *
	 * @return string
	 */
	private static function last_changed( string $table_id ): string {
		$changed = wp_cache_get( 'last_changed_' . $table_id, self::cache_group() );

		if ( false === $changed ) {
			$changed = microtime();

			wp_cache_set( 'last_changed_' . $table_id, $changed, self::cache_group() );
		}

		return (string) $changed;
	}
}
