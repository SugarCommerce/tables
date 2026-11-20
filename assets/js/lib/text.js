/**
 * Filling the places in a translated sentence.
 */

/**
 * Fill the numbered places in a sentence, or its one bare %s.
 *
 * The sentences come from PHP, already translated, so the order of the
 * values is the translator's: %2$s may come before %1$s.
 *
 * @param {string}    text   The sentence, with %1$s, %2$s or a bare %s.
 * @param {...string} values What goes in them, in order.
 * @return {string} The sentence.
 */
export function fill( text, ...values ) {
	return String( text || '' )
		.replace( /%(\d)\$s/g, ( match, n ) => values[ n - 1 ] ?? '' )
		.replace( '%s', values[ 0 ] ?? '' );
}
