<?php
/**
 * Asset Loading
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Traits\Tables;

use SugarCommerce\Plugin\Assets;
use SugarCommerce\Tables\Support\ColumnLayout;
use SugarCommerce\Tables\Editing\Routes;
use SugarCommerce\Tables\HiddenColumns;
use SugarCommerce\Tables\Support\Request;
use SugarCommerce\Tables\SearchFilter;
use SugarCommerce\Tables\Table;
use SugarCommerce\Tables\Utils\Runtime;
use SugarCommerce\Tables\Support\ScriptConfig;

/**
 * Loading the stylesheet and script, and only where a table is.
 *
 * Admin CSS loaded on every screen is how a library becomes something people
 * remove, so the enqueue is gated on the screen belonging to a registered
 * table. The dynamic styles are the per-table column widths, which cannot be
 * in a static file because they come from configuration.
 */
trait AssetManager {

    /**
     * Enqueue assets callback
     *
     * Hooked to admin_enqueue_scripts. Checks if current page matches
     * a registered table and enqueues styles if so.
     *
     * @param string $hook Current admin page hook suffix.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public static function enqueue_assets( string $hook ): void {
        $page = Request::text( 'page' );

        if ( empty( $page ) ) {
            return;
        }

        // Find matching table config
        foreach ( self::$tables as $id => $config ) {
            if ( ( $config['menu_slug'] ?? '' ) === $page ) {
                self::do_enqueue_assets( $config, (string) $id );
                break;
            }
        }
    }

    /**
     * Actually enqueue the assets
     *
     * Enqueues the main stylesheet and outputs dynamic inline styles
     * for column widths and alignments.
     *
     * @param array  $config Table configuration.
     * @param string $id     Table id.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function do_enqueue_assets( array $config, string $id = '' ): void {
        if ( self::$assets_enqueued ) {
            return;
        }

        self::$assets_enqueued = true;

        // Not sugarcommerce/fields' stylesheet. It was loaded for the
        // fields header, and the header is core's own h1 now; what remains
        // of sugarcommerce/fields here is
        // Display::placeholder(), whose markup wants only core's
        // .screen-reader-text. A stylesheet and a script on every list
        // screen, for a class neither of them defines, is a request too many.

        // Enqueue CSS from composer assets package
        // The asset library's class, not its global wrapper: a bundled copy
        // prefixes the class and not the function; see FlyoutLink.
        Assets::enqueue_style(
                Runtime::handle(),
                __FILE__,
                'css/admin-tables.css'
        );

        // Only when there is something to edit inline. A script that binds
        // nothing is still a request.
        if ( ! empty( $config['bulk_edit'] ) || ! empty( $config['quick_edit'] ) ) {
            Assets::enqueue_built_script( Runtime::handle( 'inline-edit' ), __FILE__, 'inline-edit' );

            // Every string the script can show, and where to post to.
            // Hard-coded English in the script would be untranslatable, and
            // the four places it can speak are all failure or announcement
            // text -- exactly the moments where a reader needs their own
            // language.
            ScriptConfig::attach(
                    Runtime::handle( 'inline-edit' ),
                    [
						// The REST route Quick Edit saves through, with api-fetch.
						'path'           => Routes::path( $id, 'quick-edit' ),
						'error'          => __( 'The change could not be saved.', 'sugarcommerce' ),
						'saved'          => __( 'Changes saved.', 'sugarcommerce' ),
						'removeFromBulk' => __( 'Remove from Bulk Edit', 'sugarcommerce' ),
						// Said after a half-ticked box's own name in a bulk
						// checklist, as core says it of a category.
						'someHaveThis'   => __( 'Some selected items have this', 'sugarcommerce' ),
                    ]
            );
        }

        // sugarcommerce/fields' searchable select, for a filter too long to list.
        if ( SearchFilter::any( $config ) ) {
            SearchFilter::enqueue();
        }

        // A deferred column's reload, when the table has one.
        HiddenColumns::enqueue( $config, $id );

        // Output dynamic styles for this table's configuration
        self::output_dynamic_styles( $config );
    }

    /**
     * Output dynamic inline styles
     *
     * Generates CSS for custom column widths and text alignments
     * based on the column configuration.
     *
     * @param array $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function output_dynamic_styles( array $config ): void {
        $styles = '';

        if ( ! empty( $config['columns'] ) ) {
            foreach ( $config['columns'] as $column => $col_config ) {
                // A figure's column as wide as the figure, on one line, so
                // the columns with words in them get the room. The table is
                // laid out by its contents (see Table::get_table_classes),
                // so the 1% is "as narrow as it goes", not a size.
                if ( ColumnLayout::is_compact( (string) $column, $col_config, $config ) ) {
                    $styles .= sprintf(
                            ".wp-list-table .column-%s { width: 1%%; white-space: nowrap; }\n",
                            sanitize_html_class( (string) $column )
                    );
                }

                if ( ! is_array( $col_config ) ) {
                    continue;
                }

                // Column width
                if ( ! empty( $col_config['width'] ) ) {
                    $styles .= sprintf(
                            ".wp-list-table .column-%s { width: %s; }\n",
                            esc_attr( $column ),
                            esc_attr( $col_config['width'] )
                    );
                }

                // A footer moves to the bottom navigation while its column
                // is switched off in Screen Options. Core marks the cells
                // `hidden` and the stylesheet cannot name a column it has
                // never heard of, so the rule is this column's own.
                if ( ! empty( $col_config['footer'] ) ) {
                    $styles .= sprintf(
                            ".wp-list-table:has(> tfoot .column-%1\$s.hidden) ~ .tablenav.bottom .sugarcommerce-table-total-%1\$s { display: inline; }\n",
                            sanitize_html_class( (string) $column )
                    );
                }

                // Column alignment
                if ( ! empty( $col_config['align'] ) ) {
                    $align  = in_array( $col_config['align'], [ 'left', 'center', 'right' ], true )
                            ? $col_config['align']
                            : 'left';
                    $styles .= sprintf(
                            ".wp-list-table .column-%s { text-align: %s; }\n",
                            esc_attr( $column ),
                            esc_attr( $align )
                    );
                }
            }
        }

        if ( ! empty( $styles ) ) {
            wp_add_inline_style( Runtime::handle(), $styles );
        }
    }
}
