<?php
/**
 * Toggle Route
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables;

use SugarCommerce\Plugin\Assets;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use SugarCommerce\Tables\Utils\Runtime;
use SugarCommerce\Tables\Support\Request;
use SugarCommerce\Tables\Support\ScriptConfig;

defined( 'ABSPATH' ) || exit;

/**
 * Where a toggle with a `save` callable saves, and the script that saves it.
 *
 * One route per table -- `sugarcommerce-tables/v1/{table}/toggle/{id}` --
 * rather than one for every table on the site. Each bundled copy of this
 * library knows only its own tables, and two copies answering one shared
 * route would be two handlers for one request, of which only the first is
 * ever asked.
 *
 * The capability is checked here, against the column's own, before the
 * callable is called: hiding the button from somebody who may not press it
 * is the cell's job, and refusing the request is this one's, because a
 * REST route is anybody's to post to.
 *
 * @since 1.0.0
 */
final class ToggleRoute {

	/**
	 * Tables whose route is to be registered, by id.
	 *
	 * @var array<string, true>
	 */
	private static array $tables = [];

	/**
	 * Whether the hooks are in.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * A table's route, without the id: `sugarcommerce-tables/v1/{table}/toggle`.
	 *
	 * @param string $table Table id.
	 *
	 * @return string
	 */
	public static function path( string $table ): string {
		return Runtime::rest_namespace() . '/' . sanitize_key( $table ) . '/toggle';
	}

