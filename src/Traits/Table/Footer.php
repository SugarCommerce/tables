<?php
/**
 * Column Footers
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Traits\Table;

/**
 * A figure under a column for the whole view: a total, an average, a count.
 *
 * A column declares `footer`, a callable given the view's query arguments --
 * exactly what `get_items` is given, less the page and the order -- and how
 * many rows the view has, and returning escaped markup:
 *
 * ```php
 * 'total' => [
 *     'label'  => 'Total',
 *     'footer' => static fn( array $args, int $count ): string => '<strong>' . esc_html( my_sum( $args ) ) . '</strong>',
 * ],
 * ```
 *
 * It goes in the bottom navigation, after the bulk actions -- where core
 * puts a list's extra navigation -- after the column's name, at every width
 * and whether or not the column is switched off in Screen Options. The
 * table's own footer row is left as core prints it: the heading again. A
 * `<small>` in it is quieter than the figure.
 *
 * The library never sums anything: it does not know the data. It asks once
 * per request, only when the view has rows, and remembers the answer.
 */
trait Footer {

    /**
     * What each column's footer says, once asked.
     *
     * @var array<string, string>|null
     */
    private ?array $footers = null;

    /**
     * Each column's footer, for this view.
     *
     * @return array<string, string> Column key => escaped markup. A column
     *                               whose callback said nothing is left out.
     * @since 1.0.0
     */
    public function get_footers(): array {
        if ( null !== $this->footers ) {
            return $this->footers;
        }

        $this->footers = [];
        $callbacks     = $this->footer_callbacks();

        if ( [] === $callbacks ) {
            return $this->footers;
        }

        // A view with nothing in it has nothing to add up, and saying £0.00
        // under no rows is a figure about nothing. Not asked at all, so an
        // empty screen costs no query for it.
        $count = (int) ( $this->_pagination_args['total_items'] ?? 0 );

        if ( $count < 1 ) {
            return $this->footers;
        }

        $args = $this->view_args();

        /** This filter is documented in src/Traits/Table/QueryBuilder.php */
        $args = apply_filters( "sugarcommerce_table_query_args_{$this->id}", $args, $this->config );

        foreach ( $callbacks as $column => $callback ) {
            $markup = (string) call_user_func( $callback, $args, $count );

            if ( '' !== trim( $markup ) ) {
                $this->footers[ $column ] = $markup;
            }
        }

        return $this->footers;
    }

    /**
     * The footer callbacks of the columns being drawn.
     *
     * Read against get_columns(), so a column taken out by the columns
     * filter is not added up for a cell that is never drawn.
     *
     * @return array<string, callable>
     */
    private function footer_callbacks(): array {
        $callbacks = [];
        $drawn     = $this->get_columns();

        foreach ( $this->config['columns'] as $key => $column ) {
            if ( is_array( $column ) && isset( $drawn[ $key ] ) && isset( $column['footer'] ) && is_callable( $column['footer'] ) ) {
                $callbacks[ (string) $key ] = $column['footer'];
            }
        }

        return $callbacks;
    }

    /**
     * The footers, in the bottom navigation, after the bulk actions.
     *
     * Each after its column's name, as core's own text there reads -- not
     * in the table, whose footer row stays core's repeated heading.
     *
     * @return void
     * @since 1.0.0
     */
    private function render_footers(): void {
        $footers = $this->get_footers();

        if ( [] === $footers ) {
            return;
        }

        $columns = $this->get_columns();

        echo '<div class="alignleft sugarcommerce-table-totals">';

        foreach ( $footers as $column => $markup ) {
            printf(
                '<span class="sugarcommerce-table-total sugarcommerce-table-total-%1$s"><span class="sugarcommerce-table-total-label">%2$s</span> %3$s</span>',
                esc_attr( sanitize_html_class( $column ) ),
                esc_html( wp_strip_all_tags( (string) ( $columns[ $column ] ?? '' ) ) ),
                $markup // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the consumer, as a column's cells are.
            );
        }

        echo '</div>';
    }
}
