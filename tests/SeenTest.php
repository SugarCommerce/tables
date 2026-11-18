<?php
/**
 * Seen marker tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Seen\Markers;
use SugarCommerce\Tables\Seen\Rows;
use SugarCommerce\Tables\Seen\Seen;

/**
 * "New" is new to one person since they last opened the list, in the set the
 * site is showing, no older than the window.
 */
final class SeenTest extends SeenTestCase {

	/**
	 * Each person has their own count, and opening the list clears only theirs.
	 */
	public function test_the_count_is_each_persons_own(): void {
		$this->orders();
		$this->order();
		$this->order();

		$this->assertSame( 2, Seen::count( 'shop_orders', 1 ) );
		$this->assertSame( 2, Seen::count( 'shop_orders', 2 ) );

		$this->visit();

		$this->assertSame( 0, Seen::count( 'shop_orders', 1 ) );
		$this->assertSame( 2, Seen::count( 'shop_orders', 2 ) );
	}

	/**
	 * Only the set the site is showing is counted, and each set has its own marker.
	 */
	public function test_only_the_set_being_shown_is_counted(): void {
		$this->orders();
		$this->order();
		$this->order( [ 'is_test' => 0 ] );
		$this->order( [ 'is_test' => 0 ] );

		$this->assertSame( 1, Seen::count( 'shop_orders' ) );

		$this->visit();

		$this->test_mode = false;

		$this->assertSame( 2, Seen::count( 'shop_orders' ) );
		$this->assertSame( 'shop_orders_seen_test', Markers::key( 'shop_orders', 'seen', 'test' ) );
		$this->assertSame( 1, Markers::seen( 'shop_orders', 1, 'test' ) );
		$this->assertSame( 0, Markers::seen( 'shop_orders', 1, 'live' ) );
	}

	/**
	 * A row of the other set is never new, on either side.
	 */
	public function test_a_row_of_the_other_set_is_never_new(): void {
		$this->orders();

		$real = $this->order( [ 'is_test' => '0' ] );
		$test = $this->order( [ 'is_test' => '1' ] );

		$this->assertFalse( Seen::is_new( 'shop_orders', $real ) );
		$this->assertTrue( Seen::is_new( 'shop_orders', $test ) );

		$this->test_mode = false;

		$this->assertTrue( Seen::is_new( 'shop_orders', $real ) );
		$this->assertFalse( Seen::is_new( 'shop_orders', $test ) );
	}

	/**
	 * Somebody who never opened the list is told about the window, and no more.
	 */
	public function test_somebody_new_is_told_about_the_window_and_no_more(): void {
		$this->orders();

		$old    = $this->order( [], 30 );
		$recent = $this->order( [], 2 );

		$this->assertSame( 1, Seen::count( 'shop_orders' ) );
		$this->assertFalse( Seen::is_new( 'shop_orders', $old ) );
		$this->assertTrue( Seen::is_new( 'shop_orders', $recent ) );

		// A row that does not say when it was made is not held to it.
		$this->assertTrue( Seen::is_new( 'shop_orders', (object) [ 'id' => 9, 'is_test' => 1 ] ) );
	}

	/**
	 * The window is seven days, a number, or a callable; nought is no limit.
	 */
	public function test_the_window(): void {
		$this->orders();
		$this->assertSame( 7, Rows::window( 'shop_orders' ) );

		$this->orders( [ 'window' => static fn(): int => 30 ] );
		$this->assertSame( 30, Rows::window( 'shop_orders' ) );

		$this->orders( [ 'window' => 0 ] );
		$this->assertSame( 0, Rows::window( 'shop_orders' ) );
		$this->assertSame( '', Rows::cutoff( 'shop_orders' ) );

		$this->order( [], 400 );
		$this->assertSame( 1, Seen::count( 'shop_orders' ) );

		$this->orders( [ 'window' => -4 ] );
		$this->assertSame( 0, Rows::window( 'shop_orders' ) );
	}

