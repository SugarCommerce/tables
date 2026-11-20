<?php
/**
 * Fetching Rows
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Traits\Table;

use SugarCommerce\Tables\Support\Row;
use SugarCommerce\Tables\Support\Request;
use SugarCommerce\Tables\SearchFilter;

/**
 * Asking the consumer's callback for rows, and for counts.
 *
 * The library never touches a database. It assembles the arguments — paging,
 * ordering, the search term, whatever the filters are set to — hands them to
 * a callback and formats whatever comes back, which is what lets the same
 * table class sit in front of a custom table, a post type or a remote API.
 */
trait QueryBuilder {

    /**
     * Get table data
     *
     * Retrieves items for display by calling the configured get_items callback.
     * Applies pagination, sorting, search, status filter, custom filters, and
     * whitelisted query args.
     *
     * When a search_callback is configured, it receives the search term and
     * can return an array of query args (e.g., ['customer_id' => 42]) which
     * are merged into the query. If the callback returns a non-empty array,
     * the raw search term is not passed to the query. If the callback returns
     * an empty array, the default search behavior is used as a fallback.
     *
     * @return array Array of item objects to display.
     * @since 1.0.0
     *
     */
    public function get_data(): array {
        // The page and the order, then what the view is narrowed by.
        $args = array_merge( $this->parse_pagination_args(), $this->view_args() );

        /**
         * Filter query arguments for a specific table
         *
         * @param array $args   Query arguments.
         * @param array $config Table configuration.
         *
         * @since 1.0.0
         *
         */
        $args = apply_filters( "sugarcommerce_table_query_args_{$this->id}", $args, $this->config );

        // Call the get_items callback
        if ( isset( $this->config['callbacks']['get_items'] ) && is_callable( $this->config['callbacks']['get_items'] ) ) {
            return call_user_func( $this->config['callbacks']['get_items'], $args );
        }

        return [];
    }

    /**
     * What the view is narrowed by: the search, the status, the dropdowns
     * and the whitelisted query args -- everything but the page and the
     * order.
     *
     * One place, because three things ask for the same rows: the page, the
     * count it paginates on, and a column's footer, which sums the whole
     * view. Built three times it was three chances to disagree, and a total
     * that left out one filter would be a confident wrong number.
     *
     * @return array Query arguments.
     * @since 1.0.0
     */
    private function view_args(): array {
        // Search (with optional callback resolution)
        $args = $this->resolve_search( $this->get_search() );

        // Status filter
        if ( ! empty( $this->status ) ) {
            $args['status'] = $this->status;
        }

        // Custom filters
        foreach ( $this->config['filters'] as $filter_key => $filter ) {
            $value = $this->filter_value( $filter_key, $filter );

            if ( '' === $value ) {
                continue;
            }

            if ( is_array( $filter ) && isset( $filter['apply_callback'] ) && is_callable( $filter['apply_callback'] ) ) {
                call_user_func_array( $filter['apply_callback'], [ &$args, $value ] );
            } else {
                $args[ $filter_key ] = $value;
            }
        }

        // Whitelisted query args from the URL
        return $this->apply_query_args( $args );
    }

    /**
     * Get status counts
     *
     * Retrieves and caches item counts per status. Used for view tabs
     * and determining total items for pagination.
     *
     * @return array Associative array of status => count pairs.
     *               Always includes 'total' key.
     * @since 1.0.0
     *
     */
    public function get_counts(): array {
        // Return cached counts if available
        if ( ! empty( $this->counts ) ) {
            return $this->counts;
        }

        // Call the get_counts callback
        if ( isset( $this->config['callbacks']['get_counts'] ) && is_callable( $this->config['callbacks']['get_counts'] ) ) {
            // Within any search filter that is on, which the status links
            // keep: a callback that takes no arguments ignores them and
            // counts the whole list, as it always has.
            $this->counts = call_user_func( $this->config['callbacks']['get_counts'], SearchFilter::query_args( $this->config ) );
        }

        // Deliberately not invented. A fabricated total of nought is
        // indistinguishable from a real one, so a table with no counts
        // callback looked like a table that had counted itself and found
        // nothing — and the get_total() fallback below it never ran.
        return $this->counts;
    }

    /**
     * Prepare items for display
     *
     * Main method called before rendering. Sets up column headers,
     * fetches data, and configures pagination.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function prepare_items(): void {
        $this->prepare_columns();

        // Fetch counts (for status views) and items
        $this->get_counts();
        $this->items = $this->get_data();

        // Determine total for pagination
        $total = $this->get_filtered_total();

        // Set pagination
        $this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $this->per_page,
			'total_pages' => ceil( $total / $this->per_page ),
        ] );
    }

    /**
     * Set up the column headers without fetching anything.
     *
     * Core reads them off the screen when they are not set, and the screen
     * is the wrong one in an ajax request -- so the one place that draws a
     * single row outside a page load sets them here, and skips the query
     * prepare_items() would run to fetch a page it never shows.
     *
     * @return void
     * @since 1.0.0
     */
    public function prepare_columns(): void {
        $this->_column_headers = [
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns(),
			$this->get_primary_column_name(),
        ];
    }

    /**
     * Get a row's id
     *
     * Whatever shape the row is: a get_id() method, an id property, or an
     * `id` key. method_exists() throws a TypeError on an array, so this used
     * to fatal for any table whose query returned arrays.
     *
     * @param mixed $item The row.
     *
     * @return int Item ID.
     * @since 1.0.0
     *
     */
    private function get_item_id( $item ): int {
        return Row::id( $item );
    }

    /**
     * Parse pagination arguments
     *
     * Extracts and sanitizes pagination/sorting parameters from the
     * request for use in database queries.
     *
     * @return array Query arguments with number, offset, order, orderby keys.
     * @since 1.0.0
     *
     */
    private function parse_pagination_args(): array {
        $paged  = max( 1, Request::count( 'paged', 1 ) );
        $offset = $paged > 1 ? $this->per_page * ( $paged - 1 ) : 0;

        $orderby = Request::key( 'orderby' );
        $order   = strtoupper( Request::key( 'order', (string) $this->config['order'] ) );

        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }

        // Only a column the table said could be sorted by. The value goes
        // into the consumer's query as its ORDER BY, and the URL is anybody's
        // to write -- so a column nobody offered falls back to the configured
        // default rather than reaching the query.
        if ( ! in_array( $orderby, $this->orderby_whitelist(), true ) ) {
            $orderby = (string) $this->config['orderby'];
        }

        if ( empty( $orderby ) ) {
            $orderby = 'id';
        }

        return [
			'number'  => $this->per_page,
			'offset'  => $offset,
			'order'   => $order,
			'orderby' => $orderby,
        ];
    }

    /**
     * Every value the orderby argument may take.
     *
     * A sortable column's link carries the first element of its definition
     * rather than the column key -- `['name' => ['display_name', true]]`
     * sorts by display_name -- so both spellings are allowed.
     *
     * @return string[]
     */
    private function orderby_whitelist(): array {
        $allowed = [];

        foreach ( $this->get_sortable_columns() as $key => $definition ) {
            $allowed[] = (string) $key;
            $allowed[] = (string) ( ( (array) $definition )[0] ?? $key );
        }

        return $allowed;
    }
}
