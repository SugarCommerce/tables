<?php
/**
 * Hidden column tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\HiddenColumns;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Table;
use PHPUnit\Framework\TestCase;

/**
 * A deferred column that is hidden is not worked out.
 */
final class HiddenColumnsTest extends TestCase {

	/**
	 * How many times the expensive column was worked out.
	 *
	 * @var int
	 */
	private int $asked = 0;

	/**
	 * A clean slate, on the table's screen.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$this->asked = 0;

		$GLOBALS['rt_screen'] = new class() {
			/**
			 * The screen's id.
			 *
			 * @var string
			 */
			public string $id = 'shop_page_shop-customers';

			/**
			 * No screen options of its own.
			 *
			 * @param mixed ...$args Ignored.
			 *
			 * @return null
			 */
			public function get_option( ...$args ) {
				return null;
			}
		};
	}

	/**
	 * A table whose `last_order` column asks another table.
	 *
	 * @param mixed $deferred The column's `deferred`.
	 *
	 * @return Table
	 */
	private function table( $deferred = true ): Table {
		Tables::register(
			'shop_customers',
			[
				'columns'        => [
					'name'       => 'Name',
					'last_order' => [
						'label'    => 'Last Order',
						'deferred' => $deferred,
						'callback' => function (): string {
							++$this->asked;

							return 'Trap Kit';
						},
					],
				],
				'hidden_columns' => [ 'last_order' ],
			]
		);

		return new Table( 'shop_customers', Tables::get_table( 'shop_customers' ) );
	}

	/**
	 * Hidden as the table declares, a deferred column holds the mark and
	 * asks nothing.
	 */
	public function test_a_hidden_deferred_column_asks_nothing(): void {
		$cell = $this->table()->column_default( (object) [ 'id' => 1 ], 'last_order' );

		$this->assertSame( '<span class="sugarcommerce-column-deferred" aria-hidden="true">&hellip;</span>', $cell );
		$this->assertSame( 0, $this->asked );
	}

	/**
	 * Shown -- this person switched it on -- it is worked out.
	 */
	public function test_a_shown_deferred_column_is_worked_out(): void {
		$GLOBALS['rt_user_options'] = [ 'manageshop_page_shop-customerscolumnshidden' => [ 'email' ] ];

		$this->assertSame( 'Trap Kit', $this->table()->column_default( (object) [ 'id' => 1 ], 'last_order' ) );
		$this->assertSame( 1, $this->asked );
		$this->assertFalse( HiddenColumns::is_hidden( 'shop_customers', 'last_order' ) );
		$this->assertTrue( HiddenColumns::is_hidden( 'shop_customers', 'email' ) );
	}

	/**
	 * A column that is not deferred is drawn hidden or not, as core does;
	 * one that is `manual` decides for itself.
	 */
	public function test_only_a_deferred_column_is_skipped(): void {
		$this->assertSame( 'Trap Kit', $this->table( false )->column_default( (object) [ 'id' => 1 ], 'last_order' ) );
		$this->assertSame( 'Trap Kit', $this->table( 'manual' )->column_default( (object) [ 'id' => 1 ], 'last_order' ) );
		$this->assertSame( 2, $this->asked );
	}

	/**
	 * The table's own hidden-columns filter is the declared answer, and a
	 * filter of its own can say a column is read off the page regardless.
	 */
	public function test_the_filters_are_asked(): void {
		$this->table();

		$this->assertTrue( HiddenColumns::is_hidden( 'shop_customers', 'last_order' ) );

		add_filter( 'sugarcommerce_table_hidden_columns_shop_customers', static fn(): array => [ 'name' ] );

		$this->assertFalse( HiddenColumns::is_hidden( 'shop_customers', 'last_order' ) );
		$this->assertTrue( HiddenColumns::is_hidden( 'shop_customers', 'name' ) );

		add_filter( 'sugarcommerce_table_column_hidden_shop_customers', static fn( bool $hidden, string $column ): bool => 'name' === $column ? false : $hidden, 10, 2 );

		$this->assertFalse( HiddenColumns::is_hidden( 'shop_customers', 'name' ) );
	}

	/**
	 * The reload script is wanted only where a column is deferred.
	 */
	public function test_any_finds_a_deferred_column(): void {
		$this->assertFalse( HiddenColumns::any( [ 'columns' => [ 'name' => 'Name', 'x' => [ 'label' => 'X' ] ] ] ) );
		$this->assertTrue( HiddenColumns::any( [ 'columns' => [ 'x' => [ 'label' => 'X', 'deferred' => 'manual' ] ] ] ) );
	}
}