	/**
	 * The rows a list never calls news are neither lit nor counted.
	 */
	public function test_the_exceptions_are_never_new(): void {
		$this->orders();

		$refund = $this->order( [ 'status' => 'refunded' ] );
		$sale   = $this->order();

		$this->assertFalse( Seen::is_new( 'shop_orders', $refund ) );
		$this->assertTrue( Seen::is_new( 'shop_orders', $sale ) );
		$this->assertSame( 1, Seen::count( 'shop_orders' ) );
	}

	/**
	 * A row made by hand is not news to whoever made it, and is to everybody else.
	 */
	public function test_a_row_made_by_hand_is_not_news_to_its_maker(): void {
		$this->orders(
			[
				'by_hand' => [
					// A checkout fires this too, with a session: only the
					// one with none was made by hand. It names the set.
					'shop_order_completed' => static fn( $id, $session = null ) => null === $session ? [ (int) $id, 'live' ] : 0,
					'shop_order_added_by_hand',
				],
			]
		);

		$mine  = $this->order();
		$bought = $this->order( [ 'is_test' => 0 ] );
		$typed = $this->order( [ 'is_test' => 0 ] );

		do_action( 'shop_order_added_by_hand', $mine->id );
		do_action( 'shop_order_completed', $bought->id, 'cs_test_session' );
		do_action( 'shop_order_completed', $typed->id );

		$this->assertSame( [ $mine->id ], Markers::own( 'shop_orders', 1, 'test' ) );
		$this->assertSame( [ $typed->id ], Markers::own( 'shop_orders', 1, 'live' ) );

		$this->assertFalse( Seen::is_new( 'shop_orders', $mine ) );
		$this->assertSame( 0, Seen::count( 'shop_orders', 1 ) );
		$this->assertSame( 1, Seen::count( 'shop_orders', 2 ) );

		// Behind the marker, they are forgotten.
		$this->visit();
		$this->assertSame( [], Markers::own( 'shop_orders', 1, 'test' ) );

		// And nobody signed in makes nothing by hand.
		$GLOBALS['rt_user_id'] = 0;
		do_action( 'shop_order_added_by_hand', 7 );
		$this->assertSame( [], Markers::own( 'shop_orders', 0, 'test' ) );
	}

	/**
	 * Only the last fifty made by hand are kept.
	 */
	public function test_only_the_last_fifty_made_by_hand_are_kept(): void {
		$this->orders();

		for ( $id = 1; $id <= 60; $id++ ) {
			Markers::add_own( 'shop_orders', 1, 'test', $id );
		}

		Markers::add_own( 'shop_orders', 1, 'test', 60 );

		$this->assertSame( range( 11, 60 ), Markers::own( 'shop_orders', 1, 'test' ) );
	}

	/**
	 * Somebody who may not open the list is given no count, and costs nothing.
	 */
	public function test_it_is_for_those_who_can_see_the_list(): void {
		$this->orders( [], [ 'capability' => 'manage_shop' ] );
		$this->order();

		$GLOBALS['rt_user_caps'] = [ 2 => [ 'read' ], 1 => [ 'manage_shop' ] ];

		$this->assertSame( 0, Seen::count( 'shop_orders', 2 ) );
		$this->assertSame( 0, $this->asked );
		$this->assertSame( 1, Seen::count( 'shop_orders', 1 ) );

		add_filter( 'sugarcommerce_table_seen_capability_shop_orders', static fn(): string => 'read' );

		$this->assertSame( 1, Seen::count( 'shop_orders', 2 ) );
		$this->assertSame( 0, Seen::count( 'shop_orders', 0 ) );
	}

	/**
	 * Nothing is asked before the list's own tables are there.
	 */
	public function test_nothing_is_asked_until_it_is_ready(): void {
		$ready = false;

		$this->orders( [ 'ready' => static function () use ( &$ready ): bool { return $ready; } ] );
		$this->order();

		$this->assertSame( 0, Seen::count( 'shop_orders' ) );
		$this->assertSame( 0, Rows::newest( 'shop_orders', 'test' ) );
		$this->assertSame( 0, $this->asked );

		$ready = true;

		$this->assertSame( 1, Seen::count( 'shop_orders' ) );
	}

