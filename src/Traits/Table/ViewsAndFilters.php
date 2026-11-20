<?php
/**
 * Views And Filters
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Traits\Table;

use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Support\Request;
use SugarCommerce\Tables\SearchFilter;

/**
 * The status links above the table and the controls beside them.
 *
 * Views are the `All (12) | Published (9) | Trash (3)` row, with the counts
 * coming from the table's own callback rather than from counting the rows on
 * this page. Filters are whatever selects the table declared, rendered into
 * core's tablenav so they sit where every other list table puts them.
 */
trait ViewsAndFilters {

    /**
     * Get views (status filter tabs)
     *
     * Builds the list of status links shown above the table.
     * Only shows statuses that have items.
     *
     * @return array View key => HTML link pairs.
     * @since 1.0.0
     *
     */
    #[\Override]
    public function get_views(): array {
        $views   = [];
        $current = $this->status;

        // The page, with no search, status or dropdown -- but with any
        // search filter that is on: that is the narrowing the list is
        // about, and "Pending" means pending ones within it. The counts
        // below were asked for within it too. See SearchFilter.
        $base_url = Tables::page_url( $this->config, SearchFilter::active( $this->config ) );

        // Ensure counts are loaded
        $this->get_counts();

        // Nothing at all, anywhere. The status links would read "All (0)"
        // above an empty-state panel that already says the same thing, so
        // there is nothing to navigate between and nothing to say. This is
        // the unfiltered total, so a filter that happens to match nothing
        // still keeps the links -- that is when they are most wanted.
        if ( 1 > (int) ( $this->counts['total'] ?? 0 ) ) {
            return [];
        }

        // A list with no statuses has nothing to switch between: "All (8)"
        // on its own is a link to the page you are on, beside a count the
        // table states again at its right-hand end.
        if ( [] === array_diff_key( (array) $this->config['views'], [ 'all' => true ] ) ) {
            return [];
        }

        // All items view
        $views['all'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                esc_url( $base_url ),
                empty( $current ) ? 'current' : '',
                esc_html__( 'All', 'sugarcommerce' ),
                esc_html( number_format_i18n( $this->counts['total'] ?? 0 ) )
        );

        // Status-specific views
        foreach ( $this->config['views'] as $key => $label ) {
            if ( $key === 'all' ) {
                continue;
            }

            // Skip if no items with this status
            if ( ! isset( $this->counts[ $key ] ) || $this->counts[ $key ] < 1 ) {
                continue;
            }

            $url = add_query_arg( 'status', $key, $base_url );

            $views[ $key ] = sprintf(
                    '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                    esc_url( $url ),
                    $current === $key ? 'current' : '',
                    esc_html( $label ),
                    esc_html( number_format_i18n( $this->counts[ $key ] ) )
            );
        }

        /**
         * Filter the status views
         *
         * @param array  $views  View links.
         * @param string $status Current status filter.
         *
         * @since 1.0.0
         *
         */
        return apply_filters( "sugarcommerce_table_views_{$this->id}", $views, $this->status );
    }

    /**
     * Extra table navigation (filters)
     *
     * Renders dropdown filters above the table (top position only).
     *
     * @param string $which Position: 'top' or 'bottom'.
     *
     * @since 1.0.0
     *
     */
    #[\Override]
    protected function extra_tablenav( $which ): void {
        // Below the table, after the bulk actions: each column's figure for
        // the view. See Traits\Footer.
        if ( 'bottom' === $which ) {
            $this->render_footers();

            return;
        }

        if ( $which !== 'top' ) {
            return;
        }

        $has_filters    = ! empty( $this->config['filters'] );
        $has_query_args = $this->has_active_query_args();

        if ( ! $has_filters && ! $has_query_args ) {
            return;
        }

        ?>
        <?php
        // Core hides the filters above a list on a phone. A list with a
        // search filter keeps them: it is how one product's orders are
        // found there, and the stylesheet gives each control a line.
        ?>
        <div class="alignleft actions<?php echo SearchFilter::any( $this->config ) ? ' sugarcommerce-table-filters' : ''; ?>">
            <?php
            // Render each filter dropdown
            foreach ( $this->config['filters'] as $key => $filter ) {
                $this->render_filter( $key, $filter );
            }

            // Filter submit button, when there is something drawn to choose
            // with: a search filter with no source is carried, not drawn,
            // and a button beside nothing submits nothing new.
            $drawn = array_filter(
                    $this->config['filters'],
                    static fn( $filter ): bool => ! SearchFilter::is( $filter ) || SearchFilter::searches( $filter )
            );

            if ( [] !== $drawn ) {
                submit_button( __( 'Filter', 'sugarcommerce' ), '', 'filter_action', false );
            }

            // Clear button — show when any filter or query arg is active
            $any_active = $has_query_args;
            if ( ! $any_active ) {
                foreach ( $this->config['filters'] as $key => $filter ) {
                    if ( Request::filled( $key ) ) {
                        $any_active = true;
                        break;
                    }
                }
            }

            if ( $any_active ) {
                $clear_url = Tables::page_url( $this->config );
                printf(
                        '<a href="%s" class="button">%s</a>',
                        esc_url( $clear_url ),
                        esc_html__( 'Clear', 'sugarcommerce' )
                );
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render a single filter dropdown
     *
     * Generates a select element for filtering table data.
     * Supports static options array or dynamic options callback.
     *
     * @param string $key    Filter identifier (used as form field name).
     * @param mixed  $filter Filter configuration array or simple options array.
     *
     * @since 1.0.0
     *
     */
    private function render_filter( string $key, $filter ): void {
        // Too many choices to list: searched as it is typed into, or
        // carried unseen. See SearchFilter.
        if ( SearchFilter::is( $filter ) ) {
            echo SearchFilter::control( $key, (array) $filter ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where it is built.

            return;
        }

        $options = [];
        $label   = '';
        $current = Request::text( $key );

        if ( is_array( $filter ) ) {
            $label = $filter['label'] ?? '';

            // Listed outright, named as a fields preset, or produced by a
            // callback -- the same resolution the value check uses.
            $options = $this->filter_options( $filter );
        }

        /*
         * Drawn even with nothing to choose from. Returning here left the
         * Filter button standing on its own -- the button asks whether there
         * are filters at all, not whether any of them found options, so a
         * country filter on a screen where no order has a country yet showed
         * a button beside nothing.
         *
         * A select holding only its own label says the same thing the button
         * does and keeps the row the shape it will be once there is data,
         * which is the whole reason the filter is declared before there is
         * any.
         */
        $empty = empty( $options );

        ?>
        <select name="<?php echo esc_attr( $key ); ?>" id="filter-by-<?php echo esc_attr( $key ); ?>"
            <?php disabled( $empty ); ?>>
            <?php if ( $label ) : ?>
                <option value=""><?php echo esc_html( $label ); ?></option>
            <?php endif; ?>

            <?php foreach ( $options as $value => $text ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, (string) $value ); ?>>
                    <?php echo esc_html( $text ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
