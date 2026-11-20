/**
 * show() and hide(), as jQuery's did them.
 */

import { beforeEach, expect, test } from 'vitest';

import { hide, show, siblings } from '../../assets/js/lib/dom';

beforeEach( () => {
	document.head.innerHTML = '<style>.hidden { display: none; }</style>';
	document.body.innerHTML =
		'<table><tbody><tr id="a"><td id="cell" class="hidden"></td></tr><tr id="b" class="hidden"></tr><tr id="c" class="hidden"></tr></tbody></table><div id="d" class="hidden"></div>';
} );

test( 'hide() puts display: none on, show() takes it off again', () => {
	const row = document.getElementById( 'a' );

	hide( row );
	expect( row.style.display ).toBe( 'none' );

	show( row );
	expect( row.style.display ).toBe( '' );
} );

test( 'show() gives an element a stylesheet hides the display its tag has', () => {
	show( document.getElementById( 'cell' ) );
	show( document.getElementById( 'd' ) );

	expect( document.getElementById( 'cell' ).style.display ).toBe(
		'table-cell'
	);
	expect( document.getElementById( 'd' ).style.display ).toBe( 'block' );
} );

test( 'siblings() are the others that match, never the element itself', () => {
	const b = document.getElementById( 'b' );

	expect( siblings( b, 'tr.hidden' ).map( ( row ) => row.id ) ).toEqual( [
		'c',
	] );
	expect( siblings( document.createElement( 'tr' ), 'tr' ) ).toEqual( [] );
} );
