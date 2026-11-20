<?php
/**
 * Column widths, the primary column and a row's summary on a phone.
 *
 * @package SugarCommerce\Tables\Tests
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SugarCommerce\Tables\Support\ColumnLayout;
use SugarCommerce\Tables\Table;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Utils\Runtime;

/**
 * A list laid out by what is in it.
 *
 * What went wrong before: every column the same width, so a name was cut
 * to "Charlotte …" beside a Sales column four times the width of its
 * numbers; hidden row actions wrapping to three lines and keeping that
 * band empty under every title; and on a phone a column of order numbers,
 * because the primary column was whatever came first.
 */
final class ColumnLayoutTest extends TestCase {

	/**
	 * Forget every table and style.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET     = [];
		$_REQUEST = [];
	}

	/**
	 * Register an orders table and hand back its list table.
	 *
	 * @param array<string, mixed> $config Overrides.
	 *
	 * @return Table
	 */
	private function orders( array $config = [] ): Table {
		Tables::register(
			'shop_orders',
			array_merge(
				[
					'menu_slug'   => 'shop-orders',
					'labels'      => [ 'singular' => 'order', 'plural' => 'orders' ],
					'columns'     => [
						'id'       => 'ID',
						'customer' => 'Customer',
						'status'   => 'Status',
						'total'    => 'Total',
						'notes'    => 'Notes',
					],
					'row_actions' => [],
				],
				$config
			)
		);

		return new Table( 'shop_orders', Tables::get_table( 'shop_orders' ) );
	}

	/**
	 * The primary column is not a bare id while there is anything else.
	 */
	public function test_the_primary_column_is_not_a_bare_id(): void {
		$this->assertSame( 'customer', $this->primary_of( [] ) );
		$this->assertSame( 'id', $this->primary_of( [ 'columns' => [ 'id' => 'ID' ] ] ) );
		$this->assertSame( 'id', $this->primary_of( [ 'primary_column' => 'id' ] ) );
		$this->assertSame( 'total', $this->primary_of( [ 'columns' => [ 'id' => 'ID', 'total' => [ 'label' => 'Total', 'primary' => true ] ] ] ) );
	}

	/**
	 * The primary column a registration ends up with.
	 *
	 * @param array<string, mixed> $config Overrides.
	 *
	 * @return string
	 */
	private function primary_of( array $config ): string {
		$this->orders( $config );

		return (string) Tables::get_table( 'shop_orders' )['primary_column'];
	}

	/**
	 * A figure's column is narrow; a column of words, the primary one, one
	 * with a width or a title of its own, is not; a column can say.
	 */
	public function test_a_figure_is_compact(): void {
		$config = [ 'primary_column' => 'customer' ];

		$this->assertTrue( ColumnLayout::is_compact( 'total', 'Total', $config ) );
		$this->assertTrue( ColumnLayout::is_compact( 'status', 'Status', $config ) );
		$this->assertTrue( ColumnLayout::is_compact( 'date_created', 'Date', $config ) );
		$this->assertTrue( ColumnLayout::is_compact( 'sale_count', 'Sales', $config ) );
		$this->assertTrue( ColumnLayout::is_compact( 'featured', [ 'label' => '★', 'toggle' => [] ], $config ) );
		$this->assertTrue( ColumnLayout::is_compact( 'notes', [ 'label' => 'Notes', 'compact' => true ], $config ) );

		$this->assertFalse( ColumnLayout::is_compact( 'notes', 'Notes', $config ) );
		$this->assertFalse( ColumnLayout::is_compact( 'customer', 'Customer', $config ) );
		$this->assertFalse( ColumnLayout::is_compact( 'total', [ 'label' => 'Total', 'compact' => false ], $config ) );
		$this->assertFalse( ColumnLayout::is_compact( 'total', [ 'label' => 'Total', 'width' => '10%' ], $config ) );
		$this->assertFalse( ColumnLayout::is_compact( 'total', [ 'label' => 'Total', 'title' => 'name' ], $config ) );
		$this->assertFalse( ColumnLayout::is_compact( 'total', [ 'label' => 'Total', 'type' => false ], $config ) );
		$this->assertFalse( ColumnLayout::is_compact( 'total', 'Total', [ 'primary_column' => 'total' ] ) );
		$this->assertTrue( ColumnLayout::is_compact( 'notes', [ 'label' => 'Notes', 'type' => 'price' ], $config ) );
	}

