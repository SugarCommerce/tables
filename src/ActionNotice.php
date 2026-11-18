<?php
/**
 * Action Notices
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables;

/**
 * What the list says after an action: what happened, to what, in the
 * record's own words.
 *
 * "Status changed to Disabled." said nothing about whom, and on the
 * Customers list "disabled" is "blocked". Core's own lists only manage
 * "3 posts moved to the Trash." because all they carry across the redirect
 * is a count in the address -- `trashed=3` -- and a name cannot go there:
 * it is somebody's personal data in a URL, in the browser's history and in
 * every log the request passes, and it is the browser's to rewrite.
 *
 * So the sentence is made where the names are known -- in the request
 * that did the work, from the rows themselves -- and kept on the server for
 * the person who did it: a transient keyed by their user id and a random
 * token, five minutes long. Only the token goes in the redirect. The page
 * shows the sentence once and deletes it, so a reload, a copied link or
 * another user with the same address finds nothing.
 *
 * The words are the consumer's, per action, as `messages`:
 *
 *     'messages' => [
 *         'one'    => '%s was blocked.',       // %s: the row's name
 *         'some'   => '%s were blocked.',      // %s: up to three names, "A, B and C"
 *         'many'   => '%s customers blocked.', // %s: how many; or a callable( int ): string
 *         'failed' => '%1$s could not be changed: %2$s.', // optional
 *     ]
 *
 * or a callable given the outcome -- `action`, `kind`, `done` (ids),
 * `failed` (id => reason), `names` (id => name) and `values` (what a bulk
 * or quick edit set) -- answering a string or `[ 'message', 'type' ]`. On a
 * row or bulk action it sits in the action's own config; for the bulk
 * editor, Quick Edit and Delete it is the table's `messages` under `edit`,
 * `quick_edit` and `delete`, and an action with none of its own may be
 * worded there too. Without any, the library says "Synthwave Stems
 * updated." in its own words.
 *
 * A row is named by the table's own title -- the primary column's `title`
 * callback, as the list draws it -- or by an `item_name` callback when the
 * title is not what a sentence wants ("1042" where "Order #1042" reads).
 *
 * @since 1.0.0
 */
final class ActionNotice {

	/**
	 * The query argument the token rides in.
	 */
	public const ARG = '_notice';

	/**
	 * How long a sentence waits to be shown, in seconds: a redirect, and
	 * then some.
	 */
	private const TTL = 300;

	/**
	 * Up to how many rows are named. Past it, a count.
	 */
	private const FEW = 3;

	/**
	 * What a row is called.
	 *
	 * @param array $config Table configuration.
	 * @param mixed $item   The row.
	 *
	 * @return string Plain text.
	 */
	public static function name( array $config, mixed $item ): string {
		$name    = '';
		$column  = (string) ( $config['primary_column'] ?? '' );
		$defined = $config['columns'][ $column ] ?? null;

		if ( isset( $config['item_name'] ) && is_callable( $config['item_name'] ) ) {
			$name = (string) call_user_func( $config['item_name'], $item );
		} elseif ( is_array( $defined ) && isset( $defined['title'] ) && is_callable( $defined['title'] ) ) {
			$name = (string) call_user_func( $defined['title'], $item );
		} elseif ( '' !== $column && Row::has( $item, $column ) ) {
			$name = (string) Row::get( $item, $column, '' );
		}

		foreach ( [ 'title', 'name', 'label' ] as $property ) {
			if ( '' !== trim( $name ) ) {
				break;
			}

			$name = (string) Row::get( $item, $property, '' );
		}

		$name = trim( wp_strip_all_tags( html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) ) );

