<?php
/**
 * BerlinDB Source
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Sources;

use SugarCommerce\Data\Component;
use SugarCommerce\Tables\Support\Row;

/**
 * A table's callbacks, for rows that live in a BerlinDB table.
 *
 * The library hands its callbacks a request -- how many, how far in, the
 * order, the search term, the status, whatever the filters are set to --
 * and every BerlinDB list then turns that into the query's own arguments
 * in exactly the same way. Written once per list it went wrong once per
 * list, quietly, in ways nothing showed until a table had twenty-one rows
 * (see query_args()). So it is written once, here, next to the code that
 * sends the request:
 *
 *     'callbacks' => BerlinDB::callbacks( [
 *         'query'    => \MyPlugin\Database\Queries\Order::class,
 *         'statuses' => [ 'complete', 'pending', 'refunded' ],
 *         'filters'  => [ 'country', 'gateway' ],
 *     ] ),
 *
 * `query` can also be the table's sugarcommerce/data component --
 * `$components->get( 'orders' )` -- whose query class is then the one used.
 *
 * That is rows, the count the pager works from, the counts beside the
 * status links, the row Quick Edit redraws, Delete and an `update` the
 * status moves write through (see Transitions). Each can be replaced by a
 * callable of the plugin's own -- `get`, `count`, `one`, `delete`,
 * `update` -- which is how a plugin keeps the filters its own wrapper
 * functions apply.
 *
 * @since 1.0.0
 */
final class BerlinDB {

	/**
	 * The paging and ordering arguments, which a count and a list of ids
	 * have no use for.
	 */
	private const array PAGING = [
		'number'  => true,
		'offset'  => true,
		'orderby' => true,
		'order'   => true,
	];

	/**
	 * The most rows a page may ask for: core's own ceiling on the
	 * per-page box in Screen Options.
	 */
	private const int MOST = 999;

	/**
	 * The callbacks a table needs, from a description of where its rows are.
	 *
	 * @param array $options {
	 *     @type string|Component $query  A BerlinDB Query class, or the
	 *                                    sugarcommerce/data component whose
	 *                                    query class it is. Supplies every
	 *                                    callable below that is not given.
	 *     @type callable $get            ( array $query ): array -- rows, or ids
	 *                                    with `'fields' => 'ids'`.
	 *     @type callable $count          ( array $query ): int.
	 *     @type callable $one            ( int $id ): object|false.
	 *     @type callable $delete         ( int $id ): bool.
	 *     @type callable $update         ( int $id, array $columns ): bool.
	 *     @type string[] $statuses       Statuses counted for the status links.
	 *     @type string[] $filters        Request keys passed through to the
	 *                                    query as columns of the same name.
	 *     @type array    $filter_ids     Request key => column, for a filter
	 *                                    whose value is a row id: `customer`
	 *                                    in the address, `customer_id` on the
	 *                                    table.
	 *     @type callable|array $scope    Arguments every query is held to,
	 *                                    whatever the request says: a live or
	 *                                    a test mode, a site, an owner.
	 *     @type array    $search_also    Query argument => ( string $term ):
	 *                                    array -- rows whose argument is one of
	 *                                    the values are found by a search too.
	 *     @type int      $search_limit   How many ids each half of a widened
	 *                                    search may find. Default 0: all.
	 *     @type bool|callable $country_search A country's name finds the rows
	 *                                    from it. Default on when `country` is
	 *                                    one of the filters. A callable is
	 *                                    ( string $term ): string[] codes.
	 *     @type array    $prime          Column => what it points at: a
	 *                                    BerlinDB Query class, a data
	 *                                    component, or ( int[] $ids ): void.
	 *     @type callable $also           ( array $rows ): void -- anything else
	 *                                    a page of rows should fetch at once.
	 * }
	 *
	 * @return array<string, callable>
	 * @since 1.0.0
	 */
	public static function callbacks( array $options ): array {
		$options = self::resolve( $options );

		$get   = $options['get'];
		$count = $options['count'];

		return [
			// Declaring this is what makes the library draw a Delete row
			// action, with its own nonce and confirmation. Building one by
			// hand would be a second, unprotected path to the same thing.
			'delete'     => static fn( $id ): bool => (bool) ( $options['delete'] )( (int) $id ),

			// What Quick Edit redraws the row from after a save. Without it
			// the save happens and the row does not change, which reads
			// exactly like a save that failed.
			'get_item'   => static fn( $id ) => ( $options['one'] )( (int) $id ),

			// What a status move writes through; see Transitions.
			'update'     => static fn( $id, array $columns ): bool => (bool) ( $options['update'] )( (int) $id, $columns ),

			// A page of rows, or -- asked with `count` -- how many there
			// are to page through. The library asks that way whenever a
			// search or a filter is on, and answered with rows it fell
			// back to counting the page in front of it: twenty of sixty
			// matches, "20 items", and no way to the other forty.
			'get_items'  => static function ( array $args = [] ) use ( $options, $get, $count ) {
				$query = self::search( self::query_args( $args, $options ), $options );

				if ( ! empty( $args['count'] ) ) {
					return (int) $count( array_diff_key( $query, self::PAGING ) );
				}

				$rows = $get( $query );

				return is_array( $rows ) ? self::prime( $rows, $options ) : $rows;
			},
			'get_total'  => static fn( array $args = [] ): int => (int) $count(
				array_diff_key( self::search( self::query_args( $args, $options ), $options ), self::PAGING )
			),
			'get_counts' => static function ( array $narrowed = [] ) use ( $options, $count ): array {
				// The counts have to agree with the rows, or the status links
				// say one number above a table showing another.
				$scope = self::scope( $options );

				// Within a search filter that is on -- one product's orders
				// -- which the status links keep, and which the table
				// library hands over: through the same whitelist the rows
				// go through, less the page and the order.
				if ( [] !== $narrowed ) {
					$scope = array_merge(
						array_diff_key( self::query_args( $narrowed, array_merge( $options, [ 'scope' => [] ] ) ), self::PAGING ),
						$scope
					);
				}

				// 'total' is what the table paginates on. Leave it out and
				// the screen says "0 items" while drawing a page of them.
				$all    = (int) $count( $scope );
				$counts = [
					'total' => $all,
					'all'   => $all,
				];

				foreach ( (array) $options['statuses'] as $status ) {
					$counts[ (string) $status ] = (int) $count( array_merge( $scope, [ 'status' => (string) $status ] ) );
				}

				return $counts;
			},
		];
	}