	/**
	 * Hook what a table with toggle columns needs.
	 *
	 * Called at registration, so the route exists on the REST request the
	 * button makes and not only on the page that drew it -- a table has to
	 * be registered by `init` for that.
	 *
	 * @param string $table  Table id.
	 * @param array  $config Table configuration.
	 *
	 * @return void
	 */
	public static function boot( string $table, array $config ): void {
		$toggles = Toggle::all( $config );

		if ( [] === $toggles ) {
			return;
		}

		foreach ( $toggles as $toggle ) {
			if ( is_callable( $toggle['save'] ) ) {
				self::$tables[ $table ] = true;
			}
		}

		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * Register each table's route.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		foreach ( array_keys( self::$tables ) as $table ) {
			register_rest_route(
				Runtime::rest_namespace(),
				'/' . sanitize_key( (string) $table ) . '/toggle/(?P<id>\d+)',
				[
					'methods'             => 'POST',
					'callback'            => static function ( WP_REST_Request $request ) use ( $table ) {
						return self::reply( (string) $table, $request );
					},
					'permission_callback' => static function ( WP_REST_Request $request ) use ( $table ): bool {
						$config = Tables::get_table( (string) $table );
						$toggle = null !== $config ? Toggle::config( $config, (string) $request->get_param( 'column' ) ) : null;

						return null !== $toggle && current_user_can( Toggle::capability( $config, $toggle ) );
					},
					'args'                => [
						'column' => [
							'type'     => 'string',
							'required' => true,
						],
						'on'     => [
							'type'     => 'boolean',
							'required' => true,
						],
					],
				]
			);
		}
	}

	/**
	 * Answer one press.
	 *
	 * @param string          $table   Table id.
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	private static function reply( string $table, WP_REST_Request $request ) {
		$answer = self::save(
			$table,
			(string) $request->get_param( 'column' ),
			(int) $request->get_param( 'id' ),
			rest_sanitize_boolean( $request->get_param( 'on' ) )
		);

		if ( 200 !== $answer['status'] ) {
			return new WP_Error( 'toggle_failed', $answer['body']['message'], [ 'status' => $answer['status'] ] );
		}

		return new WP_REST_Response( $answer['body'], 200 );
	}

	/**
	 * Save one toggle, and say what it now is.
	 *
	 * Apart from the request so it can be asked directly. `save` may answer
	 * true, false, a WP_Error -- its message is what the line says, when the
	 * toggle says why -- or an array with `on` (what it now is, if not what
	 * was asked) and `since` (the tooltip's first part, already worded).
	 * Without a `since` the row is read back through `callbacks.get_item`
	 * and asked the toggle's own `since`.
	 *
	 * @param string $table  Table id.
	 * @param string $column Column key.
	 * @param int    $id     The row.
	 * @param bool   $on     What it was asked to be.
	 *
	 * @return array{status: int, body: array<string, mixed>}
	 */
	public static function save( string $table, string $column, int $id, bool $on ): array {
		$config = Tables::get_table( $table );
		$toggle = null !== $config ? Toggle::config( $config, $column ) : null;

		if ( null === $config || null === $toggle || ! is_callable( $toggle['save'] ) ) {
			return self::refused( 404, __( 'There is nothing here to change.', 'sugarcommerce' ) );
		}

		if ( ! current_user_can( Toggle::capability( $config, $toggle ) ) ) {
			return self::refused( 403, __( 'Sorry, you are not allowed to change this.', 'sugarcommerce' ) );
		}

		if ( $id < 1 ) {
			return self::refused( 400, (string) $toggle['failed'] );
		}

		$result = call_user_func( $toggle['save'], $id, $on );

		if ( is_wp_error( $result ) ) {
			return self::refused( 400, (string) $result->get_error_message() );
		}

		if ( false === $result ) {
			return self::refused( 500, (string) $toggle['failed'] );
		}

		$state = is_array( $result ) && array_key_exists( 'on', $result ) ? (bool) $result['on'] : $on;
		$since = is_array( $result ) ? (string) ( $result['since'] ?? '' ) : '';

		if ( '' === $since && $state && is_callable( $toggle['since'] ) && is_callable( $config['callbacks']['get_item'] ?? null ) ) {
			$row   = call_user_func( $config['callbacks']['get_item'], $id );
			$since = null !== $row && false !== $row ? (string) call_user_func( $toggle['since'], $row ) : '';
		}

		/**
		 * Fires once a toggle cell has saved.
		 *
		 * @param int    $id     The row.
		 * @param string $column The column.
		 * @param bool   $state  What it now is.
		 */
		do_action( "sugarcommerce_table_toggled_{$table}", $id, $column, $state );

		return [
			'status' => 200,
			'body'   => [
				'on'         => $state,
				'since_text' => $since,
			],
		];
	}

	/**
	 * A refusal, in the shape save() answers with.
	 *
	 * @param int    $status  HTTP status.
	 * @param string $message Why.
	 *
	 * @return array{status: int, body: array<string, mixed>}
	 */
	private static function refused( int $status, string $message ): array {
		return [
			'status' => $status,
			'body'   => [ 'message' => $message ],
		];
	}

	/**
	 * The script and stylesheet, on a screen whose table has toggles.
	 *
	 * Through the assets package's class rather than its global function,
	 * for the reason FlyoutLink gives.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		[ $table, $config ] = self::current();

		if ( null === $config || [] === Toggle::all( $config ) ) {
			return;
		}

		Assets::enqueue_style( Runtime::handle( 'toggle' ), __FILE__, 'css/toggle.css' );
		Assets::enqueue_built_script( Runtime::handle( 'toggle' ), __FILE__, 'toggle' );

		ScriptConfig::attach(
			Runtime::handle( 'toggle' ),
			// No nonce: the script saves through api-fetch, which sends core's
			// REST nonce and renews it.
			[
				'columns' => Toggle::script_data( (string) $table, $config ),
			]
		);
	}

	/**
	 * The table on this screen, if it is one of ours.
	 *
	 * @return array{0: string|null, 1: array|null}
	 */
	public static function current(): array {
		$page = Request::text( 'page' );

		foreach ( Tables::get_all_tables() as $id => $config ) {
			if ( '' !== $page && ( $config['menu_slug'] ?? '' ) === $page ) {
				return [ (string) $id, $config ];
			}
		}

		return [ null, null ];
	}
}
