<?php
/**
 * Reorder tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Editing\Reorder;
use SugarCommerce\Tables\Editing\Routes;
use SugarCommerce\Tables\Table;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * Rows somebody puts in order themselves.
 *
 * Worth pinning: the controls appear only while the list shows the order
 * they change -- a row dragged in a list sorted by name has been put nowhere
 * that means anything -- each row is numbered by its place, and the save is
 * checked (nonce, capability) before anything is handed the ids.
 */
final class ReorderTest extends TestCase {

	/**
	 * What the save callable was handed.
	 *
	 * @var array<int, array<int>>
	 */
	private array $saved = [];

	/**
	 * A clean request between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET        = [];
		$_POST       = [];
		$_REQUEST    = [];
		$this->saved = [];

		Reorder::reset( 'demo' );
	}

	/**
	 * A collections table that reorders.
	 *
	 * @param array<string, mixed> $reorder Extra reorder settings.
	 * @param mixed                $save    What the save answers.
	 *
	 * @return void
	 */
	private function register( array $reorder = [], mixed $save = true ): void {
		Tables::register(
			'demo',
			[
				'capability' => 'edit_things',
				'per_page'   => 3,
				'orderby'    => 'sort_order',
				'order'      => 'asc',
				'filters'    => [ 'colour' => [ 'options' => [ 'red' => 'Red' ] ] ],
				'columns'    => [
					'name'       => 'Name',
					'sort_order' => 'Position',
				],
				'reorder'    => array_merge(
					[
						'column' => 'sort_order',
						'save'   => function ( array $ids ) use ( $save ) {
							$this->saved[] = $ids;

							return $save;
						},
					],
					$reorder
				),
				'callbacks'  => [ 'get_items' => static fn() => [] ],
			]
		);
	}

	/**
	 * The position cells of a few rows, drawn as a page draws them.
	 *
	 * @param int $count How many rows.
	 *
	 * @return string[]
	 */
	private function cells( int $count = 2 ): array {
		$table = new Table( 'demo', Tables::get_table( 'demo' ) );
		$cells = [];

		for ( $i = 1; $i <= $count; $i++ ) {
			$cells[] = $table->column_default( (object) [
				'id' => 10 + $i,
				'name' => 'Row ' . $i,
				'sort_order' => 0,
			], 'sort_order' );
		}

		return $cells;
	}

	/**
	 * In the chosen order, a row has its handle, its place and two buttons,
	 * each named for the row.
	 */
	public function test_a_row_in_order_can_be_moved(): void {
		$this->register();

		[ $first, $second ] = $this->cells();

		$this->assertStringContainsString( '<span class="sugarcommerce-reorder" data-id="11">', $first );
		$this->assertStringContainsString( 'aria-label="Move “Row 1”. Drag, or use the up and down arrow keys."', $first );
		$this->assertStringContainsString( '<span class="sugarcommerce-reorder__number">1</span>', $first );
		$this->assertStringContainsString( 'data-step="-1" aria-label="Move “Row 1” up"', $first );
		$this->assertStringContainsString( 'data-step="1" aria-label="Move “Row 1” down"', $first );
		$this->assertStringContainsString( '<span class="sugarcommerce-reorder__number">2</span>', $second );
	}

	/**
	 * A second page is numbered on from the first.
	 */
	public function test_a_second_page_numbers_on(): void {
		$this->register();

		$_GET['paged'] = '2';

		$this->assertStringContainsString( '<span class="sugarcommerce-reorder__number">4</span>', $this->cells( 1 )[0] );
	}

	/**
	 * Sorted by another column, searched, or narrowed: no controls.
	 */
	public function test_no_controls_out_of_order(): void {
		$cases = [
			'sorted by name'       => [ 'orderby' => 'name' ],
			'sorted the other way' => [
				'orderby' => 'sort_order',
				'order' => 'desc',
			],
			'searched'             => [ 's' => 'drum' ],
			'in a status'          => [ 'status' => 'draft' ],
			'filtered'             => [ 'colour' => 'red' ],
		];

		foreach ( $cases as $case => $get ) {
			$this->register();
			Reorder::reset( 'demo' );

			$_GET = $get;

			$this->assertStringNotContainsString( 'sugarcommerce-reorder__handle', $this->cells( 1 )[0], $case );
		}
	}