	/**
	 * The rule is written for a compact column and not for the others, and
	 * the table is no longer core's `fixed` one, where it would mean 1%.
	 */
	public function test_a_compact_column_is_as_wide_as_what_is_in_it(): void {
		$table  = $this->orders();
		$method = new ReflectionMethod( Tables::class, 'output_dynamic_styles' );

		$method->invoke( null, Tables::get_table( 'shop_orders' ) );

		$css = (string) ( $GLOBALS['rt_inline_styles'][ Runtime::handle() ] ?? '' );

		$this->assertStringContainsString( '.wp-list-table .column-total { width: 1%; white-space: nowrap; }', $css );
		$this->assertStringContainsString( '.wp-list-table .column-status { width: 1%; white-space: nowrap; }', $css );
		$this->assertStringNotContainsString( 'column-customer', $css );
		$this->assertStringNotContainsString( 'column-notes', $css );

		$classes = new ReflectionMethod( $table, 'get_table_classes' );
		$classes = (array) $classes->invoke( $table );

		$this->assertNotContains( 'fixed', $classes );
		$this->assertContains( 'widefat', $classes );
	}

	/**
	 * The phone's summary: a status and an amount by default, never the
	 * primary column, never a column switched off, and what a table names.
	 */
	public function test_the_summary_is_a_status_and_an_amount(): void {
		$this->orders();
		$config = (array) Tables::get_table( 'shop_orders' );

		$this->assertSame( [ 'status', 'total' ], ColumnLayout::summary( $config ) );
		$this->assertSame( [ 'total' ], ColumnLayout::summary( $config, [ 'status' ] ) );
		$this->assertSame( [], ColumnLayout::summary( [ 'summary' => false ] + $config ) );
		$this->assertSame( [ 'notes' ], ColumnLayout::summary( [ 'summary' => [ 'notes', 'gone', 'customer' ] ] + $config ) );
		$this->assertSame( [ 'status' ], ColumnLayout::summary( [ 'primary_column' => 'total' ] + $config ) );
	}

	/**
	 * Drawn in the primary cell, before the row actions; a fact with
	 * nothing to say is left out rather than shown as a dash.
	 */
	public function test_the_summary_is_drawn_under_the_title(): void {
		$table   = $this->orders();
		$actions = new ReflectionMethod( $table, 'handle_row_actions' );
		$row     = (object) [
			'id'       => 7,
			'customer' => 'Elena',
			'status'   => 'active',
			'total'    => '',
		];

		$html = (string) $actions->invoke( $table, $row, 'customer', 'customer' );

		$this->assertStringStartsWith( '<div class="sugarcommerce-row-summary"><span class="sugarcommerce-row-summary-part"><span class="wp-status-badge wp-status-badge--success">', $html );
		$this->assertSame( 1, substr_count( $html, 'sugarcommerce-row-summary-part' ) );
		$this->assertSame( '', (string) $actions->invoke( $table, $row, 'notes', 'customer' ) );
	}

	/**
	 * The stylesheet's half: hidden actions take no room, the summary is a
	 * phone's, and there the columns before the primary one fold away too.
	 */
	public function test_the_stylesheet_folds_what_it_should(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/admin-tables.css' );

		$this->assertMatchesRegularExpression( '/\.admin-table:not\(\.mobile\) \.wp-list-table tr:not\(:hover\):not\(:focus-within\) \.row-actions \{\s*height: 0;/', $css );
		$this->assertMatchesRegularExpression( '/\n\.sugarcommerce-row-summary \{\s*display: none;/', $css );
		$this->assertMatchesRegularExpression( '/max-width: 782px\) \{\s*\.sugarcommerce-row-summary \{\s*display: flex;/', $css );
		$this->assertStringContainsString( 'td:not(.check-column):not(.column-primary) {', $css );
		$this->assertMatchesRegularExpression( '/td:not\(\.check-column\):not\(\.column-primary\) \{\s*padding: 3px 8px 3px 35%;/', $css );
		$this->assertMatchesRegularExpression( '/\.wp-list-table th\.sortable a,\s*\.wp-list-table th\.sorted a \{\s*display: flex;/', $css );
		$this->assertMatchesRegularExpression( '/\.sugarcommerce-item-title,\s*\.sugarcommerce-item-meta \{[^}]*-webkit-line-clamp: 1;[^}]*overflow-wrap: anywhere;/', $css );
		$this->assertMatchesRegularExpression( '/\.wp-list-table td \{\s*overflow-wrap: anywhere;/', $css );
	}
}
