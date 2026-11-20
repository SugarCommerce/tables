<?php
/**
 * BerlinDB source tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Data\Component;
use SugarCommerce\Tables\Sources\BerlinDB;
use SugarCommerce\Geo\Countries;
use SugarCommerce\Tables\Tests\Fixtures\FakeQuery;
use SugarCommerce\Tables\Tests\Fixtures\FakeRelatedQuery;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/berlindb-query.php';

/**
 * The library's request, turned into BerlinDB's arguments.
 *
 * Every rule here was a bug once, in a copy of this that lived in a plugin:
 * pages read from keys the library never sends, a search read from `s`,
 * counts that disagreed with the rows, a URL that could reach the query.
 * None of them throws. A wrong one is a list that looks like it worked.
 */
final class BerlinDBSourceTest extends TestCase {

	/**
	 * A small customers table.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		FakeQuery::seed(
			[
				[ 'id' => 1, 'name' => 'Anna Schmidt', 'email' => 'anna@example.test', 'country' => 'DE', 'status' => 'active', 'is_test' => 0, 'customer_id' => 7 ],
				[ 'id' => 2, 'name' => 'Bernd Vogel', 'email' => 'bernd@example.test', 'country' => 'DE', 'status' => 'disabled', 'is_test' => 0, 'customer_id' => 8 ],
				[ 'id' => 3, 'name' => 'Georgia Brown', 'email' => 'georgia@example.test', 'country' => 'FR', 'status' => 'active', 'is_test' => 0, 'customer_id' => 7 ],
				[ 'id' => 4, 'name' => 'Nino Beridze', 'email' => 'nino@example.test', 'country' => 'GE', 'status' => 'active', 'is_test' => 0, 'customer_id' => 0 ],
				[ 'id' => 5, 'name' => 'Test Person', 'email' => 'test@example.test', 'country' => 'DE', 'status' => 'active', 'is_test' => 1, 'customer_id' => 9 ],
			]
		);
		FakeRelatedQuery::seed( [] );
	}

	/**
	 * The callbacks, held to live rows and filterable by country.
	 *
	 * @param array<string, mixed> $options More options.
	 *
	 * @return array<string, callable>
	 */
	private function callbacks( array $options = [] ): array {
		return BerlinDB::callbacks(
			$options + [
				'query'    => FakeQuery::class,
				'statuses' => [ 'active', 'disabled' ],
				'filters'  => [ 'country' ],
				'scope'    => static fn(): array => [ 'is_test' => 0 ],
			]
		);
	}

	/**
	 * The names a request draws.
	 *
	 * @param array<string, mixed> $args What the library would pass.
	 *
	 * @return array<int, string>
	 */
	private function names( array $args ): array {
		return array_map(
			static fn( object $row ): string => (string) $row->name,
			$this->callbacks()['get_items']( $args + [ 'number' => 20, 'orderby' => 'name', 'order' => 'asc' ] )
		);
	}

	/**
	 * `number` and `offset` are what the library sends. Read as `per_page`
	 * and `paged`, every page was the first one.
	 */
	public function test_the_page_is_read_from_number_and_offset(): void {
		$query = BerlinDB::query_args( [ 'number' => 2, 'offset' => 4, 'per_page' => 50, 'paged' => 3, 'orderby' => 'name', 'order' => 'asc' ] );

		$this->assertSame( [ 'number' => 2, 'offset' => 4, 'orderby' => 'name', 'order' => 'ASC' ], $query );
		$this->assertSame( [ 'Bernd Vogel', 'Georgia Brown' ], $this->names( [ 'number' => 2, 'offset' => 1 ] ) );
	}

	/**
	 * `search` is the key the library sends; `s` is the request's.
	 */
	public function test_the_search_key_is_the_one_the_library_sends(): void {
		$this->assertCount( 4, $this->names( [ 's' => 'anna' ] ) );
		$this->assertSame( [ 'Anna Schmidt' ], $this->names( [ 'search' => 'schmidt' ] ) );
	}