	/**
	 * The query a page of rows is read with, search widening included.
	 *
	 * query_args() and then what `country_search` and `search_also` add to
	 * a search: the arguments `get_items` hands to `get`. For something
	 * that has to agree with the rows without being them -- a sum of the
	 * listed orders under the column, say -- so a search for "germany"
	 * sums the German orders the list is showing.
	 *
	 * @param array $args    What the table asked for.
	 * @param array $options As for callbacks().
	 *
	 * @return array<string, mixed>
	 * @since 1.0.0
	 */
	public static function query( array $args, array $options ): array {
		$options = self::resolve( $options );

		return self::search( self::query_args( $args, $options ), $options );
	}

	/**
	 * Turn the table's request into BerlinDB query arguments.
	 *
	 * Filter values arrive alongside the paging arguments under their own
	 * keys, so anything named in `filters` is passed straight through --
	 * which is what makes a country or a gateway dropdown work without a
	 * case for each one.
	 *
	 * Public, because a list that has no status to count, or that builds its
	 * own query around this one, still wants the request read the same way.
	 *
	 * @param array $args    What the table asked for.
	 * @param array $options `filters`, `filter_ids` and `scope`, as for callbacks().
	 *
	 * @return array<string, mixed>
	 * @since 1.0.0
	 */
	public static function query_args( array $args, array $options = [] ): array {
		// As the library sends them: how many, and how far in. A copy of
		// this once read `per_page` and `paged`, which the library has never
		// sent, so both fell back to their defaults and every page of every
		// table was the first twenty rows -- unnoticed while no table had
		// twenty-one.
		$query = [
			'number'  => min( self::MOST, max( 1, (int) ( $args['number'] ?? 20 ) ) ),
			'offset'  => max( 0, (int) ( $args['offset'] ?? 0 ) ),
			'orderby' => (string) ( $args['orderby'] ?? 'id' ),
			'order'   => 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC',
		];

		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$query['status'] = sanitize_key( (string) $args['status'] );
		}

