<?php
/**
 * Seen marker SQL tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Seen\Rows;
use SugarCommerce\Tables\Seen\Seen;
use PHPUnit\Framework\TestCase;

/**
 * The two questions asked of a table: every name through `%i`, every value a
 * placeholder, and the same clauses `is_new()` holds a row to.
 */
final class SeenQueryTest extends TestCase {

	/**
	 * A database that records what it was asked and answers with a number.
	 */
	protected function setUp(): void {
		rt_reset_globals();
		Seen::reset( true );

		$GLOBALS['rt_user_id'] = 1;

		$GLOBALS['wpdb'] = new class() {

			/**
			 * The site's table prefix.
			 *
			 * @var string
			 */
			public string $prefix = 'wp_';

			/**
			 * What was asked, as it was prepared.
			 *
			 * @var array<int, array{0: string, 1: array}>
			 */
			public array $asked = [];

			/**
			 * Keep the query and its values together.
			 *
			 * @param string $sql    The query.
			 * @param mixed  ...$args Its values.
			 *
			 * @return array{0: string, 1: array}
			 */
			public function prepare( $sql, ...$args ) {
				return [ $sql, is_array( $args[0] ?? null ) ? $args[0] : $args ];
			}

			/**
			 * Remember it, and answer.
			 *
			 * @param array{0: string, 1: array} $query The prepared query.
			 *
			 * @return string
			 */
			public function get_var( $query ) {
				$this->asked[] = $query;

				return '4';
			}
		};
	}

	/**
	 * Forget the database.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
	}

	/**
	 * Register a list with a marker on a table of its own.
	 *
	 * @return void
	 */
	private function register(): void {
		Seen::register(
			'shop_orders',
			[
				'seen' => [
					'table'   => 'shop_orders_table',
					'columns' => [ 'id' => 'order_id', 'created' => 'placed_at' ],
					'scopes'  => [
						'test' => [ 'is_test' => 1 ],
						'live' => [ 'is_test' => 0 ],
					],
					'scope'   => static fn(): string => 'test',
					'except'  => [ 'status' => [ 'refunded', 'failed' ] ],
					'window'  => 0,
				],
			]
		);
	}

	/**
	 * The count: above the marker, in the set, inside the window, less their own and the exceptions.
	 */
	public function test_the_count_is_one_prepared_query(): void {
		$this->register();

		update_user_meta( 1, 'shop_orders_seen_test', 12 );
		update_user_meta( 1, 'shop_orders_own_test', [ 14, 15 ] );

		$this->assertSame( 4, Seen::count( 'shop_orders' ) );

		[ $sql, $values ] = $GLOBALS['wpdb']->asked[0];

		$this->assertSame(
			'SELECT COUNT(*) FROM %i WHERE %i > %d AND %i = %d AND %i NOT IN ( %d, %d ) AND %i NOT IN ( %s, %s )',
			$sql
		);
		$this->assertSame( [ 'wp_shop_orders_table', 'order_id', 12, 'is_test', 1, 'order_id', 14, 15, 'status', 'refunded', 'failed' ], $values );
	}

	/**
	 * With a window, the created column holds it.
	 */
	public function test_the_window_is_a_clause_of_its_own(): void {
		$this->register();
		Seen::register( 'shop_orders', [ 'seen' => array_merge( Seen::get( 'shop_orders' )['raw']['seen'], [ 'window' => 7, 'except' => [] ] ) ] );

		Seen::count( 'shop_orders' );

		[ $sql, $values ] = $GLOBALS['wpdb']->asked[0];

		$this->assertSame( 'SELECT COUNT(*) FROM %i WHERE %i > %d AND %i = %d AND %i >= %s', $sql );
		$this->assertSame( 'placed_at', $values[5] );
		$this->assertSame( Rows::cutoff( 'shop_orders' ), $values[6] );
	}

	/**
	 * The newest row, in the set.
	 */
	public function test_the_newest_row(): void {
		$this->register();

		$this->assertSame( 4, Rows::newest( 'shop_orders', 'test' ) );
		$this->assertSame( [ 'SELECT MAX(%i) FROM %i WHERE %i = %d', [ 'order_id', 'wp_shop_orders_table', 'is_test', 1 ] ], $GLOBALS['wpdb']->asked[0] );

		$this->assertSame( 4, Rows::newest( 'shop_orders', '' ) );
		$this->assertSame( [ 'SELECT MAX(%i) FROM %i', [ 'order_id', 'wp_shop_orders_table' ] ], $GLOBALS['wpdb']->asked[1] );
	}

	/**
	 * The table defaults to the list's own id.
	 */
	public function test_the_table_defaults_to_the_id(): void {
		Seen::register( 'shop_customers', [ 'seen' => [ 'label' => 'New customer' ] ] );

		Rows::newest( 'shop_customers', '' );

		$this->assertSame( [ 'SELECT MAX(%i) FROM %i', [ 'id', 'wp_shop_customers' ] ], $GLOBALS['wpdb']->asked[0] );
		$this->assertSame( 'date_created', Seen::get( 'shop_customers' )['created'] );
	}
}