	/**
	 * "all" is no status, and a status is a key.
	 */
	public function test_all_is_no_status(): void {
		$this->assertArrayNotHasKey( 'status', BerlinDB::query_args( [ 'status' => 'all' ] ) );
		$this->assertSame( 'active', BerlinDB::query_args( [ 'status' => 'Active!' ] )['status'] );
	}

	/**
	 * Only a declared filter reaches the query; a URL cannot ask for every
	 * row by naming `number` or anything else.
	 */
	public function test_only_a_declared_filter_reaches_the_query(): void {
		$query = BerlinDB::query_args( [ 'country' => 'DE', 'gateway' => 'x', 'fields' => 'ids' ], [ 'filters' => [ 'country' ] ] );

		$this->assertSame( 'DE', $query['country'] );
		$this->assertArrayNotHasKey( 'gateway', $query );
		$this->assertArrayNotHasKey( 'fields', $query );
		$this->assertSame( 999, BerlinDB::query_args( [ 'number' => 100000 ] )['number'] );
		$this->assertSame( 1, BerlinDB::query_args( [ 'number' => -4 ] )['number'] );
	}

	/**
	 * An id filter is renamed to its column, and a value that cannot be an
	 * id matches nothing rather than every row with no customer.
	 */
	public function test_an_id_filter_cannot_match_everything(): void {
		$options = [
			'filters'    => [ 'customer' ],
			'filter_ids' => [ 'customer' => 'customer_id' ],
		];

		$query = BerlinDB::query_args( [ 'customer' => '7' ], $options );
		$this->assertSame( 7, $query['customer_id'] );
		$this->assertArrayNotHasKey( 'customer', $query );

		$this->assertSame( [ 0 ], BerlinDB::query_args( [ 'customer' => 'abc' ], $options )['id__in'] );
		$this->assertSame( [ 0 ], BerlinDB::query_args( [ 'customer' => '0' ], $options )['id__in'] );
		$this->assertArrayNotHasKey( 'id__in', BerlinDB::query_args( [ 'customer' => '' ], $options ) );
	}

	/**
	 * The scope is last: nothing in the request steps outside it.
	 */
	public function test_the_scope_wins_over_the_request(): void {
		$query = BerlinDB::query_args( [ 'is_test' => 1 ], [ 'filters' => [ 'is_test' ], 'scope' => [ 'is_test' => 0 ] ] );

		$this->assertSame( 0, $query['is_test'] );
		$this->assertNotContains( 'Test Person', $this->names( [] ) );
	}

	/**
	 * Asked with `count`, the number of matches -- not the page.
	 */
	public function test_count_is_how_many_match(): void {
		$get = $this->callbacks()['get_items'];

		$this->assertSame( 2, $get( [ 'country' => 'DE', 'count' => true, 'number' => 0 ] ) );
		$this->assertSame( 4, $get( [ 'count' => true, 'number' => 1 ] ) );

		$last = end( FakeQuery::$log );
		$this->assertArrayNotHasKey( 'offset', $last );
		$this->assertArrayNotHasKey( 'orderby', $last );
	}

	/**
	 * The count agrees with the rows, or the pager offers pages that are
	 * not there.
	 */
	public function test_the_count_agrees_with_the_rows(): void {
		$callbacks = $this->callbacks();

		foreach ( [ [], [ 'search' => 'example' ], [ 'search' => 'germany' ], [ 'search' => 'nothing at all' ], [ 'status' => 'active' ] ] as $args ) {
			$this->assertSame( count( $callbacks['get_items']( $args ) ), $callbacks['get_total']( $args ), (string) wp_json_encode( $args ) );
		}
	}