	/**
	 * Worked out once, then remembered until a row arrives, goes, or is seen.
	 */
	public function test_the_count_is_kept_until_a_row_arrives(): void {
		$this->orders();
		$this->order();

		$this->assertSame( 1, Seen::count( 'shop_orders' ) );
		$this->assertSame( 1, Seen::count( 'shop_orders' ) );
		$this->assertSame( 1, $this->asked, 'The second count is remembered.' );

		// A new row: the kept count is not believed.
		$second = $this->order();
		$this->assertSame( 2, Seen::count( 'shop_orders' ) );

		// One deleted: nor then.
		unset( $this->rows[ $second->id ] );
		do_action( 'shop_delete_order', $second->id );
		$this->assertSame( 1, Seen::count( 'shop_orders' ) );

		// And the marker moving.
		Seen::mark_all( 'shop_orders' );
		$this->assertSame( 0, Seen::count( 'shop_orders' ) );
		$this->assertSame( 4, $this->asked );
	}

	/**
	 * A filter has the last word on the figure, and it is never below nought.
	 */
	public function test_the_count_is_filtered(): void {
		$this->orders();

		add_filter(
			'sugarcommerce_table_new_count_shop_orders',
			static fn( int $count, int $user, string $scope ): int => 'test' === $scope ? $count + 40 : -5
		);

		$this->assertSame( 40, Seen::count( 'shop_orders' ) );

		$this->test_mode = false;

		$this->assertSame( 0, Seen::count( 'shop_orders' ) );
	}

	/**
	 * The rows that were new are lit for the visit that sees them, and plain on the next.
	 */
	public function test_the_marker_moves_after_the_list_is_drawn(): void {
		$this->orders();

		$old = $this->order();

		Seen::mark_all( 'shop_orders' );

		$first  = $this->order();
		$second = $this->order();
		$late   = null;

		$this->visit(
			function () use ( $first, $second, $old, &$late ): void {
				$this->assertTrue( Seen::is_new( 'shop_orders', $first ) );
				$this->assertTrue( Seen::is_new( 'shop_orders', $second ) );
				$this->assertFalse( Seen::is_new( 'shop_orders', $old ) );
				$this->assertSame( $old->id, Markers::seen( 'shop_orders', 1, 'test' ), 'The marker moved before the rows were out.' );

				// A row that arrives while the page is being built is not seen by it.
				$late = $this->order();
			}
		);

		$this->assertSame( $second->id, Markers::seen( 'shop_orders', 1, 'test' ) );
		$this->assertSame( 1, Seen::count( 'shop_orders' ) );

		// A row redrawn on its own, after a quick edit, is a request the
		// list was not opened on: it comes back as it was.
		Seen::reset();

		$this->assertTrue( Seen::is_new( 'shop_orders', $first ) );
		$this->assertFalse( Seen::is_new( 'shop_orders', $old ) );

		// The next visit: what was new is plain, and what came late is new.
		$this->visit(
			function () use ( $first, &$late ): void {
				$this->assertFalse( Seen::is_new( 'shop_orders', $first ) );
				$this->assertTrue( Seen::is_new( 'shop_orders', $late ) );
			}
		);

		$this->assertSame( 0, Seen::count( 'shop_orders' ) );
	}

	/**
	 * The marker the page was drawn from is written only when it moves.
	 */
	public function test_shown_is_written_only_when_it_moves(): void {
		$this->orders();
		$this->order();

		$this->visit();
		$this->visit();

		$writes = array_count_values( $GLOBALS['rt_meta_writes'] ?? [] );

		// Nought, then one: the first visit's "shown" is nought, which is
		// what an empty key already reads as.
		$this->assertSame( 1, $writes['shop_orders_shown_test'] ?? 0 );
		$this->assertSame( 1, $writes['shop_orders_seen_test'] ?? 0 );
	}

	/**
	 * An older page finishing late cannot bring seen rows back.
	 */
	public function test_the_marker_only_moves_forwards(): void {
		$this->orders();

		$first  = $this->order();
		$second = $this->order();

		Markers::mark( 'shop_orders', 1, 'test', $second->id );
		Markers::mark( 'shop_orders', 1, 'test', $first->id );
		Markers::mark( 'shop_orders', 1, 'test', 0 );

		$this->assertSame( $second->id, Markers::seen( 'shop_orders', 1, 'test' ) );
		$this->assertSame( 0, Seen::count( 'shop_orders' ) );
	}

