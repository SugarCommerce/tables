<?php
/**
 * Reordering Rows
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Editing;

use SugarCommerce\Plugin\Assets;
use SugarCommerce\Tables\Utils\Runtime;
use SugarCommerce\Tables\Support\Columns;
use SugarCommerce\Tables\Support\Request;
use SugarCommerce\Tables\Support\Row;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\ToggleRoute;

defined( 'ABSPATH' ) || exit;

/**
 * Rows a person puts in order themselves: `reorder`.
 *
 * A list table sorts its rows by a column. Some lists are in an order
 * somebody chose -- collections, plans, the lessons of a course -- and the
 * order is the point of the screen. This gives one column a handle and two
 * buttons, and saves the order the rows end up in:
 *
 *     'orderby'  => 'sort_order',
 *     'order'    => 'asc',
 *     'per_page' => 100,
 *     'reorder'  => [
 *         'column' => 'sort_order',                         // the column the handle is in
 *         'save'   => static fn( array $ids ): bool => my_plugin_reorder( $ids ),
 *     ],
 *
 * Three ways to the same move, and one function behind all of them in the
 * script. The handle is dragged with a mouse and answers the arrow keys when
 * it has focus; the two buttons are what a thumb gets, on a screen too
 * narrow to drag a row along. Each move posts the ids of the rows on the
 * page, top to bottom, and says where the row went.
 *
 * The controls are only drawn while the list is showing that order: not
 * sorted by another column, not narrowed by a search, a status or a filter.
 * Sorted by name, or narrowed, a row dragged above another has not been put
 * anywhere that means anything. A row can only be dragged past rows on the
 * same page, so such a list wants a page long enough to hold them all.
 *
 * @since 1.0.0
 */
final class Reorder {

	/**
	 * Whether the enqueue is hooked.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Where each table is up to in numbering the rows it is drawing.
	 *
	 * @var array<string, int>
	 */
	private static array $drawn = [];

