<?php
/**
 * Status Transitions
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables;

use SugarCommerce\Tables\Support\Row;

/**
 * Moving a row from one status to another, from its row or from a bulk
 * action, and saying so afterwards in the record's own words.
 *
 *     'transitions'  => [
 *         'active'   => [ 'disabled' => __( 'Block', 'my-plugin' ) ],
 *         'disabled' => [ 'active' => __( 'Unblock', 'my-plugin' ) ],
 *     ],
 *     'bulk_actions' => [
 *         'block'   => [ 'label' => __( 'Block', 'my-plugin' ), 'status' => 'disabled' ],
 *         'unblock' => [ 'label' => __( 'Unblock', 'my-plugin' ), 'status' => 'active' ],
 *     ],
 *     'callbacks'    => [ 'set_status' => 'my_plugin_set_customer_status' ],
 *
 * Keyed by the status the row is in, not by the status being offered.
 * Offering every status a row is not already in turned the row into a state
 * picker: an active product carried "Set draft" and "Archive" beside Edit
 * and Delete, four links to read on the way to the one that was wanted. A
 * row action should be the obvious next move, and for most things there is
 * exactly one -- a draft gets published, a published thing gets archived,
 * an archived thing comes back. Where there is genuinely more than one, as
 * with a pending order that either went through or did not, both are
 * listed; where there is none, the row says nothing.
 *
 * One contract for the write, whichever is given: `callbacks.set_status`,
 * ( int $id, string $status ), for anything with more to do than write the
 * column -- logging who changed it, firing a hook -- or else
 * `callbacks.update`, ( int $id, array $columns ), which the BerlinDB source
 * provides. Only a `false` is a failure.
 *
 * The list then says what happened by name, "Elena Okafor is now
 * blocked.", in the record's own word for the status (`status_label`, or
 * the view's label), lower-cased inside a sentence; and of a row already in
 * that status, "Order #98 is already complete", through Outcome::fail().
 *
 * @since 1.0.0
 */
final class Transitions {

	/**
	 * Build the row actions and fill in the bulk actions a table's status
	 * moves describe. Called once, at registration.
	 *
	 * @param string $id     Table identifier.
	 * @param array  $config Table configuration, otherwise complete.
	 *
	 * @return array The configuration.
	 * @since 1.0.0
	 */
	public static function expand( string $id, array $config ): array {
		$set = self::setter( $config );

		if ( null === $set ) {
			return $config;
		}

		// A row_actions callback draws its own; there is nothing to add to.
		if ( is_array( $config['row_actions'] ?? null ) ) {
			foreach ( (array) ( $config['transitions'] ?? [] ) as $from => $offers ) {
				foreach ( (array) $offers as $to => $label ) {
					$key = 'status_' . $from . '_' . $to;

					$config['row_actions'][ $key ] ??= [
						'label'     => $label,
						'condition' => static fn( $item ): bool => (string) $from === (string) Row::get( $item, 'status', '' ),
						'handler'   => static fn( $item_id ): bool => false !== $set( (int) $item_id, (string) $to ),

						// The status it sets, said back by name once it is
						// set: "Elena Okafor is now blocked."
						'to'        => (string) $to,
						'messages'  => self::messages( $config, (string) $to ),
					];
				}
			}
		}

		foreach ( (array) ( $config['bulk_actions'] ?? [] ) as $key => $action ) {
			if ( ! is_array( $action ) || ! isset( $action['status'] ) ) {
				continue;
			}

			$status = (string) $action['status'];

			$config['bulk_actions'][ $key ]['messages'] ??= self::messages( $config, $status );
			$config['bulk_actions'][ $key ]['callback'] ??= static function ( array $ids ) use ( $id, $set, $status ): void {
				$table = (array) Tables::get_table( $id );
				$get   = $table['callbacks']['get_item'] ?? null;

				foreach ( $ids as $item_id ) {
					$row = is_callable( $get ) ? call_user_func( $get, (int) $item_id ) : null;

					// One already there is not "now blocked": the notice
					// says so of it, by name.
					if ( $row && $status === (string) Row::get( $row, 'status', '' ) ) {
						self::already( $id, $row, $status );

						continue;
					}

					if ( false === $set( (int) $item_id, $status ) ) {
						Outcome::fail( (int) $item_id );
					}
				}
			};
		}

		return $config;
	}

