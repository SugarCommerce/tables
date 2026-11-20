<?php
/**
 * Column footer tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Table;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SugarCommerce\Tables\Utils\Runtime;

/**
 * A column's figure for the whole view, in the bottom navigation.
 *
 * What is worth pinning is that it is the whole view: the same rows the list
 * pages through, whichever page this is, however many to a page and in
 * whatever order -- and that it is asked for once, and not at all for a view
 * with nothing in it.
 */
final class FooterTest extends TestCase {

	/**
	 * What each footer callback was handed, call by call.
	 *
	 * @var array<int, array{0: array<string, mixed>, 1: int}>
	 */
	private array $asked = [];

	/**
	 * A clean request between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET        = [];
		$_REQUEST    = [];
		$this->asked = [];
	}

	/**
	 * A prepared table whose `total` column has a footer.
	 *
	 * @param int                  $total  How many rows the view has.
	 * @param array<string, mixed> $config Anything else.
	 *
	 * @return Table
	 */
	private function table( int $total = 114, array $config = [] ): Table {
		$config = array_merge(
			[
				'per_page'  => 20,
				'columns'   => [
					'name'  => 'Name',
					'total' => [
						'label'  => 'Total',
						'footer' => function ( array $args, int $count ): string {
							$this->asked[] = [ $args, $count ];

							return '<strong>&pound;1,222.33</strong> <small>for all ' . $count . ' orders</small>';
						},
					],
					'date'  => 'Date',
				],
				'sortable'  => [ 'name', 'total' ],
				'filters'   => [
					'country' => [ 'label' => 'All countries', 'options' => [ 'GB' => 'UK', 'FR' => 'France' ] ],
				],
				'callbacks' => [
					'get_items'  => static fn( array $args ) => ! empty( $args['count'] ) ? $total : [ [ 'id' => 1, 'name' => 'One' ] ],
					'get_counts' => static fn(): array => [ 'total' => $total, 'complete' => $total ],
				],
			],
			$config
		);

		Tables::register( 'demo', $config );

		$table = new Table( 'demo', Tables::get_table( 'demo' ) );
		$table->prepare_items();

		return $table;
	}

	/**
	 * The footer row, as the table prints it.
	 *
	 * @param Table $table The table.
	 *
	 * @return string
	 */
	private function footer_row( Table $table ): string {
		ob_start();
		$table->print_column_headers( false );

		return (string) ob_get_clean();
	}

	/**
	 * The bottom navigation's extra part.
	 *
	 * @param Table $table The table.
	 *
	 * @return string
	 */
	private function bottom( Table $table ): string {
		$method = new ReflectionMethod( $table, 'extra_tablenav' );

		ob_start();
		$method->invoke( $table, 'bottom' );

		return (string) ob_get_clean();
	}

	/**
	 * It is handed the view -- search, status, dropdowns -- and nothing
	 * about the page.
	 */
	public function test_the_footer_is_handed_the_view_and_not_the_page(): void {
		$_GET['s']       = 'rossi';
		$_GET['status']  = 'complete';
		$_GET['country'] = 'GB';

		$this->table()->get_footers();

		$this->assertCount( 1, $this->asked );
		$this->assertSame(
			[
				'search'  => 'rossi',
				'status'  => 'complete',
				'country' => 'GB',
			],
			$this->asked[0][0]
		);
		$this->assertSame( 114, $this->asked[0][1], 'How many rows the view has, not the page.' );
	}

	/**
	 * Page two, fifty to a page, sorted by total: the same arguments as
	 * page one, so the same figure.
	 *
	 * Revert view_args() to the page's own arguments and this fails: the
	 * figure would be the page's total, which changes as you page.
	 */
	public function test_paging_and_sorting_do_not_change_what_it_is_handed(): void {
		$_GET['status'] = 'complete';

		$this->table()->get_footers();

		$_GET['paged']   = '2';
		$_GET['orderby'] = 'total';
		$_GET['order']   = 'asc';

		$this->table( 114, [ 'per_page' => 50 ] )->get_footers();

		$this->assertCount( 2, $this->asked );
		$this->assertSame( $this->asked[0], $this->asked[1] );
		$this->assertArrayNotHasKey( 'number', $this->asked[1][0] );
		$this->assertArrayNotHasKey( 'offset', $this->asked[1][0] );
		$this->assertArrayNotHasKey( 'orderby', $this->asked[1][0] );
	}

	/**
	 * The table's query-args filter reaches it, as it reaches the rows.
	 */
	public function test_the_query_args_filter_reaches_it(): void {
		add_filter( 'sugarcommerce_table_query_args_demo', static fn( array $args ): array => $args + [ 'is_test' => 1 ] );

		$this->table()->get_footers();

		$this->assertSame( 1, $this->asked[0][0]['is_test'] ?? null );
	}