	/**
	 * A list with one set has one marker, named for the table.
	 */
	public function test_a_list_with_one_set(): void {
		$this->orders( [ 'scopes' => [], 'scope' => null ] );

		$row = $this->order( [ 'is_test' => 0 ] );

		$this->assertSame( '', Seen::scope( 'shop_orders' ) );
		$this->assertTrue( Seen::is_new( 'shop_orders', $row ) );

		$this->visit();

		$this->assertSame( $row->id, (int) get_user_meta( 1, 'shop_orders_seen', true ) );

		$this->orders( [ 'meta_prefix' => 'shop_list_' ] );
		$this->assertSame( 'shop_list_own_test', Markers::key( 'shop_orders', 'own', 'test' ) );
	}

	/**
	 * Hooked everywhere only to hear about rows, and those hooks read nothing.
	 */
	public function test_hearing_about_a_row_reads_nothing(): void {
		$this->orders( [ 'by_hand' => 'shop_order_added_by_hand', 'menu_count' => true ] );

		$hooked = [];

		foreach ( $GLOBALS['rt_actions'] as $hook => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback ) && is_string( $callback[0] ) && str_starts_with( $callback[0], 'SugarCommerce\\Tables\\Seen\\' ) ) {
					$hooked[ $hook ][] = $callback[1];
				}
			}
		}

		ksort( $hooked );

		$this->assertSame(
			[
				'admin_enqueue_scripts'                      => [ 'enqueue' ],
				'admin_menu'                                 => [ 'badge' ],
				'shop_add_order'                             => [ 'changed' ],
				'shop_delete_order'                          => [ 'changed' ],
				'shop_order_added_by_hand'                   => [ 'made_by_hand' ],
				'sugarcommerce_after_render_table_shop_orders'  => [ 'looked' ],
				'sugarcommerce_before_render_table_shop_orders' => [ 'opened' ],
			],
			$hooked
		);

		$this->order();
		do_action( 'shop_order_added_by_hand', 1 );

		$this->assertSame( 0, $this->asked );
		$this->assertSame( [ 'shop_orders_own_test' ], $GLOBALS['rt_meta_writes'] );
	}

	/**
	 * Registered before the table is -- on every request, so a webhook is
	 * heard -- and again with the table, which adds the menu it is in.
	 */
	public function test_registering_twice_adds_and_hooks_once(): void {
		Seen::register(
			'shop_orders',
			[
				'seen' => [
					'changed' => [ 'shop_add_order' ],
					'count'   => static fn(): int => 3,
				],
			]
		);

		$this->assertSame( '', Seen::get( 'shop_orders' )['menu_slug'] );

		$this->orders();

		$this->assertSame( 'shop-orders', Seen::get( 'shop_orders' )['menu_slug'] );
		$this->assertCount( 1, $GLOBALS['rt_actions']['shop_add_order'] );
		$this->assertCount( 1, $GLOBALS['rt_actions']['sugarcommerce_before_render_table_shop_orders'] );

		// Nothing to register: no seen and no highlights.
		Seen::register( 'shop_nothing', [ 'menu_slug' => 'x' ] );
		$this->assertNull( Seen::get( 'shop_nothing' ) );
	}

	/**
	 * Somebody who has seen everything a dashboard showed them.
	 */
	public function test_marking_a_list_seen(): void {
		$this->orders();
		$this->order();
		$this->order();

		Seen::mark_all( 'shop_orders', 2 );

		$this->assertSame( 2, Markers::seen( 'shop_orders', 2, 'test' ) );
		$this->assertSame( 0, Markers::seen( 'shop_orders', 1, 'test' ) );

		// A table with no marker has nothing to mark.
		Seen::mark_all( 'shop_other', 2 );
		$this->assertSame( 0, Markers::seen( 'shop_other', 2 ) );
	}
}
