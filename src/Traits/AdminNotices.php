<?php
/**
 * Admin Notices
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Traits;

use SugarCommerce\Tables\ActionNotice;
use SugarCommerce\Tables\Request;
use SugarCommerce\Tables\Table;

/**
 * What the page says after something happened.
 *
 * Read back out of the query string, because the action that did the work
 * redirected before rendering. Every message is looked up from the table's
 * own labels rather than assembled from fragments, so it can be translated as
 * a sentence — and so that a count of one does not read "1 items".
 */
trait AdminNotices {

    /**
     * Render admin notices
     *
     * Displays success/error messages based on URL parameters from action
     * processing. Supports custom notices defined in row_actions and
     * bulk_actions via the 'notice' configuration key.
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function render_admin_notices( string $id, array $config ): void {
        // What an action said it did, kept on the server for whoever did it
        // and fetched once by the token in the address. See ActionNotice.
        $kept = Request::filled( ActionNotice::ARG ) ? ActionNotice::take( Request::key( ActionNotice::ARG ) ) : null;

        if ( null !== $kept ) {
            echo wp_kses_post( ActionNotice::render( $kept ) );
        }

        $singular = $config['labels']['singular'] ?? 'item';
        $plural   = $config['labels']['plural'] ?? 'items';

        // Row action notices (custom handler results)
        $row_action  = Request::key( '_row_action' );
        $bulk_action = Request::key( '_bulk_action' );

        if ( ! empty( $row_action ) && isset( $config['row_actions'][ $row_action ]['notice'] ) ) {
            self::render_action_notice( $config['row_actions'][ $row_action ]['notice'] );
        } elseif ( empty( $bulk_action ) ) {
            // The generic notices: for a request that named no action, and
            // for a row action that has no wording of its own. Leaving the
            // second out meant a row action without a `notice` changed the
            // row and said nothing at all -- the page reloaded and the only
            // sign anything had happened was the row itself.

            // Deleted notice
            if ( Request::has( 'deleted' ) ) {
                $count = Request::count( 'deleted' );

                if ( $count > 0 ) {
                    $message = sprintf(
                            /* translators: 1: number of items, 2: what they are called, singular or plural */
                            __( '%1$s %2$s deleted successfully.', 'sugarcommerce' ),
                            number_format_i18n( $count ),
                            1 === $count ? $singular : $plural
                    );
                    $type    = 'success';
                } else {
                    $message = __( 'Delete failed. Please try again.', 'sugarcommerce' );
                    $type    = 'error';
                }

                self::notice( $message, $type );
            }

            // Updated notice (generic, when no action-specific notice exists)
            if ( Request::has( 'updated' ) ) {
                $count = Request::count( 'updated' );

                if ( $count > 0 ) {
                    $message = sprintf(
                            /* translators: 1: number of items, 2: what they are called, singular or plural */
                            __( '%1$s %2$s updated successfully.', 'sugarcommerce' ),
                            number_format_i18n( $count ),
                            1 === $count ? $singular : $plural
                    );

                    self::notice( $message, 'success' );
                }
            }
        }

        // Bulk action notices
        if ( ! empty( $bulk_action ) && isset( $config['bulk_actions'][ $bulk_action ] ) ) {
            $bulk_config = $config['bulk_actions'][ $bulk_action ];

            if ( is_array( $bulk_config ) && isset( $bulk_config['notice'] ) ) {
                // Get count from redirect args
                $count = Request::has( 'updated' ) ? Request::count( 'updated' ) : Request::count( 'deleted' );
                self::render_action_notice( $bulk_config['notice'], $count );
            } elseif ( Request::has( 'updated' ) || Request::has( 'deleted' ) ) {
                // Fallback to generic notice for bulk actions without custom notice
                $count = Request::has( 'updated' ) ? Request::count( 'updated' ) : Request::count( 'deleted' );

                if ( $count > 0 ) {
                    $message = sprintf(
                            /* translators: 1: number of items, 2: what they are called, singular or plural */
                            __( '%1$s %2$s updated successfully.', 'sugarcommerce' ),
                            number_format_i18n( $count ),
                            1 === $count ? $singular : $plural
                    );

                    self::notice( $message, 'success' );
                }
            }
        }

        // Error notice (always show, not action-specific)
        if ( 'action_failed' === Request::text( 'error' ) && ! isset( $config['row_actions'][ $row_action ]['notice'] ) ) {
            // A row action that failed and has no wording for that either.
            self::notice( __( 'That could not be done. Please try again.', 'sugarcommerce' ), 'error' );
        } elseif ( Request::has( 'error' ) ) {
            // A code, never the words: anyone can send a link, so text read
            // from the address would let them put an official-looking
            // notice of their own wording on this screen. A code the table
            // declared in `errors` gets its wording; any other says only
            // that something failed.
            $error = Request::key( 'error' );

            if ( '' !== $error && 'action_failed' !== $error ) {
                $known = $config['errors'][ $error ] ?? null;

                self::notice( is_string( $known ) && '' !== $known ? $known : __( 'That could not be done. Please try again.', 'sugarcommerce' ), 'error' );
            }
        }

        /**
         * Filter custom admin notices for a specific table
         *
         * Starts empty. The unscoped twin of this filter used to seed it, and
         * removing that left the variable undefined — a warning above the
         * table on every request, and every notice a consumer added dropped
         * on the floor.
         *
         * @param array $notices Array of notices (see above for format).
         * @param array $config  Table configuration.
         *
         * @since 1.0.0
         *
         */
        $custom_notices = (array) apply_filters( "sugarcommerce_table_admin_notices_{$id}", [], $config );

        foreach ( $custom_notices as $notice ) {
            if ( empty( $notice['message'] ) ) {
                continue;
            }

            self::notice( (string) $notice['message'], (string) ( $notice['type'] ?? 'info' ), (bool) ( $notice['dismissible'] ?? true ) );
        }
    }

    /**
     * Render a notice from action configuration
     *
     * Handles the 'notice' key from row_actions and bulk_actions config.
     * Supports both simple array format and callable format.
     *
     * Simple format:
     * 'notice' => [
     *     'success' => 'Customer status updated.',
     *     'error'   => 'Failed to update status.',
     * ]
     *
     * With count placeholder (for bulk actions):
     * 'notice' => [
     *     'success' => '%d customers activated.',
     *     'error'   => 'Failed to activate customers.',
     * ]
     *
     * @param array|callable $notice_config Notice configuration.
     * @param int            $count         Optional count for bulk action notices.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function render_action_notice( $notice_config, int $count = 0 ): void {
        // Callable format — let the callback determine the notice
        if ( is_callable( $notice_config ) ) {
            $notice = call_user_func( $notice_config, Request::all() );
            if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
                $type = $notice['type'] ?? 'success';
                self::notice( (string) $notice['message'], (string) $type );
            }

            return;
        }

        // Array format with success/error keys
        if ( ! is_array( $notice_config ) ) {
            return;
        }

        // Determine success or error based on URL params
        $is_error = Request::has( 'error' ) || ( Request::has( 'updated' ) && 0 === Request::count( 'updated' ) );

        if ( $is_error && ! empty( $notice_config['error'] ) ) {
            self::notice( (string) $notice_config['error'], 'error' );
        } elseif ( ! $is_error && ! empty( $notice_config['success'] ) ) {
            $message = $notice_config['success'];

            // Replace %d placeholder with count if present
            if ( $count > 0 && str_contains( $message, '%d' ) ) {
                $message = sprintf( $message, $count );
            }

            self::notice( (string) $message, 'success' );
        }
    }

    /**
     * Print one notice, in core's markup.
     *
     * wp_admin_notice() is the markup these were written out by hand in,
     * and runs core's `wp_admin_notice_args` and `wp_admin_notice_markup`
     * filters. Core does not escape the message, so it is escaped here: none
     * of these is HTML.
     *
     * @param string $message     The sentence.
     * @param string $type        `success`, `error`, `warning` or `info`.
     * @param bool   $dismissible Whether it can be closed.
     *
     * @return void
     * @since 1.0.0
     */
    private static function notice( string $message, string $type, bool $dismissible = true ): void {
        wp_admin_notice(
            esc_html( $message ),
            [
                'type'        => sanitize_html_class( $type ),
                'dismissible' => $dismissible,
            ]
        );
    }
}
