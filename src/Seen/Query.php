<?php
/**
 * The two questions a marker asks of the database.
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
 * SQL for a list whose rows are a table of their own.
 *
 * The table and every column are names a table registered, passed through
 * `%i`; every value is a placeholder. A list that is not a table of its own
 * gives `count` and `newest` callables instead, and none of this runs.
 *
 * @since 1.0.0
 */
final class Query {

	/**
	 * How many rows are new: above a marker, inside a window, not somebody's
	 * own, not an exception, and in a set.
	 *
	 * @param array<string, mixed> $source The list.
	 * @param array<string, mixed> $query `after`, `since`, `not_in`, `except` and `where`.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count( array $source, array $query ): int {
		global $wpdb;

		$values = [ $wpdb->prefix . $source['table'], $source['id_column'], (int) $query['after'] ];
		$sql    = 'SELECT COUNT(*) FROM %i WHERE %i > %d';

		foreach ( (array) $query['where'] as $column => $value ) {
			$sql     .= ' AND %i = ' . self::placeholder( $value );
			$values[] = (string) $column;
			$values[] = $value;
		}

		if ( '' !== (string) $query['since'] ) {
			$sql     .= ' AND %i >= %s';
			$values[] = $source['created'];
			$values[] = (string) $query['since'];
		}

		$own = array_map( 'intval', (array) $query['not_in'] );

		if ( [] !== $own ) {
			$sql   .= ' AND %i NOT IN ( ' . implode( ', ', array_fill( 0, count( $own ), '%d' ) ) . ' )';
			$values = array_merge( $values, [ $source['id_column'] ], $own );
		}

		foreach ( (array) $query['except'] as $column => $not ) {
			$sql   .= ' AND %i NOT IN ( ' . implode( ', ', array_fill( 0, count( $not ), '%s' ) ) . ' )';
			$values = array_merge( $values, [ (string) $column ], $not );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Put together above from literals, a clause for each thing there is to say and a placeholder for each value; kept by the caller.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * The newest row's id, in a set.
	 *
	 * Asked when the list is opened, and of the primary key.
	 *
	 * @param array<string, mixed>  $source The list.
	 * @param array<string, scalar> $where The set's columns.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function newest( array $source, array $where ): int {
		global $wpdb;

		$values = [ $source['id_column'], $wpdb->prefix . $source['table'] ];
		$sql    = 'SELECT MAX(%i) FROM %i';
		$joiner = ' WHERE ';

		foreach ( $where as $column => $value ) {
			$sql     .= $joiner . '%i = ' . self::placeholder( $value );
			$values[] = (string) $column;
			$values[] = $value;
			$joiner   = ' AND ';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * The placeholder for a value: a number as a number.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string
	 */
	private static function placeholder( $value ): string {
		return is_int( $value ) || is_bool( $value ) ? '%d' : '%s';
	}
}
