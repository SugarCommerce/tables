/**
 * A deferred column switched on in Screen Options.
 *
 * Its cells are marks until the list is loaded again, and the list must
 * only load again once the choice is saved. The script saves it itself, as
 * core does, and reloads when that answers -- core's own request can only
 * be heard finishing through jQuery.
 */

import { beforeEach, expect, test, vi } from 'vitest';

import {
	hiddenColumns,
	watchDeferredColumns,
} from '../../assets/js/columns/deferred';
import { flush } from './support/table';

let fetch;
let reload;

/**
 * Screen Options and a list, with core's own handler on each box: it
 * shows or hides the column's heading before the click reaches the
 * document.
 */
function page() {
	document.body.innerHTML = `
		<div id="adv-settings">
			<input type="hidden" id="screenoptionnonce" value="so-nonce">
			<input class="hide-column-tog" type="checkbox" value="status" checked>
			<input class="hide-column-tog" type="checkbox" value="last_note">
			<input class="hide-column-tog" type="checkbox" value="country">
		</div>
		<table class="wp-list-table"><thead><tr>
			<th id="status" class="manage-column column-status">Status</th>
			<th id="last_note" class="manage-column column-last_note hidden">Note</th>
			<th id="country" class="manage-column column-country hidden">Country</th>
		</tr></thead><tbody>
			<tr><td class="column-status">ok</td><td class="column-last_note hidden"><span class="sugarcommerce-column-deferred">-</span></td><td class="column-country hidden">GB</td></tr>
		</tbody></table>`;

	document
		.querySelectorAll( '.hide-column-tog' )
		.forEach( ( box ) =>
			box.addEventListener( 'click', () =>
				document
					.getElementById( box.value )
					.classList.toggle( 'hidden', ! box.checked )
			)
		);
}

const toggle = ( column ) =>
	document.querySelector( `.hide-column-tog[value="${ column }"]` ).click();

let answer;
let stop = () => {};

beforeEach( () => {
	page();
	answer = null;
	fetch = vi.fn(
		() =>
			new Promise( ( resolve, reject ) => {
				answer = { resolve, reject };
			} )
	);
	reload = vi.fn();

	// A page loaded afresh: one watcher, waiting for nothing.
	stop();
	stop = watchDeferredColumns( document, {
		url: '/wp-admin/admin-ajax.php',
		screen: 'playground_page_playground-tables',
		fetch,
		reload,
	} );
} );

test( "switching one on saves core's choice, then loads the list again", async () => {
	toggle( 'last_note' );

	expect( fetch ).toHaveBeenCalledTimes( 1 );

	const [ url, options ] = fetch.mock.calls[ 0 ];

	expect( url ).toBe( '/wp-admin/admin-ajax.php' );
	expect( options.method ).toBe( 'POST' );
	expect( options.credentials ).toBe( 'same-origin' );
	expect( Object.fromEntries( options.body ) ).toEqual( {
		action: 'hidden-columns',
		hidden: 'country',
		screenoptionnonce: 'so-nonce',
		page: 'playground_page_playground-tables',
	} );

	// Not before the choice is saved.
	expect( reload ).not.toHaveBeenCalled();

	answer.resolve( { ok: true } );
	await flush();

	expect( reload ).toHaveBeenCalledTimes( 1 );
} );

test( 'loads the list again even if the save failed, as it always did', async () => {
	toggle( 'last_note' );
	answer.reject( new Error( 'offline' ) );
	await flush();

	expect( reload ).toHaveBeenCalledTimes( 1 );
} );

test( 'one save, however often the box is pressed while it is on its way', () => {
	toggle( 'last_note' );
	toggle( 'last_note' );
	toggle( 'last_note' );

	expect( fetch ).toHaveBeenCalledTimes( 1 );
} );

test( 'switching a deferred column off is left to core', () => {
	const box = document.querySelector( '.hide-column-tog[value="last_note"]' );

	box.checked = true;
	document.getElementById( 'last_note' ).classList.remove( 'hidden' );
	box.click();

	expect( box.checked ).toBe( false );
	expect( fetch ).not.toHaveBeenCalled();
} );

test( 'a column drawn in full, or one switched off, is left to core', () => {
	toggle( 'country' );
	toggle( 'status' );

	expect( fetch ).not.toHaveBeenCalled();
} );

test( "the hidden columns are core's: headings with an id, marked hidden", () => {
	document
		.querySelector( 'thead tr' )
		.insertAdjacentHTML(
			'beforeend',
			'<th class="manage-column hidden">No id</th><td id="cb" class="manage-column hidden"></td>'
		);

	expect( hiddenColumns( document ) ).toBe( 'last_note,country,cb' );
} );
