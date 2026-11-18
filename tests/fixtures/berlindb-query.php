<?php
/**
 * A stand-in for a BerlinDB Query class.
 *
 * In memory, and only the arguments the source sends: equality on a column,
 * `id__in` and any `{column}__in`, `search` (a LIKE on name and email),
 * `orderby`/`order`, `number` (0 for all)/`offset`, `count` and
 * `'fields' => 'ids'`. Every query it is asked is logged, so a test can say
 * what reached it.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests\Fixtures;

/**
 * The rows, as BerlinDB rows: objects with public columns.
 */
class FakeQuery {

	/**
	 * The table.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static array $rows = [];

	/**
	 * Every query asked, in order.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static array $log = [];

	/**
	 * Every write, in order.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	public static array $writes = [];

	/**
	 * Fill the table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows, each with an id.
	 *
	 * @return void
	 */
	public static function seed( array $rows ): void {
		static::$rows   = [];
		static::$log    = [];
		static::$writes = [];

		foreach ( $rows as $row ) {
			static::$rows[ (int) $row['id'] ] = $row;
		}
	}

	/**
	 * Run a query.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return array<int, mixed>|int
	 */
	public function query( array $args = [] ): array|int {
		static::$log[] = $args;

		$rows = array_filter( static::$rows, static fn( array $row ): bool => self::matches( $row, $args ) );

		if ( ! empty( $args['count'] ) ) {
			return count( $rows );
		}

		$by    = (string) ( $args['orderby'] ?? 'id' );
		$order = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 1 : -1;

		usort( $rows, static fn( array $a, array $b ): int => $order * ( ( $a[ $by ] ?? '' ) <=> ( $b[ $by ] ?? '' ) ?: $a['id'] <=> $b['id'] ) );

		$number = (int) ( $args['number'] ?? 20 );
		$rows   = array_slice( $rows, (int) ( $args['offset'] ?? 0 ), $number > 0 ? $number : null );

		if ( 'ids' === ( $args['fields'] ?? '' ) ) {
			return array_map( static fn( array $row ): int => (int) $row['id'], $rows );
		}

		return array_map( static fn( array $row ): object => (object) $row, $rows );
	}

	/**
	 * One row.
	 *
	 * @param int $id The id.
	 *
	 * @return object|false
	 */
	public function get_item( int $id ): object|false {
		return isset( static::$rows[ $id ] ) ? (object) static::$rows[ $id ] : false;
	}

	/**
	 * Delete one.
	 *
	 * @param int $id The id.
	 *
	 * @return bool
	 */
	public function delete_item( int $id ): bool {
		static::$writes[] = [ 'delete', $id ];

		if ( ! isset( static::$rows[ $id ] ) ) {
			return false;
		}

		unset( static::$rows[ $id ] );

		return true;
	}

	/**
	 * Update one.
	 *
	 * @param int                  $id   The id.
	 * @param array<string, mixed> $data Columns.
	 *
	 * @return bool
	 */
	public function update_item( int $id, array $data ): bool {
		static::$writes[] = [ 'update', $id, $data ];

		if ( ! isset( static::$rows[ $id ] ) ) {
			return false;
		}

		static::$rows[ $id ] = array_merge( static::$rows[ $id ], $data );

		return true;
	}

	/**
	 * Whether a row matches the arguments.
	 *
	 * @param array<string, mixed> $row  The row.
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return bool
	 */
	private static function matches( array $row, array $args ): bool {
		foreach ( $args as $key => $value ) {
			if ( in_array( $key, [ 'number', 'offset', 'orderby', 'order', 'count', 'fields' ], true ) ) {
				continue;
			}

			if ( 'search' === $key ) {
				$haystack = strtolower( ( $row['name'] ?? '' ) . ' ' . ( $row['email'] ?? '' ) );

				if ( ! str_contains( $haystack, strtolower( (string) $value ) ) ) {
					return false;
				}

				continue;
			}

			if ( str_ends_with( (string) $key, '__in' ) ) {
				$column = substr( (string) $key, 0, -4 );

				// As BerlinDB reads it: an empty list is no restriction.
				if ( [] === (array) $value ) {
					continue;
				}

				if ( ! in_array( $row[ $column ] ?? null, (array) $value, false ) ) {
					return false;
				}

				continue;
			}

			if ( (string) ( $row[ $key ] ?? '' ) !== (string) $value ) {
				return false;
			}
		}

		return true;
	}
}

/**
 * A second table, for priming: what the first table's rows point at.
 */
final class FakeRelatedQuery extends FakeQuery {

	/**
	 * Its own table.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static array $rows = [];

	/**
	 * Its own log.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static array $log = [];

	/**
	 * Its own writes.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	public static array $writes = [];
}
