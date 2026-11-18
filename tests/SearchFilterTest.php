<?php
/**
 * Search filter tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\SearchFilter;
use SugarCommerce\Tables\Table;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * A filter whose choices are too many to list.
 *
 * What is worth pinning: the control searches the source it names, shows
 * what is chosen by name, reaches the rows; the chip names it and its
 * cross takes only it off; and the status links and their counts keep it.
 */
final class SearchFilterTest extends TestCase {

	/**
	 * What the counts callback was handed.
	 *
	 * @var array<int, array>
	 */
	private array $counted = [];

	/**
	 * What get_items was handed.
	 *
	 * @var array<int, array>
	 */
	private array $asked = [];

	/**
	 * A clean request between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET          = [];
		$_REQUEST      = [];
		$this->counted = [];
		$this->asked   = [];
	}

	/**
	 * A table with a product search filter and a price carried unseen.
	 *
	 * @return Table
	 */
	private function table(): Table {
		Tables::register(
			'demo',
			[
				'menu_slug' => 'demo',
				'columns'   => [ 'name' => 'Name' ],
				'views'     => [
					'all'      => 'All',
					'complete' => 'Complete',
				],
				'filters'   => [
					'product_id' => [
						'type'           => 'search',
						'label'          => 'All Products',
						'source'         => 'demo_products',
						'chip'           => 'Product: %s',
						'clears'         => [ 'price_id' ],
						'label_callback' => static fn( string $term, array $ids ): array => array_map(
							static fn( $id ): array => [
								'id'   => (int) $id,
								'text' => 12 === (int) $id ? 'Synthwave <Stems>' : 'Other',
							],
							$ids
						),
					],
					'price_id'   => [
						'type'  => 'search',
						'chip'  => 'Price: %s',
					],
					'country'    => [ 'label' => 'All countries', 'options' => [ 'GB' => 'UK' ] ],
				],
				'callbacks' => [
					'get_items'  => function ( array $args ) {
						$this->asked[] = $args;

						return ! empty( $args['count'] ) ? 2 : [ [ 'id' => 1, 'name' => 'One' ] ];
					},
					'get_counts' => function ( array $narrowed = [] ): array {
						$this->counted[] = $narrowed;

						return [ 'total' => 2, 'complete' => 1 ];
					},
				],
			]
		);

		return new Table( 'demo', Tables::get_table( 'demo' ) );
	}

	/**
	 * The filter bar's markup.
	 *
	 * @param Table $table The table.
	 *
	 * @return string
	 */
	private function bar( Table $table ): string {
		$method = new ReflectionMethod( $table, 'extra_tablenav' );

		ob_start();
		$method->invoke( $table, 'top' );

		return (string) ob_get_clean();
	}

	/**
	 * The control is the kit's searchable select, naming its source, with
	 * a label for a screen reader and what is chosen shown by name.
	 */
	public function test_the_control_searches_its_source_and_shows_the_choice(): void {
		$_GET['product_id'] = '12';

		$bar = $this->bar( $this->table() );

		$this->assertStringContainsString( '<label class="screen-reader-text" for="filter-by-product_id">All Products</label>', $bar );
		$this->assertStringContainsString( 'class="field-kit__relational sugarcommerce-table-search-filter"', $bar );
		$this->assertStringContainsString( 'data-search-source="demo_products"', $bar );

		// Kept on a phone, where core hides the filter bar.
		$this->assertStringContainsString( 'class="alignleft actions sugarcommerce-table-filters"', $bar );
		$this->assertMatchesRegularExpression( '#data-search-endpoint="https://example.test/wp-json/[a-z0-9-]+/v1/search"#', $bar );
		$this->assertStringContainsString( '<option value="">All Products</option>', $bar );
		$this->assertStringContainsString( '<option value="12" selected>Synthwave &lt;Stems&gt;</option>', $bar );
	}

