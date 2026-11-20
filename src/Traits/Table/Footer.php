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
 * It goes in that column's cell of the footer row core already prints, in
 * place of the repeated heading, which is where the eye is after running down
 * the column -- the way a spreadsheet puts a sum. A `<small>` in it is a
 * quieter second line there.
 *
 * Wherever that cell is not on screen -- the column switched off in Screen
 * Options, or a phone-width screen, where core shows the primary column
 * alone -- the same figure is shown in the bottom navigation instead, after
 * the column's name. That is the stylesheet's choice, made from the cell's
 * `hidden` class, so switching the column off without a reload moves it.
 *
 * The library never sums anything: it does not know the data. It asks once
 * per request, only when the view has rows, and remembers the answer for the
 * two places that draw it.
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
     * Print the heading row, with the footers in the footer's copy of it.
     *
     * Core prints the same row twice, the second time without ids, and
     * leaves nothing in between to hook. Its markup is kept whole -- the
     * select-all box, the sort links of the other columns, the hidden and
     * primary classes Screen Options relies on -- and only the cells that
     * have a footer have their heading swapped for it.
     *
     * @param bool $with_id Whether this is the heading (true) or the footer.
     *
     * @return void
     * @since 1.0.0
     */
    #[\Override]
    public function print_column_headers( $with_id = true ): void {
        $footers = $with_id ? [] : $this->get_footers();

        if ( [] === $footers ) {
            parent::print_column_headers( $with_id );

            return;
        }

        ob_start();
        parent::print_column_headers( $with_id );
        $row = (string) ob_get_clean();

        echo $this->with_footers( $row, $footers ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core's own markup, and footers the consumer escaped.
    }

    /**
     * Swap a footer into the cell of its column.
     *
     * Matched on the `column-{key}` class rather than the cell's position:
     * the row has a checkbox cell or not, and columns can be filtered in or
     * out after the configuration was read.
     *
     * @param string                $row     The cells core printed.
     * @param array<string, string> $footers Column key => markup.
     *
     * @return string
     */
    private function with_footers( string $row, array $footers ): string {
        return (string) preg_replace_callback(
            '/<th\b([^>]*)>(.*?)<\/th>/s',
            static function ( array $cell ) use ( $footers ): string {
                if ( ! preg_match( '/\bclass=(["\'])(.*?)\1/', $cell[1], $class ) ) {
                    return $cell[0];
                }

                $classes = preg_split( '/\s+/', trim( $class[2] ) ) ?: [];

                foreach ( $footers as $column => $markup ) {
                    if ( in_array( 'column-' . $column, $classes, true ) ) {
                        $attributes = str_replace(
                            $class[0],
                            'class=' . $class[1] . $class[2] . ' sugarcommerce-table-footer' . $class[1],
                            $cell[1]
                        );

                        return '<th' . $attributes . '>' . $markup . '</th>';
                    }
                }

                return $cell[0];
            },
            $row
        );
    }

    /**
     * The footers again, in the bottom navigation, for when their cells are
     * not on screen.
     *
     * Each is hidden by the stylesheet until its column's footer cell is:
     * see the class comment.
     *
     * @return void
     * @since 1.0.0
     */
    private function render_footer_fallback(): void {
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
