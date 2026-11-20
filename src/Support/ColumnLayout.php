<?php
/**
 * Column Layout
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Support;

use SugarCommerce\Tables\Editing\Reorder;

/**
 * Which columns are narrow, and which facts ride with the primary column on
 * a phone.
 *
 * The table is laid out by what is in it (no `fixed` layout, like core's
 * Plugins screen), so nobody sets a width. A short figure -- a price, a
 * count, a status, a date -- is drawn on one line and its column is as wide
 * as that line, and the columns with words in them share what is left: the
 * name stops being cut to "Charlotte …" beside a Sales column four times the
 * width of its numbers.
 *
 * On a phone core shows the primary column alone and puts the rest behind
 * its "Show more details" toggle. A row there is its title and nothing else,
 * so a status and an amount are repeated under the title, as core puts a
 * comment's author into the comment on a phone.
 */
final class ColumnLayout {

	/**
	 * The column types drawn on one line, as wide as what is in them.
	 *
	 * @var string[]
	 */
	public const array COMPACT_TYPES = [
		'boolean',
		'color',
		'count',
		'date',
		'duration',
		'file_size',
		'image',
		'percentage',
		'price',
		'rate',
		'status',
	];

	/**
	 * A column's type: the one it names, or the one its key suggests.
	 *
	 * @param string $key    The column's key.
	 * @param mixed  $column Its configuration: a label, or an array.
	 *
	 * @return string|null Null for none, or for a column that turned typing off.
	 */
	public static function type( string $key, mixed $column ): ?string {
		if ( is_array( $column ) && array_key_exists( 'type', $column ) ) {
			return is_string( $column['type'] ) && '' !== $column['type'] ? $column['type'] : null;
		}

		return Columns::detect_type( $key );
	}

	/**
	 * Whether a column is drawn on one line, as wide as what is in it.
	 *
	 * Its own `compact` says, true or false. Otherwise a figure is (see
	 * COMPACT_TYPES), and so is a switch or a reorder handle; the primary
	 * column, and one with a width or a title of its own, is not.
	 *
	 * @param string               $key    The column's key.
	 * @param mixed                $column Its configuration.
	 * @param array<string, mixed> $config The table's configuration.
	 *
	 * @return bool
	 */
	public static function is_compact( string $key, mixed $column, array $config ): bool {
		if ( is_array( $column ) && isset( $column['compact'] ) ) {
			return (bool) $column['compact'];
		}

		if ( $key === (string) ( $config['primary_column'] ?? '' ) ) {
			return false;
		}

		if ( is_array( $column ) ) {
			if ( ! empty( $column['width'] ) || isset( $column['title'] ) ) {
				return false;
			}

			if ( isset( $column['toggle'] ) ) {
				return true;
			}
		}

		$reorder = Reorder::config( $config );

		if ( null !== $reorder && $reorder['column'] === $key ) {
			return true;
		}

		return in_array( self::type( $key, $column ), self::COMPACT_TYPES, true );
	}

	/**
	 * The columns repeated under a row's title on a phone.
	 *
	 * The table's `summary`: a list of column keys, or false for none.
	 * Unset, it is the first status column and the first money column --
	 * what a row is and what it was for. Never the primary column itself,
	 * and never one this person switched off.
	 *
	 * @param array<string, mixed> $config  The table's configuration.
	 * @param string[]             $hidden  The columns switched off.
	 *
	 * @return string[]
	 */
	public static function summary( array $config, array $hidden = [] ): array {
		$columns = (array) ( $config['columns'] ?? [] );
		$primary = (string) ( $config['primary_column'] ?? '' );
		$wanted  = $config['summary'] ?? null;

		if ( false === $wanted ) {
			return [];
		}

		if ( null === $wanted ) {
			$wanted = [];

			foreach ( [ 'status', 'price' ] as $type ) {
				foreach ( $columns as $key => $column ) {
					if ( $key !== $primary && self::type( (string) $key, $column ) === $type ) {
						$wanted[] = (string) $key;

						break;
					}
				}
			}
		}

		return array_values(
			array_filter(
				array_map( 'strval', (array) $wanted ),
				static fn( string $key ): bool => isset( $columns[ $key ] ) && $key !== $primary && ! in_array( $key, $hidden, true )
			)
		);
	}
}
