<?php
/**
 * What the seen-marker tests share.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Seen\Seen;
use PHPUnit\Framework\TestCase;

/**
 * A list of orders kept in memory, with a marker on it.
 *
 * Rows are kept apart for test and live, as a shop's are, and the count and
 * the newest row are answered from memory through the table's `count` and
 * `newest` callables -- by the same rules the SQL is made from, so a test
 * that the rows and the count agree is a test of the contract both keep.
 */
abstract class SeenTestCase extends TestCase {

	/**
	 * The rows.
	 *
	 * @var array<int, object>
	 */
	protected array $rows = [];

	/**
	 * How many times the count was worked out rather than remembered.
	 *
	 * @var int
	 */
	protected int $asked = 0;

	/**
	 * Whether the site is showing its test rows.
	 *
	 * @var bool
	 */
	protected bool $test_mode = true;

	/**
	 * A clean request, with somebody signed in.
	 */
	protected function setUp(): void {
		rt_reset_globals();
		Seen::reset( true );

		$_GET     = [];
		$_REQUEST = [];

		$this->rows      = [];
		$this->asked     = 0;
		$this->test_mode = true;

		$GLOBALS['rt_user_id'] = 1;
	}

	/**
	 * Register the orders table.
	 *
	 * @param array<string, mixed> $seen  More about its marker.
	 * @param array<string, mixed> $table More about the table.
	 *
	 * @return void
	 */
	protected function orders( array $seen = [], array $table = [] ): void {
		Tables::register(
			'shop_orders',
			array_merge(
				[
					'menu_slug'   => 'shop-orders',
					'parent_slug' => 'shop',
					'labels'      => [ 'singular' => 'order', 'plural' => 'orders' ],
					'columns'     => [ 'id' => 'Order', 'status' => 'Status' ],
					'callbacks'   => [ 'get_items' => fn() => array_values( $this->rows ) ],
					'seen'        => array_merge(
						[
							'label'   => 'New order',
							'scopes'  => [
								'test' => [ 'is_test' => 1 ],
								'live' => [ 'is_test' => 0 ],
							],
							'scope'   => fn(): string => $this->test_mode ? 'test' : 'live',
							'except'  => [ 'status' => [ 'refunded', 'failed', 'cancelled' ] ],
							'changed' => [ 'shop_add_order', 'shop_delete_order' ],
							'count'   => fn( array $query ): int => $this->count_rows( $query ),
							'newest'  => fn( array $where ): int => $this->newest_row( $where ),
						],
						$seen
					),
				],
				$table
			)
		);
	}

	/**
	 * Add an order, as the shop would: and say so.
	 *
	 * @param array<string, mixed> $columns Anything about it.
	 * @param int                  $age     How many days ago it was made.
	 *
	 * @return object
	 */
	protected function order( array $columns = [], int $age = 0 ): object {
		$id  = count( $this->rows ) + 1;
		$row = (object) array_merge(
			[
				'id'           => $id,
				'status'       => 'complete',
				'is_test'      => 1,
				'date_created' => gmdate( 'Y-m-d H:i:s', time() - $age * DAY_IN_SECONDS ),
			],
			$columns
		);

		$this->rows[ $id ] = $row;

		do_action( 'shop_add_order', $id );

		return $row;
	}

	/**
	 * Draw the list as the page does: before the rows, the rows, after them.
	 *
	 * @param callable|null $during Anything that happens while the rows are drawn.
	 *
	 * @return void
	 */
	protected function visit( ?callable $during = null ): void {
		do_action( 'sugarcommerce_before_render_table_shop_orders', [] );

		if ( null !== $during ) {
			$during();
		}

		do_action( 'sugarcommerce_after_render_table_shop_orders', [] );
	}

	/**
	 * The count, as a table's `count` callable answers it.
	 *
	 * @param array<string, mixed> $query What to count.
	 *
	 * @return int
	 */
	private function count_rows( array $query ): int {
		++$this->asked;

		$found = array_filter(
			$this->rows,
			static function ( object $row ) use ( $query ): bool {
				foreach ( $query['where'] as $column => $value ) {
					if ( (string) $row->{$column} !== (string) $value ) {
						return false;
					}
				}

				foreach ( $query['except'] as $column => $not ) {
					if ( in_array( (string) $row->{$column}, $not, true ) ) {
						return false;
					}
				}

				return $row->id > $query['after']
					&& ( '' === $query['since'] || $row->date_created >= $query['since'] )
					&& ! in_array( $row->id, $query['not_in'], true );
			}
		);

		return count( $found );
	}

	/**
	 * The newest row in a set.
	 *
	 * @param array<string, scalar> $where The set.
	 *
	 * @return int
	 */
	private function newest_row( array $where ): int {
		$ids = [ 0 ];

		foreach ( $this->rows as $row ) {
			if ( [] === array_filter( $where, static fn( $value, $column ): bool => (string) $row->{$column} !== (string) $value, ARRAY_FILTER_USE_BOTH ) ) {
				$ids[] = $row->id;
			}
		}

		return max( $ids );
	}
}
