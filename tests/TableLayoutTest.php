<?php
/**
 * A table's layout: core's.
 *
 * @package SugarCommerce\Tables\Tests
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SugarCommerce\Tables\Table;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Utils\Runtime;

/**
 * Core's list-table layout and column sizing, untouched.
 *
 * A release once dropped core's `fixed` layout, sized figure columns to
 * their contents, skipped a bare `id` for the primary column and repeated
 * a status and an amount under the title on a phone. Row actions moved
 * under the customer, a long product pushed the table past its box and
 * rows grew. All of it was taken back out; these hold it out.
 * tests/browser/layout.spec.mjs checks the same in a browser.
 */
final class TableLayoutTest extends TestCase {

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
					'row_actions' => [
						'edit' => [
							'label' => 'Edit',
							'url'   => '#edit',
						],
					],
				],
				$config
			)
		);

		return new Table( 'shop_orders', Tables::get_table( 'shop_orders' ) );
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
	 * The first column after the tick box carries the row actions, an `id`
	 * as much as any other, unless the table names one.
	 */
	public function test_the_primary_column_is_the_first_column(): void {
		$this->assertSame( 'id', $this->primary_of( [] ) );
		$this->assertSame( 'customer', $this->primary_of( [ 'primary_column' => 'customer' ] ) );
		$this->assertSame( 'total', $this->primary_of( [ 'columns' => [ 'id' => 'ID', 'total' => [ 'label' => 'Total', 'primary' => true ] ] ] ) );
	}

	/**
	 * Core's `fixed` table, as on Posts: the columns share its width.
	 */
	public function test_the_table_is_core_s_fixed_layout(): void {
		$table   = $this->orders();
		$classes = (array) ( new ReflectionMethod( $table, 'get_table_classes' ) )->invoke( $table );

		$this->assertContains( 'fixed', $classes );
		$this->assertContains( 'widefat', $classes );
	}

	/**
	 * Core's list view whatever was last chosen on Posts: only a list
	 * view's rows show their actions on keyboard focus (common.js).
	 */
	public function test_the_table_is_always_core_s_list_view(): void {
		$table   = $this->orders();
		$classes = new ReflectionMethod( $table, 'get_table_classes' );

		$GLOBALS['stub_list_mode'] = 'excerpt';

		try {
			$excerpt = (array) $classes->invoke( $table );
		} finally {
			unset( $GLOBALS['stub_list_mode'] );
		}

		$list = (array) $classes->invoke( $table );

		foreach ( [ $excerpt, $list ] as $names ) {
			$this->assertSame( 1, count( array_keys( $names, 'table-view-list', true ) ) );
			$this->assertNotContains( 'table-view-excerpt', $names );
			$this->assertContains( 'fixed', $names );
			$this->assertSame( array_values( $names ), $names );
		}
	}

	/**
	 * Nobody's column is given a width it did not ask for: a column's own
	 * `width` is the only one written.
	 */
	public function test_only_a_column_s_own_width_is_written(): void {
		$this->orders( [ 'columns' => [ 'id' => 'ID', 'total' => 'Total', 'status' => [ 'label' => 'Status', 'width' => '10%' ] ] ] );

		( new ReflectionMethod( Tables::class, 'output_dynamic_styles' ) )->invoke( null, Tables::get_table( 'shop_orders' ) );

		$css = (string) ( $GLOBALS['rt_inline_styles'][ Runtime::handle() ] ?? '' );

		$this->assertStringContainsString( '.wp-list-table .column-status { width: 10%; }', $css );
		$this->assertStringNotContainsString( 'column-total', $css );
		$this->assertStringNotContainsString( 'column-id', $css );
		$this->assertStringNotContainsString( 'nowrap', $css );
	}

	/**
	 * The primary cell is the title and the row actions, nothing between.
	 */
	public function test_nothing_is_drawn_under_the_title_but_the_actions(): void {
		$table   = $this->orders();
		$actions = new ReflectionMethod( $table, 'handle_row_actions' );
		$row     = (object) [
			'id'       => 7,
			'customer' => 'Elena',
			'status'   => 'active',
			'total'    => '1200',
		];

		$html = (string) $actions->invoke( $table, $row, 'id', 'id' );

		$this->assertStringStartsWith( '<div class="row-actions">', $html );
		$this->assertStringNotContainsString( 'summary', $html );
		$this->assertSame( '', (string) $actions->invoke( $table, $row, 'customer', 'id' ) );
	}

	/**
	 * The stylesheet sizes no column and folds none of core's: a list is
	 * laid out as Posts is.
	 */
	public function test_the_stylesheet_leaves_the_layout_to_core(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/admin-tables.css' );

		$this->assertStringNotContainsString( 'table-layout', $css );
		$this->assertStringNotContainsString( 'row-summary', $css );
		$this->assertDoesNotMatchRegularExpression( '/\.wp-list-table td \{/', $css );
		$this->assertDoesNotMatchRegularExpression( '/th\.sort(able|ed) a[^{]*\{\s*display: flex/', $css );
		$this->assertStringNotContainsString( 'line-clamp', $css );
		$this->assertStringNotContainsString( ':not(.column-primary)', $css );
		$this->assertMatchesRegularExpression( '/\.sugarcommerce-item-title \{[^}]*text-overflow: ellipsis;[^}]*white-space: nowrap;/', $css );
		$this->assertMatchesRegularExpression( '/\.sugarcommerce-item-meta \{[^}]*text-overflow: ellipsis;[^}]*white-space: nowrap;/', $css );
	}
}
