<?php
/**
 * Bulk Actions
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Traits\Tables;

use SugarCommerce\Tables\ActionNotice;
use SugarCommerce\Tables\InlineEdit;
use SugarCommerce\Tables\Outcome;
use SugarCommerce\Tables\Table;

/**
 * Acting on a set of rows at once.
 *
 * Separated from the single-row handler because it is one long method with a
 * different shape: the ids arrive as an array, the capability check is the
 * one declared for bulk rather than for the individual action, and the
 * outcome is a count rather than a yes or no.
 *
 * The capability is checked against the rows being processed, not against the
 * buttons being drawn. Enforcing it only where the menu is built leaves the
 * endpoint open to anyone who can post to it.
 */
trait BulkProcessing {

    /**
     * Process bulk actions
     *
     * Handles bulk action form submissions. Verifies nonce, checks capability,
     * executes callback (if defined), fires hooks, and redirects.
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function process_bulk_actions( string $id, array $config ): void {
        // Determine which bulk action was selected
        $action = '';
        // Which action was chosen, and from which end of the table. Read
        // before the nonce because the nonce action is not known until the
        // bulk action is; verified immediately below, before anything is
        // done with it.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
        } elseif ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
        }

        if ( empty( $action ) ) {
            return;
        }

        // "Edit" is not a bulk action. It is the control that reveals the
        // inline editor, and the edit itself is applied by
        // InlineEditSave::process_bulk_edit() further down -- the same split
        // core makes in edit.php, where 'edit' gets its own branch instead of
        // going through the bulk action machinery.
        //
        // Falling through to here fired the bulk action hooks, redirected
        // with "N updated" and exited before the edit was ever applied. The
        // notice said it had worked and nothing had changed.
        if ( 'edit' === $action && InlineEdit::has_bulk_edit( $config ) ) {
            return;
        }

        // Whether this user may run a bulk action at all.
        //
        // Refused the same way the per-action check and the delete path
        // refuse: told, rather than quietly ignored. A silently dropped
        // action is indistinguishable from one that succeeded and did
        // nothing.
        //
        // get_bulk_actions() checks the same capability, but that decides
        // whether the dropdown is *drawn* — which is hiding the control
        // rather than refusing the action. The nonce below is a CSRF check
        // and not an authorisation one, and it is scoped to the plural label,
        // so two tables sharing one accept each other's. Anyone who could see
        // the page could run its bulk actions.
        if ( ! empty( $config['capabilities']['bulk'] )
            && ! current_user_can( $config['capabilities']['bulk'] ) ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to perform this action.', 'sugarcommerce' ) );
        }

        // The label as core signed it and the checkboxes were named.
        $plural = self::bulk_key( $config );

        // Verify nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- this is the check.
        if ( ! isset( $_REQUEST['_wpnonce'] ) ||
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-' . $plural ) ) {
            return;
        }

        // Get selected items. The checkboxes post an array of ids, so each
        // one is sanitized rather than the array: sanitize_text_field() hands
        // back an empty string when it is given an array, which would drop
        // every selection and make each bulk action quietly do nothing.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately above.
        $items = isset( $_REQUEST[ $plural ] )
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                ? array_map( 'sanitize_text_field', (array) wp_unslash( $_REQUEST[ $plural ] ) )
                : [];

        if ( empty( $items ) ) {
            return;
        }

        $items = array_map( 'absint', $items );

        // Get action configuration
        $action_config = $config['bulk_actions'][ $action ] ?? null;

        if ( ! $action_config ) {
            return;
        }

        // Normalize string config to array
        if ( is_string( $action_config ) ) {
            $action_config = [ 'label' => $action_config ];
        }

        // Check capability
        if ( isset( $action_config['capability'] ) ) {
            if ( ! current_user_can( $action_config['capability'] ) ) {
                wp_die( esc_html__( 'Sorry, you are not allowed to perform this action.', 'sugarcommerce' ) );
            }
        }

        // A few rows' names, read while they still exist -- a deleted row
        // cannot be named afterwards -- and a clean slate for the handlers
        // to report a row they could not change. See ActionNotice.
        $names = ActionNotice::names_before( $config, $items );

        Outcome::reset();

        /**
         * Fires when a bulk action is processed on a specific table
         *
         * @param array  $items  Selected item IDs.
         * @param string $action Bulk action key.
         *
         * @since 1.0.0
         *
         */
        do_action( "sugarcommerce_table_bulk_action_{$id}", $items, $action );

        /**
         * Fires for a specific bulk action on a specific table
         *
         * @param array $items Selected item IDs.
         *
         * @since 1.0.0
         *
         */
        do_action( "sugarcommerce_table_bulk_action_{$id}_{$action}", $items );

        // Execute callback if defined
        $redirect_args = [];

        if ( isset( $action_config['callback'] ) && is_callable( $action_config['callback'] ) ) {
            $result = call_user_func( $action_config['callback'], $items );

            // Handle different return types
            if ( is_array( $result ) ) {
                $redirect_args = $result;
            } elseif ( is_int( $result ) ) {
                $redirect_args = [ 'updated' => $result ];
            } elseif ( is_bool( $result ) ) {
                $redirect_args = [ 'updated' => $result ? count( $items ) : 0 ];

                // Nothing changed: every row failed, with no reason given.
                if ( ! $result ) {
                    foreach ( $items as $item_id ) {
                        Outcome::fail( $item_id );
                    }
                }
            }
        } else {
            // No callback — assume success
            $redirect_args = [ 'updated' => count( $items ) ];
        }

        $redirect_args = self::bulk_notice_args( $config, $action, $action_config, $items, $names, $redirect_args );

        // Back to the view the action was taken from, with what it says.
        wp_safe_redirect( add_query_arg( $redirect_args, self::get_clean_base_url( $config ) ) );
        exit;
    }

    /**
     * What a bulk action's redirect carries for its notice.
     *
     * An action worded the old way -- a `notice` and no `messages` -- keeps
     * its count and its key in the address, which is what that wording
     * reads. Every other gets a sentence kept on the server and a token to
     * fetch it by, and nothing else of ours: the count the callback
     * returned was a number for the old notice. Anything else a callback
     * returned is its own and is passed on.
     *
     * @param array              $config        Table configuration.
     * @param string             $action        The action's key.
     * @param array              $action_config The action's configuration.
     * @param array<int>         $items         The rows.
     * @param array<int, string> $names         Names read before it ran.
     * @param array              $redirect_args What the callback returned.
     *
     * @return array
     */
    private static function bulk_notice_args( array $config, string $action, array $action_config, array $items, array $names, array $redirect_args ): array {
        if ( isset( $action_config['notice'] ) && ! isset( $action_config['messages'] ) ) {
            $redirect_args['_bulk_action'] = $action;

            return $redirect_args;
        }

        unset( $redirect_args['updated'], $redirect_args['deleted'] );

        return $redirect_args + ActionNotice::redirect_args(
            $config,
            [
				'action' => $action,
				'kind'   => 'delete' === $action ? 'delete' : 'bulk',
				'ids'    => $items,
				'failed' => Outcome::failed(),
				'names'  => $names,
            ],
            ActionNotice::messages( $config, $action, $action_config )
        );
    }
}
