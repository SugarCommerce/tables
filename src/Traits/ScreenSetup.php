<?php
/**
 * Screen Setup
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Traits;

use SugarCommerce\Tables\ListDate;
use SugarCommerce\Tables\Request;
use SugarCommerce\Tables\Table;
use WP_Screen;

/**
 * What has to be arranged before the page renders.
 *
 * Column options, per-page counts and help tabs all hang off the load hook
 * for the table's own screen: they are read by core while the page is being
 * built, so registering them any later means registering them for the next
 * request instead of this one.
 */
trait ScreenSetup {

    /**
     * Setup load hooks for screen options
     *
     * Detects when we're on one of our admin pages and sets up screen
     * options (items per page) and help tabs on the current_screen hook.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public static function setup_load_hooks(): void {
        global $pagenow;

        // Only process on admin.php pages
        if ( $pagenow !== 'admin.php' ) {
            return;
        }

        $page = Request::text( 'page' );

        if ( empty( $page ) ) {
            return;
        }

        // Find matching table config
        foreach ( self::$tables as $id => $config ) {
            if ( ( $config['menu_slug'] ?? '' ) === $page ) {
                // Setup screen options on current_screen hook
                add_action( 'current_screen', function () use ( $id, $config ) {
                    self::setup_screen( $id, $config );
                } );
                break;
            }
        }
    }

    /**
     * Setup screen options and help tabs
     *
     * Adds the "items per page" screen option and any configured help tabs.
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function setup_screen( string $id, array $config ): void {
        $screen = get_current_screen();

        if ( ! $screen ) {
            return;
        }

        // Build unique option name for this table
        $option_name = self::per_page_option( $id );

        // Add per page screen option
        $screen->add_option( 'per_page', [
			'label'   => __( 'Number of items per page:', 'sugarcommerce' ),
			'default' => $config['per_page'],
			'option'  => $option_name,
        ] );

        // Add column visibility options
        self::setup_column_options( $screen, $config, $id );

        // "Dates: Relative / Exact", when the table keeps a preference;
        // saved with the rest of the form, in handle_screen_options().
        $dates = ListDate::meta_key( $id, $config );

        if ( '' !== $dates ) {
            $screen_id = $screen->id;

            add_filter( 'screen_settings', static function ( $settings, $current = null ) use ( $dates, $screen_id ) {
                if ( ! is_object( $current ) || ( $current->id ?? '' ) !== $screen_id ) {
                    return $settings;
                }

                return (string) $settings . ListDate::control( $dates );
            }, 10, 2 );
        }

        /*
         * The three headings core gives every list screen. They are only ever
         * announced, never drawn -- WP_List_Table wraps the views, the
         * pagination and the table itself in <h2 class="screen-reader-text">
         * and reads these -- so without them a screen reader meets three
         * unnamed regions where core names them.
         *
         * core sets these on edit.php, upload.php and every other list it
         * ships. A list built from configuration should not be the one that
         * does not.
         */
        $plural = $config['labels']['plural'] ?? __( 'items', 'sugarcommerce' );

        $screen->set_screen_reader_content(
            [
				'heading_views'      => sprintf(
					/* translators: %s: plural item label */
					__( 'Filter %s list', 'sugarcommerce' ),
					$plural
				),
				'heading_pagination' => sprintf(
					/* translators: %s: plural item label */
					__( '%s list navigation', 'sugarcommerce' ),
					ucfirst( $plural )
				),
				'heading_list'       => sprintf(
					/* translators: %s: plural item label */
					__( '%s list', 'sugarcommerce' ),
					ucfirst( $plural )
				),
            ]
        );