	/**
	 * Asked once, however often it is drawn.
	 */
	public function test_it_is_asked_once_however_often_it_is_drawn(): void {
		$table = $this->table();

		$this->bottom( $table );
		$this->footer_row( $table );
		$this->bottom( $table );

		$this->assertCount( 1, $this->asked );
	}

	/**
	 * An empty view is not asked about, and shows nothing.
	 */
	public function test_an_empty_view_is_not_asked(): void {
		$table = $this->table( 0 );

		$this->assertSame( [], $table->get_footers() );
		$this->assertSame( [], $this->asked );
		$this->assertStringContainsString( '>Total</th>', $this->footer_row( $table ) );
		$this->assertSame( '', $this->bottom( $table ) );
	}

	/**
	 * The figure is in the bottom navigation, after the column's name, and
	 * the table's footer row is core's repeated heading, untouched.
	 *
	 * Put it back in the footer cell and this fails.
	 */
	public function test_the_figure_is_in_the_bottom_navigation_not_the_footer_row(): void {
		$table  = $this->table();
		$bottom = $this->bottom( $table );
		$row    = $this->footer_row( $table );

		$this->assertStringStartsWith( '<div class="alignleft sugarcommerce-table-totals">', $bottom );
		$this->assertStringContainsString(
			'<span class="sugarcommerce-table-total sugarcommerce-table-total-total"><span class="sugarcommerce-table-total-label">Total</span> <strong>&pound;1,222.33</strong> <small>for all 114 orders</small></span>',
			$bottom
		);

		$this->assertStringContainsString( '>Total</th>', $row );
		$this->assertStringNotContainsString( '1,222.33', $row );
		$this->assertStringNotContainsString( 'sugarcommerce-table-footer', $row );

		// The heading row keeps its heading too.
		ob_start();
		$table->print_column_headers();
		$heading = (string) ob_get_clean();

		$this->assertStringContainsString( '>Total</th>', $heading );
		$this->assertStringNotContainsString( '1,222.33', $heading );
	}

	/**
	 * Only below the table: the top navigation is the filters'.
	 */
	public function test_the_top_navigation_does_not_carry_it(): void {
		$table  = $this->table();
		$method = new ReflectionMethod( $table, 'extra_tablenav' );

		ob_start();
		$method->invoke( $table, 'top' );
		$top = (string) ob_get_clean();

		$this->assertStringNotContainsString( '1,222.33', $top );
	}

	/**
	 * A column switched off in Screen Options still has its figure shown.
	 */
	public function test_a_hidden_column_still_has_its_figure_below(): void {
		$table = $this->table( 114, [ 'hidden_columns' => [ 'total' ] ] );

		$this->assertStringContainsString( '<strong>&pound;1,222.33</strong>', $this->bottom( $table ) );
		$this->assertStringContainsString( "class='manage-column column-total hidden'", $this->footer_row( $table ) );
	}

	/**
	 * Saying nothing leaves the heading where it was.
	 */
	public function test_an_empty_answer_leaves_the_heading(): void {
		$table = $this->table(
			114,
			[
				'columns' => [
					'name'  => 'Name',
					'total' => [ 'label' => 'Total', 'footer' => static fn(): string => '' ],
				],
			]
		);

		$this->assertSame( [], $table->get_footers() );
		$this->assertSame( '', $this->bottom( $table ) );
	}

	/**
	 * A table with no footers prints core's row untouched.
	 */
	public function test_a_table_without_footers_is_untouched(): void {
		$table = $this->table( 114, [ 'columns' => [ 'name' => 'Name', 'total' => 'Total' ] ] );

		$this->assertStringContainsString( '>Total</th>', $this->footer_row( $table ) );
		$this->assertSame( '', $this->bottom( $table ) );
		$this->assertSame( [], $this->asked );
	}

	/**
	 * No per-column rule: the figure is shown below whatever the column.
	 */
	public function test_no_per_column_rule_for_a_footer(): void {
		$method = new ReflectionMethod( Tables::class, 'output_dynamic_styles' );
		$method->invoke(
			null,
			[
				'columns' => [
					'name'  => 'Name',
					'total' => [ 'label' => 'Total', 'footer' => static fn(): string => 'x' ],
				],
			]
		);

		$css = (string) ( $GLOBALS['rt_inline_styles'][ Runtime::handle() ] ?? '' );

		$this->assertStringNotContainsString( 'sugarcommerce-table-total', $css );
	}
}