	/**
	 * The words for setting a status, in ActionNotice's shape.
	 *
	 * One rule covers every status any list sets, in a row action, a bulk
	 * action, Bulk Edit or Quick Edit: no list of verbs to keep in step with
	 * the statuses.
	 *
	 * @param array  $config Table configuration.
	 * @param string $status The status being set.
	 *
	 * @return array<string, mixed>
	 * @since 1.0.0
	 */
	public static function messages( array $config, string $status ): array {
		$word   = self::word( $config, $status );
		$plural = (string) ( $config['labels']['plural'] ?? '' );

		return [
			/* translators: 1: kept as it is, for a name; 2: a status, such as "blocked". */
			'one'  => sprintf( __( '%1$s is now %2$s.', 'sugarcommerce' ), '%s', $word ),
			/* translators: 1: kept as it is, for a list of names; 2: a status, such as "blocked". */
			'some' => sprintf( __( '%1$s are now %2$s.', 'sugarcommerce' ), '%s', $word ),
			'many' => static fn( int $count ): string => sprintf(
				/* translators: 1: how many, 2: what they are, such as "orders", 3: a status, such as "complete". */
				_n( '%1$s %2$s is now %3$s.', '%1$s %2$s are now %3$s.', $count, 'sugarcommerce' ),
				number_format_i18n( $count ),
				$plural,
				$word
			),
		];
	}

	/**
	 * The words for an editor that set a status: Bulk Edit or Quick Edit,
	 * as a table's `messages` `edit` and `quick_edit`.
	 *
	 * Which status is only known once it is chosen, so this is a callable,
	 * which words it from what was set. An edit that set no status is said
	 * in the library's own words.
	 *
	 * @param string $id Table identifier.
	 *
	 * @return callable
	 * @since 1.0.0
	 */
	public static function edited_messages( string $id ): callable {
		return static function ( array $outcome ) use ( $id ): ?array {
			$config = (array) Tables::get_table( $id );
			$status = (string) ( $outcome['values']['status'] ?? '' );

			return ActionNotice::compose(
				$config,
				[
					'kind'   => (string) ( $outcome['kind'] ?? '' ),
					'ids'    => array_merge( (array) ( $outcome['done'] ?? [] ), array_keys( (array) ( $outcome['failed'] ?? [] ) ) ),
					'failed' => (array) ( $outcome['failed'] ?? [] ),
					'names'  => (array) ( $outcome['names'] ?? [] ),
				],
				'' === $status ? null : self::messages( $config, $status )
			);
		};
	}

	/**
	 * Tell the list a row was already in the status it was to be set to.
	 *
	 * For a handler of a plugin's own that sets a status, so it reads the
	 * same as the ones built here: "Order #98 is already complete".
	 *
	 * @param string $id     Table identifier.
	 * @param mixed  $row    The row.
	 * @param string $status The status.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function already( string $id, mixed $row, string $status ): void {
		$config = (array) Tables::get_table( $id );

		Outcome::fail(
			Row::id( $row ),
			sprintf(
				/* translators: 1: what a row is called, 2: a status, such as "complete". */
				__( '%1$s is already %2$s', 'sugarcommerce' ),
				ActionNotice::name( $config, $row ),
				self::word( $config, $status )
			)
		);
	}

	/**
	 * A status, as a word inside a sentence: "blocked", "complete".
	 *
	 * The record's own label for it, in its own context: a customer whose
	 * status is `disabled` has been blocked, so "Blocked". It used to say
	 * "Status changed to Disabled." of whoever it was, which named nobody
	 * and used a word the list never shows.
	 *
	 * @param array  $config Table configuration.
	 * @param string $status The status.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function word( array $config, string $status ): string {
		$label = $config['status_label'] ?? null;

		if ( is_callable( $label ) ) {
			$word = (string) call_user_func( $label, $status );
		} elseif ( is_string( $config['views'][ $status ] ?? null ) ) {
			$word = (string) $config['views'][ $status ];
		} else {
			$word = ucwords( str_replace( [ '_', '-' ], ' ', $status ) );
		}

		return mb_strtolower( wp_strip_all_tags( $word ) );
	}

	/**
	 * How a table writes a status, or null when it has said no way to.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return callable|null ( int $id, string $status ): mixed.
	 */
	private static function setter( array $config ): ?callable {
		$set = $config['callbacks']['set_status'] ?? null;

		if ( is_callable( $set ) ) {
			return static fn( int $id, string $status ) => call_user_func( $set, $id, $status );
		}

		$update = $config['callbacks']['update'] ?? null;

		if ( is_callable( $update ) ) {
			return static fn( int $id, string $status ) => call_user_func( $update, $id, [ 'status' => $status ] );
		}

		return null;
	}
}
