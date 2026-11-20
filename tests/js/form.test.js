/**
 * The quick editor's fields as a request body: what jQuery's serialize()
 * sent, byte for byte, so the save action reads what it always read.
 */

import { expect, test } from 'vitest';

import { fields, serialize } from '../../assets/js/lib/form';

const row = ( markup ) => {
	document.body.innerHTML = `<table><tbody><tr id="row"><td>${ markup }</td></tr></tbody></table>`;

	return document.getElementById( 'row' );
};

test( 'sends what a form would, in document order', () => {
	const root = row( `
		<input type="text" name="title" value="A &amp; B">
		<input type="hidden" name="nonce" value="n">
		<input type="checkbox" name="vip" value="1" checked>
		<input type="checkbox" name="gift" value="1">
		<input type="radio" name="size" value="s">
		<input type="radio" name="size" value="m" checked>
		<select name="status"><option value="a">A</option><option value="b" selected>B</option></select>
		<select name="tags[]" multiple><option selected>red</option><option>green</option><option value="3" selected>Blue</option></select>
		<textarea name="note">one
two</textarea>
	` );

	expect( fields( root ) ).toEqual( [
		[ 'title', 'A & B' ],
		[ 'nonce', 'n' ],
		[ 'vip', '1' ],
		[ 'size', 'm' ],
		[ 'status', 'b' ],
		[ 'tags[]', 'red' ],
		[ 'tags[]', '3' ],
		[ 'note', 'one\r\ntwo' ],
	] );
} );

test( 'leaves out what a form leaves out', () => {
	const root = row( `
		<input type="text" value="no name">
		<input type="text" name="off" value="x" disabled>
		<fieldset disabled><input type="text" name="inside" value="x"></fieldset>
		<input type="submit" name="go" value="Go">
		<input type="button" name="b" value="b">
		<input type="reset" name="r">
		<input type="image" name="i">
		<input type="file" name="f">
		<button name="button" value="1">Press</button>
		<select name="empty"></select>
	` );

	expect( fields( root ) ).toEqual( [] );
} );

test( 'encodes as jQuery did: %20 for a space, brackets encoded', () => {
	const root = row( `
		<input type="text" name="a b" value="x y+z">
		<input type="checkbox" name="labels[]" value="1" checked>
		<input type="text" name="e" value="">
	` );

	expect( serialize( root ) ).toBe( 'a%20b=x%20y%2Bz&labels%5B%5D=1&e=' );
} );