	/**
	 * With nothing chosen it holds only its empty choice.
	 */
	public function test_nothing_chosen_is_one_empty_option(): void {
		$bar = $this->bar( $this->table() );

		$this->assertStringNotContainsString( '" selected>', $bar );
		$this->assertStringNotContainsString( 'name="price_id"', $bar );
	}

	/**
	 * A filter with no source is carried, not drawn: a hidden input while
	 * it is set, so the form keeps it.
	 */
	public function test_a_filter_with_no_source_is_carried_unseen(): void {
		$_GET['price_id'] = '40';

		$bar = $this->bar( $this->table() );

		$this->assertStringContainsString( '<input type="hidden" name="price_id" value="40">', $bar );
	}

	/**
	 * The chosen value reaches the rows, and the count the pager uses.
	 */
	public function test_the_value_reaches_the_query(): void {
		$_GET['product_id'] = '12';

		$table = $this->table();
		$table->prepare_items();

		$this->assertSame( '12', $this->asked[0]['product_id'] ?? null );
		$this->assertSame( '12', $this->asked[1]['product_id'] ?? null );
		$this->assertTrue( $this->asked[1]['count'] ?? false );
	}

	/**
	 * A chip for each search filter that is on, named and escaped; its
	 * cross keeps the status, the search and the other filters, and drops
	 * the page and whatever the filter clears.
	 */
	public function test_the_chips_name_the_narrowing_and_take_one_off(): void {
		$_GET = [
			'page'       => 'demo',
			'status'     => 'complete',
			's'          => 'kira',
			'country'    => 'GB',
			'product_id' => '12',
			'price_id'   => '40',
			'paged'      => '3',
		];

		$this->table();

		$chips = SearchFilter::chips( Tables::get_table( 'demo' ) );

		$this->assertStringContainsString( '<span class="sugarcommerce-table-chip-name">Product: Synthwave &lt;Stems&gt;</span>', $chips );
		$this->assertStringContainsString( '<span class="sugarcommerce-table-chip-name">Price: 40</span>', $chips );
		$this->assertStringContainsString( 'aria-label="Remove filter: Product: Synthwave &lt;Stems&gt;"', $chips );

		preg_match_all( '/href="([^"]+)"/', $chips, $links );

		parse_str( (string) parse_url( html_entity_decode( $links[1][0] ), PHP_URL_QUERY ), $product_off );
		parse_str( (string) parse_url( html_entity_decode( $links[1][1] ), PHP_URL_QUERY ), $price_off );

		$this->assertSame(
			[
				'page'    => 'demo',
				'status'  => 'complete',
				's'       => 'kira',
				'country' => 'GB',
			],
			$product_off,
			'Taking the product off takes its price with it, and the page.'
		);

		$this->assertSame( '12', $price_off['product_id'] );
		$this->assertArrayNotHasKey( 'price_id', $price_off );
	}

	/**
	 * Nothing on, no chips.
	 */
	public function test_no_chips_when_nothing_is_on(): void {
		$_GET['country'] = 'GB';

		$this->table();

		$this->assertSame( '', SearchFilter::chips( Tables::get_table( 'demo' ) ) );
	}

	/**
	 * The status links keep a search filter, and their counts are asked for
	 * within it. A dropdown filter is still left behind, as core's are.
	 */
	public function test_the_views_keep_the_narrowing(): void {
		$_GET = [
			'page'       => 'demo',
			'product_id' => '12',
			'country'    => 'GB',
		];

		$views = $this->table()->get_views();

		$this->assertStringContainsString( 'product_id=12', $views['complete'] );
		$this->assertStringNotContainsString( 'country=', $views['complete'] );
		$this->assertSame( [ 'product_id' => '12' ], $this->counted[0] );
	}

	/**
	 * Registering a table with a searchable filter makes the search route
	 * exist on the REST request the select makes.
	 */
	public function test_registering_one_boots_the_search_route(): void {
		( new \ReflectionProperty( SearchFilter::class, 'booted' ) )->setValue( null, false );

		$this->table();

		$this->assertNotEmpty( $GLOBALS['rt_actions']['rest_api_init'] ?? [] );
	}
}
