<?php
/**
 * Search Filters
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables;

use SugarCommerce\Fields\Assets;
use SugarCommerce\Fields\Rest\SearchController;
use SugarCommerce\Fields\Utils\Runtime;
use SugarCommerce\Tables\Support\Request;
use SugarCommerce\Tables\Support\ViewState;

/**
 * A filter whose choices are too many to list: `'type' => 'search'`.
 *
 * A dropdown of every product is a dropdown nobody can use past a few
 * hundred, and a page that has to fetch all of them to draw it. This one is
 * sugarcommerce/fields' searchable select, asking a search source over REST as
 * somebody types -- the same source a settings field or a panel would name,
 * registered once with `Sources::shared()->register()` -- so it works with
 * ten products or ten thousand:
 *
 *     'product_id' => [
 *         'type'           => 'search',
 *         'label'          => 'All Products',   // what it says with nothing chosen
 *         'source'         => 'my_products',    // a fields search source
 *         'label_callback' => [ My::class, 'search_products' ],
 *         'chip'           => 'Product: %s',    // optional
 *         'search_args'    => [],               // optional, sent to the source
 *         'min_chars'      => 1,                // optional
 *         'clears'         => [ 'price_id' ],   // optional: taken off with it
 *     ],
 *
 * `label_callback` is sugarcommerce/fields' own contract for an ajax field, so one
 * callable can serve both: called `( '', [ $value ], $search_args )` and
 * answering `[ [ 'id' => ..., 'text' => ... ] ]` or `[ id => text ]`. It
 * names the chosen value, for the select and for its chip.
 *
 * With no `source` the filter is not drawn at all. It is still carried --
 * as a hidden input in the form, through the views, the pager and every
 * redirect -- and still shown as a chip. That is the shape of a narrowing
 * that is reached from a link rather than chosen here: one customer's
 * orders, one price of a product.
 *
 * Either way, while it is on:
 *
 * - **A chip above the table** says what the list is narrowed to, and its
 *   cross takes that one filter off and leaves the others.
 * - **The status links keep it.** A dropdown filter is left behind by a
 *   status link, as core's are; this is the narrowing the list is about,
 *   so "Pending" means pending orders for this product. Their counts are
 *   asked for with it too -- the `get_counts` callback is handed the
 *   filters' query arguments -- so each link says how many it leads to.
 *
 * @since 1.0.0
 */
final class SearchFilter {

	/**
	 * Whether the REST route has been asked for.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Whether a filter is one of these.
	 *
	 * @param mixed $filter A filter's configuration.
	 *
	 * @return bool
	 */
	public static function is( mixed $filter ): bool {
		return is_array( $filter ) && 'search' === ( $filter['type'] ?? '' );
	}

	/**
	 * Whether a filter is drawn as a searchable select.
	 *
	 * @param mixed $filter A filter's configuration.
	 *
	 * @return bool
	 */
	public static function searches( mixed $filter ): bool {
		return self::is( $filter ) && '' !== (string) ( $filter['source'] ?? '' );
	}

