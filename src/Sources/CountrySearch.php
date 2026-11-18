<?php
/**
 * Country Search
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Sources;

use SugarCommerce\Geo\Countries;

/**
 * The countries a search term names.
 *
 * What somebody types to mean a country, turned into the two letters a row
 * holds: "germany", "Germ" and "de" are all `DE`. A term names a country
 * when it is the start of the country's name, when it is one whole word of
 * it ("kingdom", "korea"), or when it is the two-letter code itself. Not
 * when it is merely somewhere inside the name: "land" is not a search for
 * Finland, Poland and thirty others. Fewer than three letters is a code or
 * nothing, for the same reason.
 *
 * sugarcommerce/geo's own `Countries::search()` matches anywhere in the name,
 * which is right for a picker and wrong for a list's search box.
 *
 * @since 1.0.0
 */
final class CountrySearch {

	/**
	 * Two-letter codes the term names; empty when it names no country.
	 *
	 * @param string                     $term  What was typed.
	 * @param array<string, string>|null $names Code => name to match against:
	 *                                          the site's own, translated, as
	 *                                          its country dropdowns have them.
	 *                                          Default sugarcommerce/geo's list.
	 *
	 * @return array<int, string>
	 * @since 1.0.0
	 */
	public static function codes( string $term, ?array $names = null ): array {
		$term  = mb_strtolower( trim( $term ) );
		$codes = [];

		if ( '' === $term ) {
			return [];
		}

		$names ??= class_exists( Countries::class ) ? Countries::all() : [];

		foreach ( $names as $code => $name ) {
			$name = mb_strtolower( (string) $name );

			$matches = mb_strtolower( (string) $code ) === $term
				|| ( mb_strlen( $term ) >= 3 && ( str_starts_with( $name, $term ) || in_array( $term, (array) preg_split( '/[^\p{L}\p{N}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY ), true ) ) );

			if ( $matches ) {
				$codes[] = (string) $code;
			}
		}

		return $codes;
	}
}