	/**
	 * A table's reorder settings, filled in, or null when it has none.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function config( array $config ): ?array {
		$reorder = $config['reorder'] ?? null;

		if ( ! is_array( $reorder ) || ! is_callable( $reorder['save'] ?? null ) ) {
			return null;
		}

		$reorder = array_merge(
			[
				'column'     => 'sort_order',
				'orderby'    => '',
				'capability' => '',
				'title'      => null,
				'position'   => null,
				'filtered'   => false,
				'labels'     => [],
			],
			$reorder
		);

		$reorder['column']  = (string) $reorder['column'];
		$reorder['orderby'] = '' !== (string) $reorder['orderby'] ? (string) $reorder['orderby'] : $reorder['column'];
		$reorder['labels']  = array_merge(
			[
				/* translators: %s: a row's name. */
				'move'   => __( 'Move “%s”. Drag, or use the up and down arrow keys.', 'sugarcommerce' ),
				/* translators: %s: a row's name. */
				'up'     => __( 'Move “%s” up', 'sugarcommerce' ),
				/* translators: %s: a row's name. */
				'down'   => __( 'Move “%s” down', 'sugarcommerce' ),
				/* translators: 1: a row's name, 2: its new position, 3: how many rows are listed. */
				'moved'  => __( '“%1$s” moved to position %2$s of %3$s.', 'sugarcommerce' ),
				'failed' => __( 'The new order could not be saved. Reload the page and try again.', 'sugarcommerce' ),
			],
			(array) $reorder['labels']
		);

		return $reorder;
	}

	/**
	 * The capability that may move rows: the reorder's own, or the table's
	 * edit capability.
	 *
	 * @param array $config  Table configuration.
	 * @param array $reorder The reorder settings.
	 *
	 * @return string
	 */
	public static function capability( array $config, array $reorder ): string {
		if ( '' !== (string) $reorder['capability'] ) {
			return (string) $reorder['capability'];
		}

		return (string) ( $config['capabilities']['edit'] ?? $config['capability'] ?? 'manage_options' );
	}

	/**
	 * Hook the script for a table that reorders; its save is a REST route
	 * (Routes).
	 *
	 * @param string $table  Table id.
	 * @param array  $config Table configuration.
	 *
	 * @return void
	 */
	public static function boot( string $table, array $config ): void {
		if ( null === self::config( $config ) ) {
			return;
		}

		if ( ! self::$booted ) {
			self::$booted = true;

			add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		}
	}

	/**
	 * Whether the list on screen is in the order that can be changed.
	 *
	 * Not sorted by anything else -- by nothing, or by the reorder's own
	 * column going up, whether the address says so or the table's own
	 * default does -- and not searched. Nor narrowed by a status or a
	 * filter, unless the reorder says `filtered`: the ids posted are only
	 * the rows in view, and an order among some of them is not an order.
	 *
	 * @param array $config  Table configuration.
	 * @param array $reorder The reorder settings.
	 *
	 * @return bool
	 */
	public static function in_order( array $config, array $reorder ): bool {
		// Reading how a list is sorted, not acting on anything.
		$orderby = Request::key( 'orderby' );
		$order   = strtolower( Request::key( 'order', 'asc' ) );

		// An address that names no sort shows the list in its own default
		// one, which is "nothing else" only when the table has none or it
		// is the reorder's. A list of orders newest first is not in the
		// order somebody chose, and a row dragged there would be saved
		// into a place nobody can see.
		if ( '' === $orderby ) {
			$orderby = (string) ( $config['orderby'] ?? '' );
			$order   = strtolower( (string) ( $config['order'] ?? 'asc' ) );
		}

		if ( '' !== trim( Request::text( 's' ) ) ) {
			return false;
		}

		if ( '' !== $orderby && ! ( $reorder['orderby'] === $orderby && 'asc' === $order ) ) {
			return false;
		}

		if ( ! empty( $reorder['filtered'] ) ) {
			return true;
		}

		$status = Request::key( 'status' );

		if ( '' !== $status && 'all' !== $status && '-1' !== $status ) {
			return false;
		}

		foreach ( array_keys( (array) ( $config['filters'] ?? [] ) ) as $key ) {
			if ( '' !== Request::text( (string) $key ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * One row's cell: its place, and while the list is in order, the means
	 * to move it.
	 *
	 * The number is the row's place -- first is 1 -- rather than a stored
	 * figure, which starts at nought and can have gaps: `position` when the
	 * table can say it (so a list sorted by name still shows each row's
	 * place in the chosen order), and otherwise its place in the list being
	 * drawn, counted on from the page's offset.
	 *
	 * @param string $table  Table id.
	 * @param array  $config Table configuration.
	 * @param mixed  $row    The row.
	 * @param int    $offset How many rows come before this page.
	 *
	 * @return string
	 */
	public static function cell( string $table, array $config, mixed $row, int $offset = 0 ): string {
		$reorder = self::config( $config );

		if ( null === $reorder ) {
			return '';
		}

		$in_order = self::in_order( $config, $reorder );

		self::$drawn[ $table ] = ( self::$drawn[ $table ] ?? 0 ) + 1;

		if ( is_callable( $reorder['position'] ) ) {
			$place = (int) call_user_func( $reorder['position'], $row );
		} elseif ( $in_order ) {
			$place = $offset + self::$drawn[ $table ];
		} else {
			return Columns::render_empty();
		}

		$number = '<span class="sugarcommerce-reorder__number">' . esc_html( number_format_i18n( $place ) ) . '</span>';

		if ( ! $in_order || ! current_user_can( self::capability( $config, $reorder ) ) ) {
			return $number;
		}

		$name   = self::name( $reorder, $row );
		$labels = $reorder['labels'];

		return sprintf(
			'<span class="sugarcommerce-reorder" data-id="%1$d">'
			. '<button type="button" class="sugarcommerce-reorder__handle" aria-label="%2$s">%6$s</button>'
			. '%3$s'
			. '<button type="button" class="button button-small sugarcommerce-reorder__step" data-step="-1" aria-label="%4$s">%7$s</button>'
			. '<button type="button" class="button button-small sugarcommerce-reorder__step" data-step="1" aria-label="%5$s">%8$s</button>'
			. '</span>',
			Row::id( $row ),
			esc_attr( sprintf( (string) $labels['move'], $name ) ),
			$number,
			esc_attr( sprintf( (string) $labels['up'], $name ) ),
			esc_attr( sprintf( (string) $labels['down'], $name ) ),
			Columns::icon( 'heroicons-outline:bars-3' ),
			Columns::icon( 'heroicons-outline:chevron-up' ),
			Columns::icon( 'heroicons-outline:chevron-down' )
		);
	}

	/**
	 * Start the numbering again, for a fresh draw (and for tests).
	 *
	 * @param string $table Table id.
	 *
	 * @return void
	 */
	public static function reset( string $table ): void {
		unset( self::$drawn[ $table ] );
	}

	/**
	 * What the row is called in the controls' names.
	 *
	 * @param array $reorder The reorder settings.
	 * @param mixed $row     The row.
	 *
	 * @return string
	 */
	private static function name( array $reorder, mixed $row ): string {
		if ( is_callable( $reorder['title'] ) ) {
			return (string) call_user_func( $reorder['title'], $row );
		}

		foreach ( [ 'title', 'name', 'label' ] as $field ) {
			$value = (string) Row::get( $row, $field, '' );

			if ( '' !== $value ) {
				return $value;
			}
		}

		/* translators: %d: a row's id. */
		return sprintf( __( '#%d', 'sugarcommerce' ), Row::id( $row ) );
	}

	/**
	 * Save the order the rows were moved into.
	 *
	 * Apart from the request so it can be asked directly. The ids are the
	 * page's rows, top to bottom; `save` decides what that means for the
	 * rows not on it, and may answer an array (the new order, say), which
	 * is handed back to the script.
	 *
	 * @param string $table Table id.
	 * @param array  $ids   The ids, in their new order.
	 *
	 * @return array{ok: bool, status: int, data: array<string, mixed>}
	 */
	public static function save( string $table, array $ids ): array {
		$config  = Tables::get_table( $table );
		$reorder = null !== $config ? self::config( $config ) : null;

		if ( null === $config || null === $reorder ) {
			return self::answer( false, 404, [ 'message' => __( 'There is nothing here to put in order.', 'sugarcommerce' ) ] );
		}

		if ( ! current_user_can( self::capability( $config, $reorder ) ) ) {
			return self::answer( false, 403, [ 'message' => __( 'You are not allowed to do that.', 'sugarcommerce' ) ] );
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		if ( [] === $ids ) {
			return self::answer( false, 400, [ 'message' => (string) $reorder['labels']['failed'] ] );
		}

		$result = call_user_func( $reorder['save'], $ids );

		if ( false === $result || is_wp_error( $result ) ) {
			return self::answer( false, 500, [ 'message' => (string) $reorder['labels']['failed'] ] );
		}

		/**
		 * Fires once a table's rows have been put in a new order.
		 *
		 * @param int[] $ids The ids, in their new order.
		 */
		do_action( "sugarcommerce_table_reordered_{$table}", $ids );

		return self::answer( true, 200, [ 'order' => is_array( $result ) ? $result : $ids ] );
	}

	/**
	 * An answer, in the shape save() gives.
	 *
	 * @param bool  $ok     Whether it saved.
	 * @param int   $status HTTP status.
	 * @param array $data   What goes back.
	 *
	 * @return array{ok: bool, status: int, data: array<string, mixed>}
	 */
	private static function answer( bool $ok, int $status, array $data ): array {
		return [
			'ok'     => $ok,
			'status' => $status,
			'data'   => $data,
		];
	}

	/**
	 * The script and stylesheet, on a screen whose table reorders.
	 *
	 * The drag is the script's own, on pointer events, so a mouse, a pen and
	 * a finger drag alike and neither jQuery UI's sortable nor touch punch is
	 * loaded for it. It keeps sortable's classes, which reorder.css styles.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		[ $table, $config ] = ToggleRoute::current();

		if ( null === $config || null === self::config( $config ) ) {
			return;
		}

		$reorder = (array) self::config( $config );

		Assets::enqueue_style( Runtime::handle( 'reorder' ), __FILE__, 'css/reorder.css' );
		Assets::enqueue_built_script( Runtime::handle( 'reorder' ), __FILE__, 'reorder' );

		wp_localize_script(
			Runtime::handle( 'reorder' ),
			'sugarCommerceReorder',
			[
				// The REST route the order is saved through, with api-fetch.
				'path' => Routes::path( (string) $table, 'reorder' ),
				'i18n' => [
					'moved'  => (string) $reorder['labels']['moved'],
					'failed' => (string) $reorder['labels']['failed'],
				],
			]
		);
	}
}