		// `search`, which is what the table library hands over -- it reads
		// `s` from the request and has already normalised it by the time
		// this runs. A copy of this once read `s`, a key that never arrives,
		// so every search matched everything: the term reached the URL, the
		// box kept what was typed, and the rows never changed.
		//
		// The column list is BerlinDB's: it works out where to look from the
		// schema's own searchable flags, so naming them here would be a
		// second list to keep in step.
		if ( ! empty( $args['search'] ) ) {
			$query['search'] = sanitize_text_field( (string) $args['search'] );
		}

		// Whitelisted rather than merged wholesale: anything in the request
		// would otherwise reach the query, and 'number' alone would let a
		// URL ask for every row in the table.
		foreach ( (array) ( $options['filters'] ?? [] ) as $key ) {
			$key = (string) $key;

			if ( ! isset( $args[ $key ] ) || '' === $args[ $key ] || isset( $options['filter_ids'][ $key ] ) ) {
				continue;
			}

			$query[ $key ] = sanitize_text_field( (string) $args[ $key ] );
		}

		// One customer's rows: `customer` in the address, which is what
		// somebody reads, and `customer_id` on the table. A number that
		// cannot be an id matches nothing: nought would otherwise find every
		// row that has no customer at all.
		foreach ( (array) ( $options['filter_ids'] ?? [] ) as $key => $column ) {
			if ( ! isset( $args[ $key ] ) || '' === $args[ $key ] ) {
				continue;
			}

			$id = absint( $args[ $key ] );

			if ( $id > 0 ) {
				$query[ (string) $column ] = $id;
			} else {
				$query['id__in'] = [ 0 ];
			}
		}