		return '' !== $name ? $name : '#' . Row::id( $item );
	}

	/**
	 * What some rows are called, by id, through the table's get_item.
	 *
	 * @param array      $config Table configuration.
	 * @param array<int> $ids    The rows.
	 *
	 * @return array<int, string>
	 */
	public static function names( array $config, array $ids ): array {
		$get   = $config['callbacks']['get_item'] ?? null;
		$names = [];

		foreach ( $ids as $id ) {
			$item = is_callable( $get ) ? call_user_func( $get, (int) $id ) : null;

			$names[ (int) $id ] = $item ? self::name( $config, $item ) : '#' . (int) $id;
		}

		return $names;
	}

	/**
	 * The names worth reading before an action runs: a few rows' only.
	 *
	 * Before, because a deleted row cannot be named afterwards; and only a
	 * few, because forty would be forty queries for a sentence that says
	 * "40 orders".
	 *
	 * @param array      $config Table configuration.
	 * @param array<int> $ids    The rows.
	 *
	 * @return array<int, string>
	 */
	public static function names_before( array $config, array $ids ): array {
		return count( $ids ) <= self::FEW ? self::names( $config, $ids ) : [];
	}

	/**
	 * The wording an action asked for: its own, or the table's.
	 *
	 * @param array  $config        Table configuration.
	 * @param string $action        The action's key.
	 * @param mixed  $action_config The action's own configuration.
	 *
	 * @return array|callable|null
	 */
	public static function messages( array $config, string $action, mixed $action_config = null ): mixed {
		if ( is_array( $action_config ) && isset( $action_config['messages'] ) ) {
			return $action_config['messages'];
		}

		return $config['messages'][ $action ] ?? null;
	}

	/**
	 * The sentence for what happened.
	 *
	 * @param array                $config   Table configuration.
	 * @param array                $outcome  `action`, `kind`, `ids`, `failed`, `names`, `values`.
	 * @param array|callable|null  $messages The wording.
	 *
	 * @return array{message: string, type: string}|null Null for nothing to say.
	 */
	public static function compose( array $config, array $outcome, mixed $messages ): ?array {
		$ids    = array_map( 'intval', (array) ( $outcome['ids'] ?? [] ) );
		$failed = (array) ( $outcome['failed'] ?? [] );
		$done   = array_values( array_diff( $ids, array_keys( $failed ) ) );

		if ( [] === $done && [] === $failed ) {
			return null;
		}

		// Named when few, and every failure that has no reason of its own.
		$names = (array) ( $outcome['names'] ?? [] );
		$want  = array_diff(
			count( $done ) <= self::FEW ? array_merge( $done, array_keys( $failed ) ) : array_keys( $failed ),
			array_keys( $names )
		);

		$names += self::names( $config, $want );

		$type = [] === $failed ? 'success' : ( [] === $done ? 'error' : 'warning' );

		if ( is_callable( $messages ) ) {
			$said = call_user_func(
				$messages,
				[
					'action' => (string) ( $outcome['action'] ?? '' ),
					'kind'   => (string) ( $outcome['kind'] ?? '' ),
					'done'   => $done,
					'failed' => $failed,
					'names'  => $names,
					'values' => (array) ( $outcome['values'] ?? [] ),
				]
			);

			if ( is_array( $said ) ) {
				return '' === (string) ( $said['message'] ?? '' ) ? null : [
					'message' => (string) $said['message'],
					'type'    => (string) ( $said['type'] ?? $type ),
				];
			}

			return '' === (string) $said ? null : [
				'message' => (string) $said,
				'type'    => $type,
			];
		}

		$words = is_array( $messages ) ? $messages : [];
		$parts = [];

		if ( [] !== $done ) {
			$parts[] = self::done( $config, (string) ( $outcome['kind'] ?? '' ), $done, $names, $words );
		}

		if ( [] !== $failed ) {
			$parts[] = self::failures( $failed, $names, $words );
		}

		return [
			'message' => implode( ' ', $parts ),
			'type'    => $type,
		];
	}

	/**
	 * What was done, as one sentence.
	 *
	 * @param array                $config Table configuration.
	 * @param string               $kind   `row`, `bulk`, `edit`, `quick_edit` or `delete`.
	 * @param array<int>           $done   The rows changed.
	 * @param array<int, string>   $names  Names by id.
	 * @param array<string, mixed> $words  The consumer's wording.
	 *
	 * @return string
	 */
	private static function done( array $config, string $kind, array $done, array $names, array $words ): string {
		$count   = count( $done );
		$deleted = 'delete' === $kind;

		if ( 1 === $count ) {
			$format = (string) ( $words['one'] ?? ( $deleted
				/* translators: %s: what a row is called, such as "Synthwave Stems". */
				? __( '%s deleted.', 'sugarcommerce' )
				/* translators: %s: what a row is called, such as "Synthwave Stems". */
				: __( '%s updated.', 'sugarcommerce' ) ) );

			return sprintf( $format, $names[ $done[0] ] ?? '#' . $done[0] );
		}

		if ( $count <= self::FEW ) {
			$format = (string) ( $words['some'] ?? ( $deleted
				/* translators: %s: what some rows are called, such as "A, B and C". */
				? __( '%s deleted.', 'sugarcommerce' )
				/* translators: %s: what some rows are called, such as "A, B and C". */
				: __( '%s updated.', 'sugarcommerce' ) ) );

			return sprintf( $format, self::listed( array_map( static fn( int $id ): string => $names[ $id ] ?? '#' . $id, $done ) ) );
		}

		$many = $words['many'] ?? null;

		if ( is_callable( $many ) ) {
			return (string) call_user_func( $many, $count );
		}

		if ( is_string( $many ) && '' !== $many ) {
			return sprintf( $many, number_format_i18n( $count ) );
		}

		return sprintf(
			$deleted
				/* translators: 1: how many rows, 2: what they are called, plural. */
				? __( '%1$s %2$s deleted.', 'sugarcommerce' )
				/* translators: 1: how many rows, 2: what they are called, plural. */
				: __( '%1$s %2$s updated.', 'sugarcommerce' ),
			number_format_i18n( $count ),
			(string) ( $config['labels']['plural'] ?? 'items' )
		);
	}

	/**
	 * What could not be done, and why, as one sentence.
	 *
	 * @param array<int, string>   $failed Reasons by id.
	 * @param array<int, string>   $names  Names by id.
	 * @param array<string, mixed> $words  The consumer's wording.
	 *
	 * @return string
	 */
	private static function failures( array $failed, array $names, array $words ): string {
		$why = [];

		foreach ( $failed as $id => $reason ) {
			$why[] = rtrim( '' !== trim( (string) $reason ) ? trim( (string) $reason ) : ( $names[ $id ] ?? '#' . $id ), '.' );
		}

		$count  = count( $failed );
		$format = (string) ( $words['failed']
			/* translators: 1: how many rows could not be changed, 2: why, or which. */
			?? _n( '%1$s could not be changed: %2$s.', '%1$s could not be changed: %2$s.', $count, 'sugarcommerce' ) );

		// Past a few, the first few reasons and a word for the rest.
		$listed = $count > self::FEW
			/* translators: %s: the first few reasons a row could not be changed. */
			? sprintf( __( '%s and others', 'sugarcommerce' ), implode( ', ', array_slice( $why, 0, self::FEW ) ) )
			: self::listed( $why );

		return sprintf( $format, number_format_i18n( $count ), $listed );
	}

	/**
	 * Names as a list: "A", "A and B", "A, B and C".
	 *
	 * @param array<int, string> $items The names.
	 *
	 * @return string
	 */
	private static function listed( array $items ): string {
		$items = array_values( $items );

		if ( count( $items ) < 2 ) {
			return (string) ( $items[0] ?? '' );
		}

		$last = array_pop( $items );

		/* translators: 1: names separated by commas, 2: the last name. */
		return sprintf( __( '%1$s and %2$s', 'sugarcommerce' ), implode( ', ', $items ), $last );
	}

	/**
	 * Keep a sentence for whoever is signed in, and the token to fetch it by.
	 *
	 * @param array{message: string, type: string} $notice The sentence.
	 *
	 * @return string The token.
	 */
	public static function store( array $notice ): string {
		$token = bin2hex( random_bytes( 8 ) );

		set_transient( self::key( $token ), $notice, self::TTL );

		return $token;
	}

	/**
	 * A kept sentence, once: read and forgotten.
	 *
	 * @param string $token The token.
	 *
	 * @return array{message: string, type: string}|null
	 */
	public static function take( string $token ): ?array {
		if ( 1 !== preg_match( '/^[a-f0-9]{16}$/', $token ) ) {
			return null;
		}

		$notice = get_transient( self::key( $token ) );

		delete_transient( self::key( $token ) );

		return is_array( $notice ) && '' !== (string) ( $notice['message'] ?? '' ) ? $notice : null;
	}

	/**
	 * Say what happened, keep it, and hand back what the redirect carries.
	 *
	 * @param array               $config   Table configuration.
	 * @param array               $outcome  See compose().
	 * @param array|callable|null $messages The wording.
	 *
	 * @return array<string, string> `[ '_notice' => token ]`, or empty.
	 */
	public static function redirect_args( array $config, array $outcome, mixed $messages ): array {
		$notice = self::compose( $config, $outcome, $messages );

		return null === $notice ? [] : [ self::ARG => self::store( $notice ) ];
	}

	/**
	 * A kept sentence as a notice.
	 *
	 * @param array{message: string, type: string} $notice The sentence.
	 *
	 * @return string Escaped markup.
	 */
	public static function render( array $notice ): string {
		$type = in_array( $notice['type'] ?? '', [ 'success', 'warning', 'error', 'info' ], true ) ? $notice['type'] : 'success';

		return sprintf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( (string) $notice['message'] )
		);
	}

	/**
	 * The transient a token names, for the signed-in user only.
	 *
	 * @param string $token The token.
	 *
	 * @return string
	 */
	private static function key( string $token ): string {
		return 'list_table_notice_' . get_current_user_id() . '_' . $token;
	}
}
