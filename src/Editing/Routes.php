<?php
/**
 * Editing Routes
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Editing;

use SugarCommerce\Tables\HiddenColumns;
use SugarCommerce\Tables\InlineEdit;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Utils\Runtime;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The REST routes a list's scripts call: a new order, a quick edit, the
 * columns switched off in Screen Options.
 *
 * Under the copy's own namespace (Runtime::rest_namespace(), named by
 * sugarcommerce/plugin's Build), one set per table, each with its own
 * capability check. The scripts send them through @wordpress/api-fetch, so
 * the REST nonce is sent and renewed for them.
 *
 * Bulk Edit is not here: it is a full-page form post that works without a
 * script, and stays one (InlineEditSave::process_bulk_edit()).
 *
 * @since 1.0.0
 */
final class Routes {

	/**
	 * Tables that have a route here, by id.
	 *
	 * @var array<string, true>
	 */
	private static array $tables = [];

	/**
	 * Whether the routes are hooked.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * A table's route, without the leading slash: what api-fetch's `path`
	 * takes.
	 *
	 * @param string $table Table id.
	 * @param string $route `reorder`, `quick-edit` or `hidden-columns`.
	 *
	 * @return string
	 */
	public static function path( string $table, string $route ): string {
		return Runtime::rest_namespace() . '/' . sanitize_key( $table ) . '/' . $route;
	}

