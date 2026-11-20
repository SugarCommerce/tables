<?php
/**
 * View State
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Support;

/**
 * What a list is being looked at as: its status view, search, filters,
 * sort and page -- and where to read that from after an action.
 *
 * An action ends in a redirect back to the list, and the redirect has to
 * land on the view the action was taken from. It used to be rebuilt from the
 * action's own request, which is the list's form -- and the form also holds
 * the bulk editor, whose fields share their names with the view's. Its
 * `status` select ("No Change" is -1) came after the hidden input carrying
 * the status view, so PHP kept the editor's: a bulk action taken from
 * Pending came back to `status=-1`, which shows everything. The page number
 * and the sort were never carried at all.
 *
 * So the view is read from the page the action was taken on: the
 * `_wp_http_referer` the bulk form carries, or the browser's Referer for a
 * row action's link -- core's edit.php sends a bulk action back to
 * wp_get_referer() for the same reason. Only when that is this list's own
 * page, and only the keys a view is made of, each sanitised: the referer is
 * the browser's to write. Without one the request is read, as before.
 *
 * @since 1.0.0
 */
final class ViewState {

	/**
	 * The arguments a view is made of, in the order a URL carries them.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return string[]
	 */
	public static function keys( array $config ): array {
		$keys = [ 'status', 's' ];

		foreach ( array_keys( (array) ( $config['filters'] ?? [] ) ) as $key ) {
			$keys[] = (string) $key;
		}

		foreach ( (array) ( $config['query_args'] ?? [] ) as $key => $value ) {
			$keys[] = is_numeric( $key ) ? (string) $value : (string) $key;
		}

		return array_values( array_unique( array_merge( $keys, [ 'orderby', 'order', 'paged' ] ) ) );
	}

	/**
	 * A view's arguments, out of a query string's worth of values.
	 *
	 * @param array                $config Table configuration.
	 * @param array<string, mixed> $source Unslashed query arguments.
	 * @param bool                 $paged  Whether to keep the page number. A
	 *                                     new filter is a new set of rows,
	 *                                     and page four of the old one may
	 *                                     not exist in it.
	 *
	 * @return array<string, string|int>
	 */
	public static function from( array $config, array $source, bool $paged = true ): array {
		$view = [];

		foreach ( self::keys( $config ) as $key ) {
			$value = $source[ $key ] ?? null;

			if ( ! is_scalar( $value ) || '' === (string) $value ) {
				continue;
			}

			switch ( $key ) {
				case 'paged':
					$page = absint( $value );

					if ( $paged && $page > 1 ) {
						$view['paged'] = $page;
					}
					break;

				case 'status':
				case 'orderby':
				case 'order':
					$clean = sanitize_key( (string) $value );

					// The bulk editor's "No Change", which is not a view.
					if ( '' !== $clean && '-1' !== $clean ) {
						$view[ $key ] = $clean;
					}
					break;

				default:
					$clean = sanitize_text_field( (string) $value );

					if ( '' !== $clean ) {
						$view[ $key ] = $clean;
					}
			}
		}

		return $view;
	}

	/**
	 * The view an action was taken from.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return array<string, string|int>
	 */
	public static function before_action( array $config ): array {
		$referer = function_exists( 'wp_get_referer' ) ? wp_get_referer() : false;

		if ( is_string( $referer ) && '' !== $referer ) {
			$query = [];

			parse_str( (string) wp_parse_url( $referer, PHP_URL_QUERY ), $query );

			// This list's page and no other. A referer from somewhere else
			// -- the dashboard's link to a row action -- says nothing about
			// how this list was being looked at.
			if ( (string) ( $query['page'] ?? '' ) === (string) ( $config['menu_slug'] ?? '' ) ) {
				return self::from( $config, $query );
			}
		}

		return self::from( $config, Request::all() );
	}
}
