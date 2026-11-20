<?php
/**
 * A ten-column table as Table draws it, for the browser tests.
 *
 * Registered with no `primary_column`, as most lists are, and drawn through
 * the package's own decisions -- the table's classes, the primary column,
 * each cell and the row actions -- into the markup core's
 * WP_List_Table::single_row_columns() wraps them in. Printed as JSON:
 * `{ "classes": "...", "head": "<tr>...", "rows": "<tr>..." }`.
 *
 * Run by layout.spec.mjs with `php tests/browser/render.php`.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

require dirname( __DIR__ ) . '/bootstrap.php';

use SugarCommerce\Tables\Table;
use SugarCommerce\Tables\Tables;

rt_reset_globals();

$long = static fn( string $word, int $times ): string => implode( ' ', array_fill( 0, $times, $word ) );

Tables::register(
	'shop_subscriptions',
	[
		'menu_slug'   => 'shop-subscriptions',
		'labels'      => [
			'singular' => 'subscription',
			'plural'   => 'subscriptions',
		],
		'columns'     => [
			'id'           => [
				'label' => 'Subscription',
				'title' => static fn( object $row ): string => '#' . (int) $row->id,
			],
			'buyer'        => [
				'label'    => 'Customer',
				'callback' => static fn( object $row ): string => esc_html( $row->buyer ),
			],
			'email'        => [
				'label'    => 'Email',
				'callback' => static fn( object $row ): string => '<span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . esc_html( $row->email ) . '</span>',
			],
			// Lines that do not wrap, cut short with an ellipsis, as a
			// person's address and a product's name are drawn (Cells\Person,
			// Cells\Item): as wide as all of their text to any table that
			// is not core's fixed one.
			'product'      => [
				'label'    => 'Product',
				'callback' => static fn( object $row ): string => '<span class="sugarcommerce-item-title">' . esc_html( $row->product ) . '</span>',
			],
			'status'       => 'Status',
			'charged'      => [
				'label'    => 'Amount',
				'callback' => static fn( object $row ): string => esc_html( $row->charged ),
			],
			'licence'      => 'Licence',
			'country'      => 'Country',
			'notes'        => 'Notes',
			'date_created' => 'Date',
		],
		'bulk_actions' => [ 'delete' => 'Delete' ],
		'row_actions' => [
			'edit'   => [
				'label' => 'Edit',
				'url'   => '#edit',
			],
			'cancel' => [
				'label' => 'Cancel',
				'url'   => '#cancel',
			],
			'delete' => [
				'label' => 'Delete',
				'url'   => '#delete',
			],
		],
	]
);

$table   = new Table( 'shop_subscriptions', Tables::get_table( 'shop_subscriptions' ) );
$columns = $table->get_columns();
$call    = static function ( string $method, mixed ...$args ) use ( $table ): mixed {
	$reflection = new ReflectionMethod( $table, $method );

	return $reflection->invoke( $table, ...$args );
};
$primary = (string) $call( 'get_primary_column_name' );

$head = '';

foreach ( $columns as $key => $label ) {
	$head .= 'cb' === $key
		? '<td id="cb" class="manage-column column-cb check-column"><input type="checkbox"></td>'
		: sprintf(
			'<th scope="col" id="%1$s" class="manage-column column-%1$s%2$s">%3$s</th>',
			$key,
			$key === $primary ? ' column-primary' : '',
			$label
		);
}

$rows = '';

foreach ( [ 11, 10 ] as $id ) {
	$item = (object) [
		'id'           => $id,
		'buyer'        => 'Charlotte Montgomery-Fairweather',
		'email'        => 'charlotte.montgomery-fairweather.longaddress@an-extremely-long-domain-name.example.test',
		'product'      => 'Drum & Bass Chords — Monthly, the complete collection of every chord, loop and one-shot',
		'status'       => 'active',
		'charged'      => '£35.99 / month',
		'licence'      => 'SC-7F3A9C2E4B1D8F6A0C5E7B9D2F4A6C8E',
		'country'      => 'United Kingdom of Great Britain and Northern Ireland',
		'notes'        => $long( 'renewal', 12 ),
		'date_created' => '2026/05/11',
	];

	$cells = '';

	foreach ( array_keys( $columns ) as $key ) {
		if ( 'cb' === $key ) {
			$cells .= '<th scope="row" class="check-column"><input type="checkbox"></th>';

			continue;
		}

		$value   = (string) $table->column_default( $item, $key );
		$actions = (string) $call( 'handle_row_actions', $item, $key, $primary );
		$cells  .= sprintf(
			'<%1$s%7$s class="%2$s column-%2$s%3$s" data-colname="%4$s">%5$s%6$s</%1$s>',
			$key === $primary ? 'th' : 'td',
			$key,
			$key === $primary ? ' has-row-actions column-primary' : '',
			esc_attr( wp_strip_all_tags( (string) $columns[ $key ] ) ),
			$value,
			$actions,
			$key === $primary ? ' scope="row"' : ''
		);
	}

	$rows .= '<tr id="row-' . $id . '">' . $cells . '</tr>';
}

echo wp_json_encode(
	[
		'classes' => implode( ' ', $call( 'get_table_classes' ) ),
		'primary' => $primary,
		'head'    => '<tr>' . $head . '</tr>',
		'rows'    => $rows,
	]
);