		// Last, so nothing in the request can step outside it. A test mode
		// is one switch in the settings, not a dropdown on every screen:
		// while it is on the lists show test rows and nothing else.
		return array_merge( $query, self::scope( $options ) );
	}

	/**
	 * Widen a query's search to the rows found another way.
	 *
	 * A row holds a country as two letters, and nobody types `DE` to mean
	 * Germany: they type "germany", or "germ". A licence is searched for by
	 * whose it is, and the name is on another table. So a search also finds
	 * the rows whose argument is one of what the term names -- as well as,
	 * because "georgia" is a country and also somebody's name, and both are
	 * what was asked for.
	 *
	 * BerlinDB's `search` is one group of LIKEs joined to everything else by
	 * AND, and has no way to be told "or the country is one of these". So
	 * the halves are asked for apart, as ids, and the page is then asked for
	 * by id: the rows the words match, and the rows the other ways find,
	 * each narrowed by whatever else the screen is filtered by. That leaves
	 * the order, the paging and the count to the one query that draws the
	 * page.
	 *
	 * Each half is every matching id unless a `search_limit` says
	 * otherwise: a count that stopped at a limit would be a pager that
	 * lies. On a table with tens of thousands of rows in one country that
	 * is a long list of numbers to hand back to the database, and the
	 * moment that matters it wants to be one query with an OR in it --
	 * which needs BerlinDB to grow a way to say so.
	 *
	 * @param array $query   From query_args().
	 * @param array $options As for callbacks(), resolved.
	 *
	 * @return array<string, mixed> The query, by id when the search was widened.
	 * @since 1.0.0
	 */
	private static function search( array $query, array $options ): array {
		$term = (string) ( $query['search'] ?? '' );

		if ( '' === $term ) {
			return $query;
		}

		$also = [];

		foreach ( (array) $options['search_also'] as $argument => $find ) {
			$values = is_callable( $find ) ? array_values( (array) $find( $term ) ) : [];

			if ( [] !== $values ) {
				$also[ (string) $argument ] = $values;
			}
		}

		if ( [] === $also ) {
			return $query;
		}

		// Everything else the screen is narrowed by, for every half.
		$base = array_diff_key( $query, self::PAGING, [ 'search' => true ] ) + [
			'fields' => 'ids',
			'number' => max( 0, (int) $options['search_limit'] ),
		];

		$ids = (array) ( $options['get'] )( $base + [ 'search' => $term ] );

		foreach ( $also as $argument => $values ) {
			$ids = array_merge( $ids, (array) ( $options['get'] )( array_merge( $base, [ $argument => $values ] ) ) );
		}

		unset( $query['search'] );

		// An empty list would be read as no restriction at all.
		$ids             = array_values( array_unique( array_map( 'intval', $ids ) ) );
		$query['id__in'] = [] === $ids ? [ 0 ] : $ids;

		return $query;
	}

	/**
	 * Fetch what a page of rows will ask about, before the rows are drawn.
	 *
	 * A list shows things that live on other tables: an order's customer,
	 * a licence's product. Asked a row at a time that is a query a row for
	 * each of them. Asked here it is one query for each kind, and the
	 * answers are remembered -- BerlinDB keeps what it fetched in the object
	 * cache -- so the cell that asks afterwards asks the memory.
	 *
	 * @param array $rows    The page.
	 * @param array $options As for callbacks(), resolved.
	 *
	 * @return array The same rows.
	 * @since 1.0.0
	 */
	private static function prime( array $rows, array $options ): array {
		if ( [] === $rows ) {
			return $rows;
		}

		foreach ( (array) $options['prime'] as $column => $fetch ) {
			$fetch = self::query_class( $fetch ) ?? $fetch;

			$ids = array_values(
				array_unique(
					array_filter( array_map( static fn( $row ): int => (int) Row::get( $row, (string) $column, 0 ), $rows ) )
				)
			);

			if ( [] === $ids ) {
				continue;
			}

			if ( is_string( $fetch ) && class_exists( $fetch ) ) {
				// The rows are thrown away: asking is what fills the memory.
				( new $fetch() )->query(
					[
						'id__in' => $ids,
						'number' => 0,
					]
				);
			} elseif ( is_callable( $fetch ) ) {
				$fetch( $ids );
			}
		}

		if ( isset( $options['also'] ) && is_callable( $options['also'] ) ) {
			( $options['also'] )( $rows );
		}

		return $rows;
	}

	/**
	 * The arguments every query is held to.
	 *
	 * @param array $options As for callbacks().
	 *
	 * @return array<string, mixed>
	 */
	private static function scope( array $options ): array {
		$scope = $options['scope'] ?? [];

		return (array) ( is_callable( $scope ) ? $scope() : $scope );
	}

	/**
	 * The options with every default filled in, and the five callables made
	 * from the query class where they were not given.
	 *
	 * @param array $options As for callbacks().
	 *
	 * @return array<string, mixed>
	 */
	private static function resolve( array $options ): array {
		$class = (string) ( self::query_class( $options['query'] ?? '' ) ?? '' );
		$query = static fn(): object => new $class();

		$options += [
			'get'          => static fn( array $args ) => $query()->query( $args ),
			'count'        => static fn( array $args ): int => (int) $query()->query( array_merge( $args, [
				'count' => true,
				'number' => 0,
			] ) ),
			'one'          => static fn( int $id ) => $query()->get_item( $id ),
			'delete'       => static fn( int $id ): bool => (bool) $query()->delete_item( $id ),
			'update'       => static fn( int $id, array $columns ): bool => (bool) $query()->update_item( $id, $columns ),
			'statuses'     => [],
			'filters'      => [],
			'filter_ids'   => [],
			'search_also'  => [],
			'search_limit' => 0,
			'prime'        => [],
		];

		// A list that can be filtered by country can be searched for one by
		// name.
		$countries = $options['country_search'] ?? in_array( 'country', (array) $options['filters'], true );

		if ( false !== $countries && null !== $countries && ! isset( $options['search_also']['country__in'] ) ) {
			$options['search_also']['country__in'] = is_callable( $countries )
				? $countries
				: static fn( string $term ): array => CountrySearch::codes( $term );
		}

		return $options;
	}

	/**
	 * The query class a `query` or a `prime` entry names.
	 *
	 * A sugarcommerce/data component knows its own query class -- the one
	 * its rows are read and cached through -- so a list declared against
	 * the component and the component's other readers share one object
	 * cache.
	 *
	 * @param mixed $source A class name, a component, or anything else.
	 *
	 * @return string|null Null when it names no class: a callable, say.
	 */
	private static function query_class( mixed $source ): ?string {
		if ( $source instanceof Component ) {
			return $source->class_for( 'query' );
		}

		return is_string( $source ) && '' !== $source && class_exists( $source ) ? $source : null;
	}
}