        // Add help tabs if configured
        if ( ! empty( $config['help'] ) ) {
            self::setup_help_tabs( $screen, $config['help'] );
        }
    }

    /**
     * Setup column visibility screen options
     *
     * Registers columns so users can show/hide them via Screen Options.
     *
     * @param WP_Screen $screen Current screen object.
     * @param array     $config Table configuration.
     * @param string    $id     Table identifier.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function setup_column_options( $screen, array $config, string $id = '' ): void {
        if ( empty( $config['columns'] ) ) {
            return;
        }

        // Build columns array for WP_Screen
        $columns = [];
        foreach ( $config['columns'] as $key => $column ) {
            if ( $key === 'cb' ) {
                continue; // Skip checkbox column
            }

            $label           = is_array( $column ) ? ( $column['label'] ?? $key ) : $column;
            $columns[ $key ] = $label;
        }

        // Store columns for get_column_headers()
        // WordPress uses this to build the column toggle checkboxes
        add_filter( 'manage_' . $screen->id . '_columns', function () use ( $columns ) {
            return $columns;
        } );

        /*
         * And which of them start hidden, said to core as well as to the
         * table. The table hides a column in `hidden_columns` until
         * somebody has saved a choice of their own; the checkboxes in
         * Screen Options are core's, and core asks this filter. Unsaid,
         * the two disagreed: a column that was not in the table had a
         * ticked box, and the first click on it unticked the box and
         * changed nothing.
         */
        $screen_id = $screen->id;

        add_filter( 'default_hidden_columns', function ( $hidden, $current = null ) use ( $config, $id, $screen_id ) {
            if ( ! is_object( $current ) || ( $current->id ?? '' ) !== $screen_id ) {
                return $hidden;
            }

            /** This filter is documented in src/Traits/ColumnDefinitions.php */
            $ours = apply_filters( "sugarcommerce_table_hidden_columns_{$id}", $config['hidden_columns'] ?? [], $config );

            return array_values( array_unique( array_merge( (array) $hidden, (array) $ours ) ) );
        }, 10, 2 );
    }

    /**
     * Setup help tabs on the screen
     *
     * @param WP_Screen $screen Current screen object.
     * @param array     $help   Help tab configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function setup_help_tabs( $screen, array $help ): void {
        foreach ( $help as $key => $tab ) {
            // Sidebar is special
            if ( $key === 'sidebar' ) {
                $screen->set_help_sidebar( $tab );
                continue;
            }

            if ( ! is_array( $tab ) || ! isset( $tab['title'] ) ) {
                continue;
            }

            // Get content from callback or direct content
            $content = '';
            if ( isset( $tab['callback'] ) && is_callable( $tab['callback'] ) ) {
                $content = call_user_func( $tab['callback'] );
            } elseif ( isset( $tab['content'] ) ) {
                $content = $tab['content'];
            }

            $screen->add_help_tab( [
				'id'      => sanitize_key( $key ),
				'title'   => $tab['title'],
				'content' => $content,
            ] );
        }
    }

    /**
     * Handle screen option saving
     *
     * Adds the filter that lets a table's per_page option be saved.
     *
     * Only this library's own options. Core hands every screen option on
     * the site through this one filter, and matching on the `_per_page`
     * suffix claimed all of them -- another plugin's was saved as an integer
     * whether or not that was what it held, and its own filter never saw it.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function handle_screen_options(): void {
        add_filter( 'set-screen-option', function ( $status, $option, $value ) {
            foreach ( self::$tables as $id => $config ) {
                if ( $option === self::per_page_option( (string) $id ) ) {
                    // The table's date preference is a second field in the
                    // same form, read now -- after core has checked the
                    // form's nonce -- and only for one of our own tables'
                    // forms, so another screen's cannot set it.
                    ListDate::save( ListDate::meta_key( (string) $id, $config ) );

                    return absint( $value );
                }
            }

            return $status;
        }, 10, 3 );
    }

    /**
     * The name a table's per-page preference is stored under.
     *
     * @param string $id Table identifier.
     *
     * @return string
     */
    private static function per_page_option( string $id ): string {
        return $id . '_per_page';
    }
}
