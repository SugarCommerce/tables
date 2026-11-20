<?php
/**
 * Shared Cells
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Traits\Table;

use SugarCommerce\Tables\Cells\Item;
use SugarCommerce\Tables\Cells\Person;
use SugarCommerce\Tables\HiddenColumns;
use SugarCommerce\Tables\ListDate;
use SugarCommerce\Tables\Support\Row;

/**
 * The cells every list draws the same way, as column types: a person, one
 * thing a row names, and a date in the list's date mode -- plus not drawing
 * a deferred column nobody is looking at.
 *
 * Here and not in the static formatters because each wants the table: its
 * panels, its id for the hooks, and whose date preference it reads.
 */
trait CellRenderer {

	/**
	 * How this person wants this table's dates said, once worked out.
	 *
	 * @var string|null
	 */
	private ?string $date_mode = null;

	/**
	 * Hidden columns, once read.
	 *
	 * @var array<string, bool>
	 */
	private array $hidden_now = [];

	/**
	 * How this person wants this table's dates said.
	 *
	 * @return string `relative` or `exact`.
	 * @since 1.0.0
	 */
	private function date_mode(): string {
		return $this->date_mode ??= ListDate::mode( ListDate::meta_key( $this->id, $this->config ) );
	}

	/**
	 * A cell drawn before anything else is asked of the column, or null to
	 * draw it the usual way.
	 *
	 * @param array  $column      The column's configuration.
	 * @param mixed  $item        The row.
	 * @param string $column_name The column.
	 *
	 * @return string|null
	 * @since 1.0.0
	 */
	private function shared_cell( array $column, $item, string $column_name ): ?string {
		// Not worked out while nobody is looking at it; see HiddenColumns.
		if ( true === ( $column['deferred'] ?? false ) && $this->column_is_hidden( $column_name ) ) {
			return HiddenColumns::placeholder();
		}

		return match ( $column['type'] ?? null ) {
			'person' => $this->person_cell( $column, $item ),
			'item'   => $this->item_cell( $column, $item ),
			default  => null,
		};
	}

	/**
	 * Whether a column is hidden for whoever is looking.
	 *
	 * @param string $column_name The column.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function column_is_hidden( string $column_name ): bool {
		return $this->hidden_now[ $column_name ] ??= HiddenColumns::is_hidden( $this->id, $column_name );
	}

	/**
	 * A `person` column.
	 *
	 * Its `person` callable says who the row's person is -- `name`, `email`,
	 * `id`, `after`, as Cells\Person takes them -- or null for nobody; left
	 * out, they are the row's own `name`, `email` and id. `link` opens
	 * `view_flyout` or `edit_flyout`, a panel by its full id, or a URL a
	 * callable returns; `avatar`, `avatar_size` and `email_line` are passed
	 * on.
	 *
	 * @param array $column The column's configuration.
	 * @param mixed $item   The row.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function person_cell( array $column, $item ): string {
		$parts = isset( $column['person'] ) && is_callable( $column['person'] )
			? call_user_func( $column['person'], $item )
			: [
				'name'  => (string) Row::get( $item, 'name', '' ),
				'email' => (string) Row::get( $item, 'email', '' ),
				'id'    => Row::id( $item ),
			];

		if ( ! is_array( $parts ) ) {
			$parts = [];
		}

		$parts += array_intersect_key( $column, array_flip( [ 'avatar', 'avatar_size', 'email_line' ] ) );
		$parts += $this->person_link( $column['link'] ?? null, $item );

		/**
		 * Filter a person's cell in a table.
		 *
		 * @param string $html  The markup.
		 * @param array  $parts What it was built from.
		 * @param mixed  $item  The row.
		 *
		 * @since 1.0.0
		 */
		return (string) apply_filters( "sugarcommerce_table_person_cell_{$this->id}", Person::cell( $parts ), $parts, $item );
	}

	/**
	 * Where a person's name goes, as Cells\Person's parts.
	 *
	 * A closure or an array callable returns a URL. A string is the table's
	 * `view_flyout` or `edit_flyout`, a URL when it has a slash in it, and
	 * otherwise a panel's full id -- never a function's name, so a panel
	 * that happens to share one is not called.
	 *
	 * @param mixed $link The column's `link`.
	 * @param mixed $item The row.
	 *
	 * @return array<string, string>
	 * @since 1.0.0
	 */
	private function person_link( $link, $item ): array {
		if ( $link instanceof \Closure || ( is_array( $link ) && is_callable( $link ) ) ) {
			return [ 'url' => (string) call_user_func( $link, $item ) ];
		}

		if ( ! is_string( $link ) || '' === $link ) {
			return [];
		}

		if ( in_array( $link, [ 'view_flyout', 'edit_flyout' ], true ) ) {
			return [ 'flyout' => (string) ( $this->config['flyouts'][ substr( $link, 0, 4 ) ] ?? '' ) ];
		}

		return str_contains( $link, '/' ) ? [ 'url' => $link ] : [ 'flyout' => $link ];
	}

	/**
	 * An `item` column: its `item` callable returns Cells\Item's parts.
	 *
	 * @param array $column The column's configuration.
	 * @param mixed $item   The row.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function item_cell( array $column, $item ): string {
		$parts = isset( $column['item'] ) && is_callable( $column['item'] )
			? call_user_func( $column['item'], $item )
			: [ 'title' => (string) Row::get( $item, 'title', (string) Row::get( $item, 'name', '' ) ) ];

		if ( ! is_array( $parts ) || [] === array_filter( $parts ) ) {
			return '<span aria-hidden="true">&mdash;</span>';
		}

		/**
		 * Filter a cell that names one thing, in a table.
		 *
		 * @param string $html  The markup.
		 * @param array  $parts What it was built from.
		 * @param mixed  $item  The row.
		 *
		 * @since 1.0.0
		 */
		return (string) apply_filters( "sugarcommerce_table_item_cell_{$this->id}", Item::cell( $parts ), $parts, $item );
	}
}