	/**
	 * The status links' counts, within the scope and within a search
	 * filter that is on.
	 */
	public function test_the_counts_are_held_to_the_scope_and_the_narrowing(): void {
		$counts = $this->callbacks()['get_counts']();

		$this->assertSame( [ 'total' => 4, 'all' => 4, 'active' => 3, 'disabled' => 1 ], $counts );

		$narrowed = $this->callbacks()['get_counts']( [ 'country' => 'DE' ] );

		$this->assertSame( [ 'total' => 2, 'all' => 2, 'active' => 1, 'disabled' => 1 ], $narrowed );

		// The narrowing cannot step outside the scope either.
		$this->assertSame( 2, $this->callbacks()['get_counts']( [ 'country' => 'DE', 'is_test' => 1 ] )['total'] );
	}

	/**
	 * A country's name, its start or its code finds the rows from it, and
	 * a word that is a country and a name finds both.
	 */
	public function test_a_search_for_a_country_finds_its_rows(): void {
		foreach ( [ 'germany', 'Germ', 'de' ] as $term ) {
			$this->assertSame( [ 'Anna Schmidt', 'Bernd Vogel' ], $this->names( [ 'search' => $term ] ), $term );
			$this->assertSame( 2, $this->callbacks()['get_total']( [ 'search' => $term ] ), $term );
		}

		$this->assertSame( [ 'Georgia Brown', 'Nino Beridze' ], $this->names( [ 'search' => 'georgia' ] ) );
	}

	/**
	 * A word that is neither is the search it always was; a country nobody
	 * is in finds nothing, not everybody.
	 */
	public function test_a_term_that_is_no_country_is_an_ordinary_search(): void {
		$this->assertSame( [ 'Anna Schmidt' ], $this->names( [ 'search' => 'schmidt' ] ) );
		$this->assertSame( [], $this->names( [ 'search' => 'japan' ] ) );
		$this->assertSame( 0, $this->callbacks()['get_total']( [ 'search' => 'japan' ] ) );
	}

	/**
	 * The other filters still narrow it, and it pages like any other.
	 */
	public function test_the_country_search_keeps_to_the_filters_and_pages(): void {
		$this->assertSame( [ 'Anna Schmidt' ], $this->names( [ 'search' => 'germany', 'status' => 'active' ] ) );
		$this->assertSame( [ 'Georgia Brown' ], $this->names( [ 'search' => 'georgia', 'country' => 'FR' ] ) );
		$this->assertSame( [ 'Bernd Vogel' ], $this->names( [ 'search' => 'germany', 'number' => 1, 'offset' => 1 ] ) );
	}

	/**
	 * Off unless asked for, or unless the list filters by country.
	 */
	public function test_the_country_search_is_off_without_a_country(): void {
		$get = BerlinDB::callbacks( [ 'query' => FakeQuery::class ] )['get_items'];

		$this->assertSame( [], $get( [ 'search' => 'germany' ] ) );

		$get = BerlinDB::callbacks( [ 'query' => FakeQuery::class, 'filters' => [ 'country' ], 'country_search' => false ] )['get_items'];

		$this->assertSame( [], $get( [ 'search' => 'germany' ] ) );
	}

	/**
	 * A search finds rows another way too: by whose they are.
	 */
	public function test_a_search_can_be_widened_another_way(): void {
		$names = BerlinDB::callbacks(
			[
				'query'       => FakeQuery::class,
				'search_also' => [ 'customer_id__in' => static fn( string $term ): array => 'elena' === $term ? [ 8 ] : [] ],
			]
		)['get_items']( [ 'search' => 'elena' ] );

		$this->assertSame( [ 'Bernd Vogel' ], array_map( static fn( object $row ): string => $row->name, $names ) );
	}

