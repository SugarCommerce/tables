<?php
/**
 * Runtime Key Derivation
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Utils;

/**
 * Every runtime string this library registers, derived from its own namespace.
 *
 * Strauss rewrites class namespaces and leaves string literals alone. Two
 * plugins each bundling a prefixed copy of this library therefore get
 * distinct classes but would otherwise register the same REST routes and the
 * same script handles, admin-ajax actions and cache group.
 *
 * That is not merely untidy, and neither failure announces itself:
 *
 * - `WP_REST_Server::register_route()` keys endpoints by route path and, with
 *   no `$override`, merges a second registration into the first with
 *   `array_merge()`. The handler list is numerically indexed, so handlers are
 *   appended rather than replaced and dispatch runs the first whose methods
 *   match. The plugin that registered first answers the other's requests --
 *   looking the table up in its own registry, where the other plugin's table
 *   is not. A star pressed on the second plugin's list is refused as a
 *   table that does not exist, on a route that plainly does.
 * - `wp_enqueue_script()` ignores a handle that is already registered, so the
 *   plugin that enqueued second gets the other plugin's JavaScript -- from a
 *   build whose markup may not be the one its own cells drew.
 *
 * The derivation exploits the one thing Strauss does rewrite: this file's
 * namespace. In a prefixed build `__NAMESPACE__` begins with the consumer's
 * prefix ("MyPlugin\SugarCommerce\Tables\Utils"), unique per plugin by
 * construction, so every key comes out distinct with no configuration.
 */
final class Runtime {

	/**
	 * This library's own identifier, used when running unprefixed.
	 */
	private const LIBRARY = 'sugarcommerce-tables';

	/**
	 * How many segments this class's own namespace has --
	 * SugarCommerce\Tables\Utils -- before any prefix. Counted rather than
	 * written out: Strauss rewrites a namespace it finds in a string, and
	 * would prefix the very thing being compared against.
	 */
	private const OWN_SEGMENTS = 3;

	/**
	 * The per-build prefix.
	 *
	 * "sugarcommerce-tables" for a plain Composer install -- development, or a single
	 * consumer that does not use Strauss -- and "{prefix}-sugarcommerce-tables" for a
	 * prefixed build.
	 *
	 * @return string
	 */
	public static function prefix(): string {
		return self::prefix_for( __NAMESPACE__ );
	}

	/**
	 * The prefix a given namespace would produce.
	 *
	 * Split out so the rule can be tested. prefix() reads __NAMESPACE__,
	 * which cannot be changed at runtime, so a test can only reach the
	 * prefixed case by asking the rule directly — this takes the namespace
	 * as an argument, and prefix() hands it __NAMESPACE__.
	 *
	 * The alternative, compiling a copy of this file under another namespace
	 * at runtime, was tried and abandoned: the interpreter that does it
	 * refuses the `declare( strict_types=1 )` at the top of this file on PHP
	 * 8.3 and 8.4, so the test passed on 8.5 alone and reported the local PHP
	 * version rather than the code. It is also a construct that has no place
	 * in a file that ships inside somebody's plugin.
	 *
	 * @param string $under The namespace this class is compiled under, prefixed or not.
	 *
	 * @return string
	 */
	public static function prefix_for( string $under ): string {
		// What Strauss put in front of this package's own namespace. A
		// plugin of the SugarCommerce family prefixes with a namespace that
		// starts "SugarCommerce\" too -- "SugarCommerce\Playground\Vendor\"
		// -- so the first segment alone cannot tell a prefixed build from an
		// unprefixed one: it named both "sugarcommerce-tables", and that plugin's
		// handles, routes and keys were every unprefixed copy's.
		$segments = array_slice( explode( '\\', trim( $under, '\\' ) ), 0, -self::OWN_SEGMENTS );

		if ( [] === $segments || '' === $segments[0] ) {
			return self::LIBRARY;
		}

		// Only the first segment, as it always was, where that is enough:
		// "SugarCart\Vendor" is "sugarcart". A family plugin's prefix needs
		// all of its segments to be told apart from the library and from
		// its siblings.
		$name = 'SugarCommerce' === $segments[0] ? implode( '_', $segments ) : $segments[0];

		return self::slug( $name ) . '-' . self::LIBRARY;
	}

	/**
	 * Get the REST namespace for this build.
	 *
	 * @return string
	 */
	public static function rest_namespace(): string {
		return self::prefix() . '/v1';
	}

	/**
	 * Get a script or style handle for this build.
	 *
	 * @param string $suffix Optional handle suffix.
	 *
	 * @return string
	 */
	public static function handle( string $suffix = '' ): string {
		return '' === $suffix ? self::prefix() : self::prefix() . '-' . $suffix;
	}

	/**
	 * Get an option, transient or nonce key for this build.
	 *
	 * @param string $suffix Optional key suffix.
	 *
	 * @return string
	 */
	public static function key( string $suffix = '' ): string {
		$base = str_replace( '-', '_', self::prefix() );

		return '' === $suffix ? $base : $base . '_' . $suffix;
	}

	/**
	 * Reduce a namespace segment to a lowercase slug.
	 *
	 * `sanitize_title()` is not used here: this runs from `__NAMESPACE__` at
	 * class-load time, which can precede WordPress being fully loaded.
	 *
	 * @param string $value Value to slug.
	 *
	 * @return string
	 */
	private static function slug( string $value ): string {
		$value = preg_replace( '/[^A-Za-z0-9]+/', '-', $value ) ?? '';

		return strtolower( trim( $value, '-' ) );
	}
}