	/**
	 * A table whose own default sort is another column is out of order
	 * until somebody sorts by the reorder's: newest first is not the
	 * order anybody chose.
	 */
	public function test_a_default_sort_by_another_column_is_out_of_order(): void {
		$this->register();

		$config            = Tables::get_table( 'demo' );
		$config['orderby'] = 'date_created';
		$config['order']   = 'desc';

		$this->assertFalse( Reorder::in_order( $config, Reorder::config( $config ) ?? [] ) );

		$_GET = [
			'orderby' => 'sort_order',
			'order'   => 'asc',
		];

		$this->assertTrue( Reorder::in_order( $config, Reorder::config( $config ) ?? [] ) );
	}

	/**
	 * With no default sort, or its own going up, the list is in order as
	 * it opens.
	 */
	public function test_a_default_sort_by_its_own_column_is_in_order(): void {
		$this->register();

		$this->assertStringContainsString( 'sugarcommerce-reorder__handle', $this->cells( 1 )[0] );

		$config            = Tables::get_table( 'demo' );
		$config['orderby'] = '';

		$this->assertTrue( Reorder::in_order( $config, Reorder::config( $config ) ?? [] ) );

		$config['orderby'] = 'sort_order';
		$config['order']   = 'desc';

		$this->assertFalse( Reorder::in_order( $config, Reorder::config( $config ) ?? [] ) );
	}

	/**
	 * Sorted by its own column going up is the order itself.
	 */
	public function test_sorted_by_its_own_column_is_in_order(): void {
		$this->register();

		$_GET = [
			'orderby' => 'sort_order',
			'order' => 'asc',
		];

		$this->assertStringContainsString( 'sugarcommerce-reorder__handle', $this->cells( 1 )[0] );
	}

	/**
	 * A reorder can allow moving within a filter.
	 */
	public function test_a_reorder_can_allow_a_filter(): void {
		$this->register( [ 'filtered' => true ] );

		$_GET = [ 'colour' => 'red' ];

		$this->assertStringContainsString( 'sugarcommerce-reorder__handle', $this->cells( 1 )[0] );
	}

	/**
	 * Out of order, the place comes from `position` when the table can say
	 * it, and is left blank when it cannot.
	 */
	public function test_out_of_order_the_place_needs_a_position(): void {
		$this->register();

		$_GET = [ 'orderby' => 'name' ];

		$this->assertStringContainsString( 'aria-hidden="true"', $this->cells( 1 )[0] );
		$this->assertStringNotContainsString( 'sugarcommerce-reorder__number', $this->cells( 1 )[0] );

		$this->register( [ 'position' => static fn( $row ): int => 7 ] );
		Reorder::reset( 'demo' );

		$this->assertSame( '<span class="sugarcommerce-reorder__number">7</span>', $this->cells( 1 )[0] );
	}

	/**
	 * Somebody who may not reorder sees the place and nothing to move it by.
	 */
	public function test_a_viewer_sees_only_the_place(): void {
		$this->register();

		$GLOBALS['rt_caps'] = [ 'read' ];

		$this->assertSame( '<span class="sugarcommerce-reorder__number">1</span>', $this->cells( 1 )[0] );
	}

	/**
	 * The save is handed the ids, as integers, once each, in order.
	 */
	public function test_the_save_is_handed_the_ids(): void {
		$this->register();

		$answer = Reorder::save( 'demo', [ '7', '3', 'x', '3', '9' ] );

		$this->assertTrue( $answer['ok'] );
		$this->assertSame( [ [ 7, 3, 9 ] ], $this->saved );
		$this->assertSame( [ 'order' => [ 7, 3, 9 ] ], $answer['data'] );
		$this->assertContains( 'sugarcommerce_table_reordered_demo', $GLOBALS['rt_fired'] ?? [] );
	}

	/**
	 * Without the capability nothing is handed anything.
	 */
	public function test_the_save_checks_the_capability(): void {
		$this->register();

		$GLOBALS['rt_caps'] = [ 'read' ];

		$this->assertSame( 403, Reorder::save( 'demo', [ 1, 2 ] )['status'] );
		$this->assertSame( [], $this->saved );
	}

	/**
	 * Nothing to save, or a save that did not work, is a failure.
	 */
	public function test_a_failed_save_is_a_failure(): void {
		$this->register( [], false );

		$this->assertSame( 400, Reorder::save( 'demo', [] )['status'] );
		$this->assertSame( 500, Reorder::save( 'demo', [ 1, 2 ] )['status'] );
	}

