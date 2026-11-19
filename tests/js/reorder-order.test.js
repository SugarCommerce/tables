/**
 * Moving a row without dragging it, and keeping the order.
 *
 * The PHP suite used to read reorder.js for three lines: the action posted,
 * the ids, and the ticket that lets only the newest save speak. These hold
 * what those lines do.
 */

import { beforeEach, describe, expect, test, vi } from 'vitest';

import { createOrder } from '../../src/js/reorder/order';
import { initReorder } from '../../src/js/reorder/init';
import { fakeFetch, flush, ids, reorderTable } from './support/table';

const CONFIG = {
	url: '/wp-admin/admin-ajax.php',
	action: 'sugarcommerce-tables-reorder-demo',
	nonce: 'n0nce',
	i18n: {
		moved: '“%1$s” moved to position %2$s of %3$s.',
		failed: 'The new order could not be saved.',
	},
};

let parts;
let fetch;
let speak;

beforeEach( () => {
	parts = reorderTable( 4, { first: 101 } );
	fetch = fakeFetch();
	speak = vi.fn();
	initReorder( parts.list, { config: CONFIG, fetch, speak } );
} );

const key = ( target, name ) =>
	target.dispatchEvent(
		new window.KeyboardEvent( 'keydown', {
			key: name,
			bubbles: true,
			cancelable: true,
		} )
	);

const handle = ( index ) =>
	parts.list.children[ index ].querySelector(
		'.sugarcommerce-reorder__handle'
	);

describe( 'the arrow keys on a handle', () => {
	test( 'move its row one place, keep the focus on it, and post', async () => {
		const grip = handle( 0 );

		grip.focus();
		key( grip, 'ArrowDown' );
		await flush();

		expect( ids( parts.list ) ).toEqual( [ '12', '11', '13', '14' ] );
		expect( document.activeElement ).toBe( grip );
		expect( fetch.calls ).toHaveLength( 1 );

		// Up moves the row itself, which takes the focus off it unless it
		// is put back.
		key( grip, 'ArrowUp' );
		expect( ids( parts.list ) ).toEqual( [ '11', '12', '13', '14' ] );
		expect( document.activeElement ).toBe( grip );
	} );

	test( 'never scroll the page, even at the end of the list', () => {
		const event = new window.KeyboardEvent( 'keydown', {
			key: 'ArrowUp',
			bubbles: true,
			cancelable: true,
		} );

		handle( 0 ).dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( true );
		expect( fetch.calls ).toHaveLength( 0 );
	} );

	test( 'other keys are left alone', () => {
		key( handle( 0 ), 'Enter' );

		expect( fetch.calls ).toHaveLength( 0 );
	} );
} );

describe( 'the step buttons', () => {
	test( 'move a row down and up', () => {
		parts.list.children[ 1 ].querySelector( '[data-step="1"]' ).click();
		expect( ids( parts.list ) ).toEqual( [ '11', '13', '12', '14' ] );

		parts.list.children[ 2 ].querySelector( '[data-step="-1"]' ).click();
		expect( ids( parts.list ) ).toEqual( [ '11', '12', '13', '14' ] );
		expect( fetch.calls ).toHaveLength( 2 );
	} );

	test( 'do nothing past the ends', () => {
		parts.list.children[ 3 ].querySelector( '[data-step="1"]' ).click();

		expect( ids( parts.list ) ).toEqual( [ '11', '12', '13', '14' ] );
		expect( fetch.calls ).toHaveLength( 0 );
	} );
} );

describe( 'a save', () => {
	test( "posts the table's action, its nonce and every id in order", () => {
		handle( 2 ).focus();
		key( handle( 2 ), 'ArrowUp' );

		const body = fetch.calls[ 0 ][ 1 ].body;

		expect( body.get( 'action' ) ).toBe( CONFIG.action );
		expect( body.get( 'nonce' ) ).toBe( 'n0nce' );
		expect( body.getAll( 'ids[]' ) ).toEqual( [ '11', '13', '12', '14' ] );
	} );

	test( 'numbers from where the page starts, not from one', () => {
		key( handle( 0 ), 'ArrowDown' );

		expect(
			Array.from(
				parts.list.querySelectorAll( '.sugarcommerce-reorder__number' )
			).map( ( number ) => number.textContent )
		).toEqual( [ '101', '102', '103', '104' ] );
	} );

	test( 'says where the row went', () => {
		key( handle( 0 ), 'ArrowDown' );

		expect( speak ).toHaveBeenCalledWith(
			'“Row 11” moved to position 2 of 4.',
			'assertive'
		);
	} );

	test( 'that is refused says so, once, under the heading', async () => {
		parts = reorderTable( 3 );
		fetch = fakeFetch( { success: false } );
		initReorder( parts.list, { config: CONFIG, fetch, speak } );

		key( handle( 0 ), 'ArrowDown' );
		await flush();
		key( handle( 0 ), 'ArrowDown' );
		await flush();

		const notices = document.querySelectorAll(
			'.sugarcommerce-reorder-failed'
		);

		expect( notices ).toHaveLength( 1 );
		expect( notices[ 0 ].previousElementSibling.matches( 'hr' ) ).toBe(
			true
		);
		expect( notices[ 0 ].textContent ).toBe( CONFIG.i18n.failed );
		expect( speak ).toHaveBeenCalledWith( CONFIG.i18n.failed, 'assertive' );
	} );

	test( 'that cannot be sent at all says so too', async () => {
		parts = reorderTable( 3 );
		initReorder( parts.list, {
			config: CONFIG,
			fetch: () => Promise.reject( new Error( 'offline' ) ),
			speak,
		} );

		key( handle( 0 ), 'ArrowDown' );
		await flush();

		expect(
			document.querySelectorAll( '.sugarcommerce-reorder-failed' )
		).toHaveLength( 1 );
	} );

	test( 'only the newest answer counts', async () => {
		const answers = [];

		parts = reorderTable( 3 );
		initReorder( parts.list, {
			config: CONFIG,
			fetch: () =>
				new Promise( ( resolve ) =>
					answers.push( ( success ) =>
						resolve( {
							json: () => Promise.resolve( { success } ),
						} )
					)
				),
			speak,
		} );

		key( handle( 0 ), 'ArrowDown' );
		key( handle( 0 ), 'ArrowDown' );

		// The newest succeeds; the older one, answering late, fails.
		answers[ 1 ]( true );
		await flush();
		answers[ 0 ]( false );
		await flush();

		expect(
			document.querySelectorAll( '.sugarcommerce-reorder-failed' )
		).toHaveLength( 0 );
	} );

	test( 'is not spoken when there are no words to speak', () => {
		parts = reorderTable( 2 );
		initReorder( parts.list, {
			config: { i18n: {} },
			fetch,
			speak,
		} );
		speak.mockClear();

		key( handle( 0 ), 'ArrowDown' );

		expect( speak ).not.toHaveBeenCalled();
	} );
} );

test( 'only rows with a handle are rows to move', () => {
	parts.list.insertAdjacentHTML( 'afterbegin', '<tr class="hidden"></tr>' );

	const order = createOrder( parts.list, {
		config: CONFIG,
		fetch,
		speak,
	} );

	expect( order.rows() ).toHaveLength( 4 );
} );
