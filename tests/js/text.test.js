/**
 * Filling a translated sentence.
 */

import { expect, test } from 'vitest';

import { fill } from '../../assets/js/lib/text';

test( 'numbered places take their values in the order the translator put them', () => {
	expect( fill( '%2$s, then %1$s', 'a', 'b' ) ).toBe( 'b, then a' );
	expect(
		fill( '“%1$s” moved to position %2$s of %3$s.', 'Gift', '2', '5' )
	).toBe( '“Gift” moved to position 2 of 5.' );
} );

test( 'a bare %s takes the first value, once', () => {
	expect( fill( 'Mark “%s” as Featured', 'Ada' ) ).toBe(
		'Mark “Ada” as Featured'
	);
	expect( fill( '%s and %s', 'a' ) ).toBe( 'a and %s' );
} );

test( 'nothing to fill, or nothing to fill it with, is not an error', () => {
	expect( fill( undefined ) ).toBe( '' );
	expect( fill( 'Place %1$s' ) ).toBe( 'Place ' );
	expect( fill( 'Since %s' ) ).toBe( 'Since ' );
} );