	/**
	 * The order is saved through the table's own REST route: ids typed and
	 * bounded, the reorder capability asked, no admin-ajax action left.
	 */
	public function test_the_order_is_saved_through_a_rest_route(): void {
		$this->register();
		Routes::register_routes();

		$route = $GLOBALS['rt_routes']['sugarcommerce-tables/v1/demo/reorder'] ?? null;

		$this->assertNotNull( $route );
		$this->assertSame( 'POST', $route['methods'] );
		$this->assertSame( 'array', $route['args']['ids']['type'] );
		$this->assertTrue( $route['args']['ids']['required'] );
		$this->assertSame( 1, $route['args']['ids']['minItems'] );
		$this->assertSame( [ 'type' => 'integer', 'minimum' => 1 ], $route['args']['ids']['items'] );
		$this->assertSame( [], preg_grep( '/^wp_ajax_/', array_keys( $GLOBALS['rt_actions'] ?? [] ) ) );

		$this->assertTrue( ( $route['permission_callback'] )() );

		$GLOBALS['rt_caps'] = [ 'read' ];
		$this->assertFalse( ( $route['permission_callback'] )() );
	}

	/**
	 * The route saves what it is sent, and a request with nothing in order
	 * is refused.
	 */
	public function test_the_route_saves_and_refuses_nothing(): void {
		$this->register();

		$request = new WP_REST_Request( 'POST', '/' );
		$request->set_param( 'ids', [ 2, 1 ] );

		$answer = Routes::reorder( 'demo', $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $answer );
		$this->assertSame( [ 'order' => [ 2, 1 ] ], $answer->get_data() );
		$this->assertSame( [ [ 2, 1 ] ], $this->saved );

		$request->set_param( 'ids', [ 'x', 0 ] );
		$refused = Routes::reorder( 'demo', $request );

		$this->assertInstanceOf( \WP_Error::class, $refused );
		$this->assertSame( 400, $refused->get_error_data()['status'] );
		$this->assertSame( [ [ 2, 1 ] ], $this->saved );
	}

	/**
	 * Each table has its own route.
	 */
	public function test_each_table_has_its_own_route(): void {
		$this->register();

		$this->assertSame( 'sugarcommerce-tables/v1/demo/reorder', Routes::path( 'demo', 'reorder' ) );
		$this->assertSame( 'sugarcommerce-tables/v1/other/reorder', Routes::path( 'other', 'reorder' ) );
	}

	/**
	 * A table without a save callable does not reorder.
	 */
	public function test_without_a_save_there_is_no_reorder(): void {
		$this->assertNull( Reorder::config( [ 'reorder' => [ 'column' => 'sort_order' ] ] ) );
		$this->assertNull( Reorder::config( [ 'reorder' => [] ] ) );
	}

	/**
	 * A screen whose table reorders loads the built script -- its own drag,
	 * not jQuery UI's sortable or touch punch -- and hands it the table's
	 * REST route and the two sentences. What the script
	 * does with them is tests/js/reorder-*.test.js.
	 */
	public function test_the_screen_loads_the_built_script(): void {
		$this->register();
		$_GET['page'] = 'demo';

		Reorder::enqueue();

		[ $src, $deps ] = $GLOBALS['rt_scripts']['sugarcommerce-tables-reorder'] ?? [ '', [] ];

		$this->assertStringEndsWith( '/assets/build/reorder.js', (string) $src );
		$this->assertContains( 'wp-a11y', $deps );
		$this->assertSame( [], preg_grep( '/^jquery/', $deps ) );

		// In this build's entry of the registry, never a fixed page global.
		$data = rt_script_config( 'sugarcommerce-tables-reorder' );

		$this->assertArrayNotHasKey( 'sugarcommerce-tables-reorder', $GLOBALS['rt_localized'] ?? [] );
		$this->assertSame( 'sugarcommerce-tables/v1/demo/reorder', $data['path'] );
		$this->assertSame( [ 'build', 'path', 'i18n' ], array_keys( $data ) );
		$this->assertSame( 'sugarcommerce-tables', $data['build'] );
		$this->assertSame( [ 'moved', 'failed' ], array_keys( $data['i18n'] ) );
	}
}