	/**
	 * The query the rows are read with is there to be asked for, search
	 * widening and all, so a figure under a column can agree with them.
	 */
	public function test_the_widened_query_is_public(): void {
		$options = [
			'query'    => FakeQuery::class,
			'filters'  => [ 'country' ],
			'scope'    => static fn(): array => [ 'is_test' => 0 ],
		];

		$query = BerlinDB::query( [ 'search' => 'germany', 'number' => 5, 'status' => 'active' ], $options );

		$this->assertArrayNotHasKey( 'search', $query );
		$this->assertSame( [ 1 ], $query['id__in'] );
		$this->assertSame( 'active', $query['status'] );
		$this->assertSame( 0, $query['is_test'] );
		$this->assertSame( 5, $query['number'] );

		// The same rows get_items reads.
		$this->assertSame(
			array_map( static fn( object $row ): int => (int) $row->id, BerlinDB::callbacks( $options )['get_items']( [ 'search' => 'germany', 'number' => 5, 'status' => 'active' ] ) ),
			array_map( static fn( object $row ): int => (int) $row->id, ( new FakeQuery() )->query( $query ) )
		);

		// No search, nothing widened: query_args() as it was.
		$this->assertSame( BerlinDB::query_args( [ 'country' => 'FR' ], $options ), BerlinDB::query( [ 'country' => 'FR' ], $options ) );
	}

	/**
	 * A page's related rows are fetched once, for the page.
	 */
	public function test_a_page_is_primed(): void {
		$seen = [];
		$also = 0;

		$this->callbacks(
			[
				'prime' => [
					'customer_id' => FakeRelatedQuery::class,
					'country'     => static function ( array $ids ) use ( &$seen ): void {
						$seen = $ids;
					},
				],
				'also'  => static function ( array $rows ) use ( &$also ): void {
					$also = count( $rows );
				},
			]
		)['get_items']( [ 'number' => 20 ] );

		$this->assertSame(
			[
				[
					'id__in' => [ 7, 8 ],
					'number' => 0,
				],
			],
			FakeRelatedQuery::$log
		);
		$this->assertSame( 4, $also );

		// A count is not a page: nothing to prime.
		FakeRelatedQuery::$log = [];
		$this->callbacks( [ 'prime' => [ 'customer_id' => FakeRelatedQuery::class ] ] )['get_items']( [ 'count' => true ] );
		$this->assertSame( [], FakeRelatedQuery::$log );
	}

	/**
	 * The query class answers for one row, a delete and an update; a
	 * callable given in its place wins.
	 */
	public function test_the_query_class_supplies_the_rest(): void {
		$callbacks = $this->callbacks();

		$this->assertSame( 'Anna Schmidt', $callbacks['get_item']( 1 )->name );
		$this->assertTrue( $callbacks['update']( 1, [ 'status' => 'disabled' ] ) );
		$this->assertSame( 'disabled', FakeQuery::$rows[1]['status'] );
		$this->assertTrue( $callbacks['delete']( 2 ) );
		$this->assertFalse( $callbacks['delete']( 2 ) );

		$own = $this->callbacks( [ 'one' => static fn( int $id ): object => (object) [ 'id' => $id, 'name' => 'Mine' ] ] );

		$this->assertSame( 'Mine', $own['get_item']( 1 )->name );
	}

	/**
	 * A sugarcommerce/data component stands for its query class, as the
	 * list's own and as what a column points at.
	 */
	public function test_a_data_component_names_its_query_class(): void {
		$customers = new Component( 'customer', [ 'query' => FakeQuery::class ] );
		$related   = new Component( 'related', [ 'query' => FakeRelatedQuery::class ] );

		$callbacks = $this->callbacks(
			[
				'query' => $customers,
				'prime' => [ 'customer_id' => $related ],
			]
		);

		$this->assertSame( 'Anna Schmidt', $callbacks['get_item']( 1 )->name );
		$this->assertSame( 4, $callbacks['get_items']( [ 'count' => true ] ) );

		$callbacks['get_items']( [ 'number' => 20 ] );

		$this->assertSame(
			[
				[
					'id__in' => [ 7, 8 ],
					'number' => 0,
				],
			],
			FakeRelatedQuery::$log
		);
	}
}

