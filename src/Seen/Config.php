<?php
/**
 * A list's `seen` configuration, filled in.
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
 * Turning what a table said about its marker into what the marker reads.
 *
 * Done once, as the table is registered, so nothing that runs a row at a
 * time has to wonder whether a key is there.
 *
 * @since 1.0.0
 */
final class Config {

	/**
	 * How many days a row stays new for, unless the table says.
	 */
	public const int WINDOW = 7;

	/**
	 * A table's `seen` and `highlights`, normalised.
	 *
	 * @param string $table_id The table's id.
	 * @param array  $raw      The table's configuration, as known so far.
	 *
	 * @return array<string, mixed>
	 * @since 1.0.0
	 */
	public static function normalise( string $table_id, array $raw ): array {
		$seen = is_array( $raw['seen'] ?? null ) ? $raw['seen'] : [];
		$on   = ! empty( $raw['seen'] );

		$columns = is_array( $seen['columns'] ?? null ) ? $seen['columns'] : [];

		return [
			'raw'         => $raw,
			'on'          => $on,

			// What a new row is called, read aloud for whoever cannot see
			// that it is lit: "New order".
			'label'       => (string) ( $seen['label'] ?? __( 'New', 'sugarcommerce' ) ),

			// Where the rows are, for the two questions asked of the
			// database: the newest id, and how many are new. Without the
			// site's table prefix, which is added. Left out, the table's id.
			'table'       => sanitize_key( (string) ( $seen['table'] ?? $table_id ) ),
			'id_column'   => sanitize_key( (string) ( $columns['id'] ?? 'id' ) ),
			'created'     => sanitize_key( (string) ( $columns['created'] ?? 'date_created' ) ),

			// Asked instead of the database, for rows that are not in a
			// table of their own.
			'count'       => is_callable( $seen['count'] ?? null ) ? $seen['count'] : null,
			'newest'      => is_callable( $seen['newest'] ?? null ) ? $seen['newest'] : null,
			'ready'       => is_callable( $seen['ready'] ?? null ) ? $seen['ready'] : null,

			// Kept apart: `scopes` names each set of rows by the columns
			// that say a row is in it, `scope` which set is showing.
			'scopes'      => self::scopes( $seen['scopes'] ?? [] ),
			'scope'       => is_callable( $seen['scope'] ?? null ) ? $seen['scope'] : null,

			'except'      => self::exceptions( $seen['except'] ?? [] ),
			'blocked'     => self::exceptions( $seen['blocked'] ?? [] ),
			'excused'     => is_callable( $seen['excused'] ?? null ) ? $seen['excused'] : null,

			'window'      => $seen['window'] ?? self::WINDOW,
			'changed'     => array_values( array_filter( array_map( 'strval', (array) ( $seen['changed'] ?? [] ) ) ) ),
			'by_hand'     => self::by_hand( $seen['by_hand'] ?? [] ),

			'capability'  => (string) ( $seen['capability'] ?? $raw['capabilities']['view'] ?? $raw['capability'] ?? 'manage_options' ),
			'menu_count'  => self::menu_count( $seen['menu_count'] ?? false ),
			'meta_prefix' => (string) ( $seen['meta_prefix'] ?? $table_id . '_' ),

			'menu_slug'   => (string) ( $raw['menu_slug'] ?? '' ),
			'parent_slug' => (string) ( $raw['parent_slug'] ?? '' ),
			'highlights'  => is_array( $raw['highlights'] ?? null ) ? $raw['highlights'] : [],
		];
	}

	/**
	 * A list's sets, as a name and the columns that put a row in it.
	 *
	 * @param mixed $scopes What the table said.
	 *
	 * @return array<string, array<string, scalar>>
	 */
	private static function scopes( $scopes ): array {
		$clean = [];

		foreach ( (array) $scopes as $name => $where ) {
			$name  = sanitize_key( (string) $name );
			$where = array_filter( (array) $where, 'is_scalar' );
			$keys  = array_map( static fn( $column ): string => sanitize_key( (string) $column ), array_keys( $where ) );

			if ( '' !== $name && [] !== $where ) {
				$clean[ $name ] = array_combine( $keys, array_values( $where ) );
			}
		}

		return $clean;
	}

	/**
	 * Rows a list never calls news, or calls blocked: a column and the values of it.
	 *
	 * @param mixed $except What the table said.
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function exceptions( $except ): array {
		$clean = [];

		foreach ( (array) $except as $column => $values ) {
			$column = sanitize_key( (string) $column );
			$values = array_values( array_filter( array_map( 'strval', (array) $values ), static fn( string $value ): bool => '' !== $value ) );

			if ( '' !== $column && [] !== $values ) {
				$clean[ $column ] = $values;
			}
		}

		return $clean;
	}

	/**
	 * The actions that say somebody made a row by hand.
	 *
	 * A name alone, a list of names, or a name and a callable that is given
	 * the action's arguments and says which row (and, optionally, which set).
	 *
	 * @param mixed $by_hand What the table said.
	 *
	 * @return array<string, callable|null>
	 */
	private static function by_hand( $by_hand ): array {
		$clean = [];

		foreach ( (array) $by_hand as $hook => $ask ) {
			if ( is_int( $hook ) ) {
				$hook = (string) $ask;
				$ask  = null;
			}

			if ( '' !== (string) $hook ) {
				$clean[ (string) $hook ] = is_callable( $ask ) ? $ask : null;
			}
		}

		return $clean;
	}

	/**
	 * The count beside the menu: off, or how it is drawn.
	 *
	 * @param mixed $menu_count What the table said.
	 *
	 * @return array{parent: bool, text: callable|null}|false
	 */
	private static function menu_count( $menu_count ) {
		if ( empty( $menu_count ) ) {
			return false;
		}

		$menu_count = is_array( $menu_count ) ? $menu_count : [];

		return [
			'parent' => (bool) ( $menu_count['parent'] ?? true ),
			'text'   => is_callable( $menu_count['text'] ?? null ) ? $menu_count['text'] : null,
		];
	}
}