	/**
	 * Hook the routes for a table, on every request: the script's request is
	 * not the page that drew the list.
	 *
	 * @param string $table Table id.
	 *
	 * @return void
	 */
	public static function boot( string $table ): void {
		self::$tables[ $table ] = true;

		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register each table's routes, for what it does.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		foreach ( array_keys( self::$tables ) as $table ) {
			$table  = (string) $table;
			$config = Tables::get_table( $table );

			if ( null === $config ) {
				continue;
			}

			$base = Runtime::rest_namespace();
			$slug = '/' . sanitize_key( $table );

			if ( null !== Reorder::config( $config ) ) {
				register_rest_route(
					$base,
					$slug . '/reorder',
					[
						'methods'             => 'POST',
						'callback'            => static fn( WP_REST_Request $request ) => self::reorder( $table, $request ),
						'permission_callback' => static fn(): bool => self::may_reorder( $table ),
						'args'                => [
							'ids' => [
								'type'     => 'array',
								'required' => true,
								'minItems' => 1,
								'maxItems' => 5000,
								'items'    => [
									'type'    => 'integer',
									'minimum' => 1,
								],
							],
						],
					]
				);
			}

			if ( InlineEdit::has_quick_edit( $config ) ) {
				register_rest_route(
					$base,
					$slug . '/quick-edit/(?P<id>\d+)',
					[
						'methods'             => 'POST',
						'callback'            => static fn( WP_REST_Request $request ) => self::quick_edit( $table, $request ),
						'permission_callback' => static fn(): bool => self::may_quick_edit( $table ),
						'args'                => [
							'id' => [
								'type'     => 'integer',
								'required' => true,
								'minimum'  => 1,
							],
						],
					]
				);
			}

			if ( HiddenColumns::any( $config ) ) {
				register_rest_route(
					$base,
					$slug . '/hidden-columns',
					[
						'methods'             => 'POST',
						'callback'            => static fn( WP_REST_Request $request ) => self::hidden_columns( $table, $request ),
						'permission_callback' => static fn(): bool => self::may_view( $table ),
						'args'                => [
							'screen' => [
								'type'     => 'string',
								'required' => true,
								'pattern'  => '^[a-z0-9_-]+$',
							],
							'hidden' => [
								'type'     => 'array',
								'required' => true,
								'items'    => [
									'type'    => 'string',
									'pattern' => '^[A-Za-z0-9_-]*$',
								],
							],
						],
					]
				);
			}
		}
	}

	/**
	 * Whether the signed-in user may put a table's rows in order.
	 *
	 * @param string $table Table id.
	 *
	 * @return bool
	 */
	public static function may_reorder( string $table ): bool {
		$config  = Tables::get_table( $table );
		$reorder = null !== $config ? Reorder::config( $config ) : null;

		return null !== $reorder && current_user_can( Reorder::capability( $config, $reorder ) );
	}

	/**
	 * Whether the signed-in user may quick edit a table's rows: its `edit`
	 * capability, not the one that shows the list.
	 *
	 * @param string $table Table id.
	 *
	 * @return bool
	 */
	public static function may_quick_edit( string $table ): bool {
		$config = Tables::get_table( $table );

		return null !== $config && InlineEdit::has_quick_edit( $config ) && current_user_can( InlineEditSave::edit_capability( $config ) );
	}

	/**
	 * Whether the signed-in user may see a table, and so choose its columns.
	 *
	 * @param string $table Table id.
	 *
	 * @return bool
	 */
	public static function may_view( string $table ): bool {
		$config = Tables::get_table( $table );

		return null !== $config && current_user_can( (string) ( $config['capabilities']['view'] ?? $config['capability'] ?? 'manage_options' ) );
	}

	/**
	 * Save a new order.
	 *
	 * @param string          $table   Table id.
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function reorder( string $table, WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$answer = Reorder::save( $table, array_map( 'absint', (array) $request->get_param( 'ids' ) ) );

		if ( ! $answer['ok'] ) {
			return new WP_Error( 'reorder_failed', (string) ( $answer['data']['message'] ?? '' ), [ 'status' => $answer['status'] ] );
		}

		return new WP_REST_Response( $answer['data'], 200 );
	}

	/**
	 * Save a quick edit, and answer with the row as it now reads and what to
	 * say about it.
	 *
	 * The fields are the editor's form, posted as it is (checklists as
	 * arrays); InlineEditSave keeps only the table's own fields and values
	 * each one offered.
	 *
	 * @param string          $table   Table id.
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function quick_edit( string $table, WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$input  = (array) $request->get_body_params() + (array) $request->get_json_params();
		$answer = InlineEditSave::quick_edit( $table, (int) $request->get_param( 'id' ), $input );

		if ( 200 !== $answer['status'] || '' === $answer['row'] ) {
			return new WP_Error(
				'quick_edit_failed',
				__( 'The change could not be saved.', 'sugarcommerce' ),
				[ 'status' => 200 === $answer['status'] ? 500 : $answer['status'] ]
			);
		}

		return new WP_REST_Response(
			[
				'row'    => $answer['row'],
				'notice' => $answer['notice'],
			],
			200
		);
	}

	/**
	 * Keep the columns switched off on a table's screen, as core's Screen
	 * Options does, and only on that screen.
	 *
	 * Core saves them through its own admin-ajax action; a deferred column
	 * needs them saved before the list is loaded again, so its script asks
	 * here, and the choice lands in the same user option core reads.
	 *
	 * @param string          $table   Table id.
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function hidden_columns( string $table, WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$config = (array) Tables::get_table( $table );
		$screen = sanitize_key( (string) $request->get_param( 'screen' ) );
		$slug   = sanitize_key( (string) ( $config['menu_slug'] ?? '' ) );

		// The table's own screen, whatever its parent: core names it
		// `{parent}_page_{menu_slug}`.
		if ( '' === $slug || ! str_ends_with( $screen, 'page_' . $slug ) ) {
			return new WP_Error( 'not_this_screen', __( 'That is not this list\'s screen.', 'sugarcommerce' ), [ 'status' => 400 ] );
		}

		$columns = array_keys( (array) ( $config['columns'] ?? [] ) );
		$hidden  = array_values(
			array_intersect(
				array_map( 'strval', (array) $request->get_param( 'hidden' ) ),
				array_map( 'strval', $columns )
			)
		);

		update_user_meta( get_current_user_id(), "manage{$screen}columnshidden", $hidden );

		return new WP_REST_Response( [ 'hidden' => $hidden ], 200 );
	}
}