	/**
	 * Whether any of a table's filters is drawn as a searchable select.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return bool
	 */
	public static function any( array $config ): bool {
		foreach ( (array) ( $config['filters'] ?? [] ) as $filter ) {
			if ( self::searches( $filter ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The search filters that are on, by key, as the address has them.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return array<string, string>
	 */
	public static function active( array $config ): array {
		$active = [];

		foreach ( (array) ( $config['filters'] ?? [] ) as $key => $filter ) {
			$value = self::is( $filter ) ? Request::text( (string) $key ) : '';

			if ( '' !== $value ) {
				$active[ (string) $key ] = $value;
			}
		}

		return $active;
	}

	/**
	 * The search filters that are on, as query arguments -- what the
	 * view's own arguments carry for them, `apply_callback` and all.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function query_args( array $config ): array {
		$args = [];

		foreach ( self::active( $config ) as $key => $value ) {
			$filter = $config['filters'][ $key ];

			if ( isset( $filter['apply_callback'] ) && is_callable( $filter['apply_callback'] ) ) {
				call_user_func_array( $filter['apply_callback'], [ &$args, $value ] );
			} else {
				$args[ $key ] = $value;
			}
		}

		return $args;
	}

	/**
	 * What a chosen value is called.
	 *
	 * @param array  $filter The filter.
	 * @param string $value  What is chosen.
	 *
	 * @return string Not escaped. The value itself when nothing names it.
	 */
	public static function name( array $filter, string $value ): string {
		$callback = $filter['label_callback'] ?? null;

		if ( '' === $value || ! is_callable( $callback ) ) {
			return $value;
		}

		$answer = call_user_func( $callback, '', [ $value ], (array) ( $filter['search_args'] ?? [] ) );

		if ( is_string( $answer ) ) {
			return '' !== $answer ? $answer : $value;
		}

		foreach ( (array) $answer as $id => $result ) {
			if ( is_array( $result ) && (string) ( $result['id'] ?? '' ) === $value ) {
				return (string) ( $result['text'] ?? $value );
			}

			if ( is_scalar( $result ) && (string) $id === $value ) {
				return (string) $result;
			}
		}

		return $value;
	}

	/**
	 * The control, in the filter bar.
	 *
	 * sugarcommerce/fields' searchable select: a real <select> holding only what is
	 * chosen, which sugarcommerce/fields' script turns into a combobox that searches
	 * the source as somebody types, a keystroke's pause apart, with the
	 * arrow keys and Enter. Without the script it is still a select that
	 * submits what it holds.
	 *
	 * @param string $key    The filter's key, which is also its argument.
	 * @param array  $filter The filter.
	 *
	 * @return string Escaped markup.
	 */
	public static function control( string $key, array $filter ): string {
		$value = Request::text( $key );

		if ( ! self::searches( $filter ) ) {
			return '' === $value ? '' : sprintf(
				'<input type="hidden" name="%s" value="%s">',
				esc_attr( $key ),
				esc_attr( $value )
			);
		}

		$label  = (string) ( $filter['label'] ?? '' );
		$id     = 'filter-by-' . $key;
		$args   = (array) ( $filter['search_args'] ?? [] );
		$chosen = '' === $value ? '' : sprintf(
			'<option value="%s" selected>%s</option>',
			esc_attr( $value ),
			esc_html( self::name( $filter, $value ) )
		);

		return sprintf(
			'<label class="screen-reader-text" for="%1$s">%2$s</label>'
			. '<select name="%3$s" id="%1$s" class="field-kit__relational sugarcommerce-table-search-filter"' . Runtime::stamp()
			. ' data-search-endpoint="%4$s" data-search-source="%5$s" data-search-nonce="%6$s" data-min-chars="%7$d"%8$s>'
			. '<option value="">%2$s</option>%9$s</select>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $key ),
			esc_url( rest_url( Runtime::rest_namespace() . '/search' ) ),
			esc_attr( (string) $filter['source'] ),
			esc_attr( wp_create_nonce( 'wp_rest' ) ),
			max( 0, (int) ( $filter['min_chars'] ?? 1 ) ),
			[] === $args ? '' : ' data-search-args="' . esc_attr( (string) wp_json_encode( $args ) ) . '"',
			$chosen
		);
	}

	/**
	 * The chips above the table: what it is narrowed to, each with a cross.
	 *
	 * The cross is a link to the same view without that filter -- and
	 * without the page number, since fewer filters is a different set of
	 * rows -- keeping the status, the search, the sort and the rest.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return string Escaped markup; empty when no search filter is on.
	 */
	public static function chips( array $config ): string {
		$active = self::active( $config );

		if ( [] === $active ) {
			return '';
		}

		$view  = ViewState::from( $config, Request::all(), false );
		$chips = '';

		foreach ( $active as $key => $value ) {
			$filter = $config['filters'][ $key ];
			$name   = self::name( $filter, $value );
			$format = (string) ( $filter['chip'] ?? '' );
			$text   = '' !== $format ? sprintf( $format, $name ) : $name;
			$off    = array_merge( [ $key ], array_map( 'strval', (array) ( $filter['clears'] ?? [] ) ) );

			$chips .= sprintf(
				'<span class="sugarcommerce-table-chip"><span class="sugarcommerce-table-chip-name">%1$s</span>'
				. '<a class="sugarcommerce-table-chip-remove" href="%2$s" aria-label="%3$s"><span aria-hidden="true">&times;</span></a></span>',
				esc_html( $text ),
				esc_url( Tables::page_url( $config, array_diff_key( $view, array_flip( $off ) ) ) ),
				/* translators: %s: what a list is narrowed to, such as "Product: Synthwave Stems". */
				esc_attr( sprintf( __( 'Remove filter: %s', 'sugarcommerce' ), $text ) )
			);
		}

		return '<div class="sugarcommerce-table-chips">' . $chips . '</div>';
	}

	/**
	 * The search route the select asks, once.
	 *
	 * sugarcommerce/fields registers it when a field set is built; a table with a
	 * search filter may be the only thing on the site that needs it, and
	 * the route has to exist on the REST request the select makes, not
	 * only on the page that drew it. Registering twice is harmless -- the
	 * controller claims its namespace once.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		( new SearchController() )->boot();
	}

	/**
	 * sugarcommerce/fields' script and stylesheet, for the searchable select.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		( new Assets() )->enqueue();
	}
}
