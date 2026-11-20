/**
 * Toggle cells.
 *
 * The PHP suite used to read toggle.js for the REST nonce, the one-press-
 * at-a-time guard, the ask-first arming, the `{id}` in the URL and the icon
 * swapped whole from the server's markup. These press the buttons.
 */

import { beforeEach, describe, expect, test, vi } from 'vitest';

import { day } from '../../assets/js/toggle/draw';
import { bindToggles } from '../../assets/js/toggle/press';
import { flush } from './support/table';

const ON = '<svg class="sugarcommerce-icon is-on"></svg>';
const OFF = '<span class="sugarcommerce-icon dashicons is-off"></span>';

const COLUMNS = {
	'demo:featured': {
		on: 'Featured',
		off: 'Not featured',
		turnOn: 'Mark as Featured',
		turnOff: 'Remove from Featured',
		turnOnRow: 'Mark “%s” as Featured',
		turnOffRow: 'Remove “%s” from Featured',
		doneOn: '“%s” marked as featured.',
		failed: 'That could not be saved.',
		icons: { on: ON, off: OFF },
		request: {
			url: '/wp-json/shop/v1/products/{id}',
			method: 'PATCH',
			key: 'featured',
			state: 'featured',
			data: { context: 'list' },
		},
	},
	'demo:consent': {
		name: 'Subscribed to marketing',
		on: 'Subscribed',
		off: 'Not subscribed',
		turnOn: 'Mark as Subscribed',
		turnOff: 'Mark as Not Subscribed',
		confirmOn: 'Only do this if they agreed.',
		pressAgain: 'Press again to mark them as subscribed.',
		sinceLabel: 'Since %s',
		sayWhy: true,
		failed: 'That could not be saved.',
		icons: { on: ON, off: '' },
		request: {
			url: '/wp-json/t/v1/demo/toggle/{id}',
			key: 'on',
			state: 'on',
			since: 'since',
			data: { column: 'consent' },
		},
	},
};

let fetch;
let speak;
let storage;

/**
 * A fetch answering each request with the next of some answers.
 *
 * @param {...Object} answers { status, body } each.
 * @return {Function} The fetch, with `.calls`.
 */
function answering( ...answers ) {
	const calls = [];
	const fake = ( url, options ) => {
		calls.push( [ url, options ] );

		const { status = 200, body = {} } = answers.shift() || {};

		return Promise.resolve( {
			ok: status < 400,
			status,
			json: () =>
				'string' === typeof body
					? Promise.reject( new Error( 'not json' ) )
					: Promise.resolve( body ),
		} );
	};

	fake.calls = calls;

	return fake;
}

/**
 * A toggle cell, as Toggle::render() draws it.
 *
 * @param {string}  key The toggle.
 * @param {string}  id  The row.
 * @param {boolean} on  Whether it is on.
 * @return {HTMLButtonElement} The button.
 */
function cell( key, id = '7', on = false ) {
	document.body.insertAdjacentHTML(
		'beforeend',
		`<div class="sugarcommerce-toggle-cell${ on ? ' is-on' : '' }">
			<button type="button" class="sugarcommerce-toggle" data-toggle="${ key }" data-id="${ id }" data-name="Ada &amp; Co" aria-pressed="${ on }" title="x">${
				on ? ON : OFF
			}<span class="sugarcommerce-toggle__word">${ on ? 'on' : 'off' }</span></button>
			<span class="sugarcommerce-toggle__message" hidden></span>
		</div>`
	);

	return document.body.lastElementChild.querySelector( 'button' );
}

let failingStorage = false;

// One document for the whole file, listened to once; each test swaps what
// the listener reaches through these.
bindToggles( document, {
	settings: { nonce: 'rest-nonce', columns: COLUMNS },
	storage: () =>
		failingStorage
			? {
					getItem: () => {
						throw new Error( 'denied' );
					},
					setItem: () => {
						throw new Error( 'denied' );
					},
				}
			: {
					getItem: ( k ) => storage.get( k ) ?? null,
					setItem: ( k, v ) => storage.set( k, v ),
				},
	fetch: ( ...args ) => fetch( ...args ),
	speak: ( ...args ) => speak( ...args ),
} );

beforeEach( () => {
	document.body.innerHTML = '';
	speak = vi.fn();
	storage = new Map();
	failingStorage = false;
	fetch = answering( { body: { featured: true } } );
} );

describe( 'a press', () => {
	test( 'turns it over at once, then saves with the REST nonce', async () => {
		const button = cell( 'demo:featured', 'a/b' );

		button.click();

		expect( button.getAttribute( 'aria-pressed' ) ).toBe( 'true' );
		expect( button.getAttribute( 'aria-busy' ) ).toBe( 'true' );

		const [ url, options ] = fetch.calls[ 0 ];

		expect( url ).toBe( '/wp-json/shop/v1/products/a%2Fb' );
		expect( options.method ).toBe( 'PATCH' );
		expect( options.credentials ).toBe( 'same-origin' );
		expect( options.headers ).toEqual( {
			'Content-Type': 'application/json',
			'X-WP-Nonce': 'rest-nonce',
		} );
		expect( JSON.parse( options.body ) ).toEqual( {
			context: 'list',
			featured: true,
		} );

		await flush();

		expect( button.hasAttribute( 'aria-busy' ) ).toBe( false );
		expect( button.getAttribute( 'aria-label' ) ).toBe(
			'Remove “Ada & Co” from Featured'
		);
		expect( button.getAttribute( 'title' ) ).toBe( 'Remove from Featured' );
		expect(
			button.querySelector( '.sugarcommerce-toggle__word' ).textContent
		).toBe( 'Featured' );
		expect(
			button
				.closest( '.sugarcommerce-toggle-cell' )
				.classList.contains( 'is-on' )
		).toBe( true );
		expect( speak ).toHaveBeenCalledWith(
			'“Ada & Co” marked as featured.'
		);
	} );

	test( "swaps the icon for the server's own, whole", async () => {
		const button = cell( 'demo:featured' );

		button.click();
		await flush();

		const icons = button.querySelectorAll( '.sugarcommerce-icon' );

		expect( icons ).toHaveLength( 1 );
		expect( icons[ 0 ].outerHTML ).toBe( ON );
		expect( button.firstElementChild ).toBe( icons[ 0 ] );
	} );

	test( 'draws what the server says, not what was pressed', async () => {
		fetch = answering( { body: { featured: false } } );

		const button = cell( 'demo:featured' );

		button.click();
		await flush();

		expect( button.getAttribute( 'aria-pressed' ) ).toBe( 'false' );
		expect( speak ).toHaveBeenCalledWith( 'Ada & Co: Not featured' );
	} );

	test( 'while one save is on its way, is ignored', () => {
		const button = cell( 'demo:featured' );

		button.click();
		button.click();

		expect( fetch.calls ).toHaveLength( 1 );
		expect( button.getAttribute( 'aria-pressed' ) ).toBe( 'true' );
	} );

	test( 'that is refused turns it back and says so beside it', async () => {
		fetch = answering( {
			status: 403,
			body: { message: 'Sorry, you are not allowed.' },
		} );

		const button = cell( 'demo:featured', '9' );

		button.click();
		await flush();

		const slot = document.querySelector( '.sugarcommerce-toggle__message' );

		expect( button.getAttribute( 'aria-pressed' ) ).toBe( 'false' );
		expect( button.getAttribute( 'title' ) ).toBe( 'x' );
		expect( slot.hidden ).toBe( false );
		expect( slot.textContent ).toBe( 'That could not be saved.' );
		expect( slot.classList.contains( 'is-error' ) ).toBe( true );
		expect( slot.id ).toBe(
			'sugarcommerce-toggle-message-demo-featured-9'
		);
		expect( button.getAttribute( 'aria-describedby' ) ).toBe( slot.id );
		expect( speak ).toHaveBeenCalledWith( 'That could not be saved.' );
		expect( button.hasAttribute( 'aria-busy' ) ).toBe( false );
	} );

	test( "says the server's own words when the toggle asks for them", async () => {
		storage.set( 'sugarcommerce-toggle-noted:demo:consent:on', '1' );
		fetch = answering(
			{ status: 500, body: 'oops' },
			{ status: 403, body: { message: 'Cookie check failed.' } }
		);

		const button = cell( 'demo:consent' );

		button.click();
		await flush();
		expect(
			document.querySelector( '.sugarcommerce-toggle__message' )
				.textContent
		).toBe( 'That could not be saved.' );

		button.click();
		await flush();
		expect(
			document.querySelector( '.sugarcommerce-toggle__message' )
				.textContent
		).toBe( 'Cookie check failed.' );
	} );

	test( 'on a button no column knows, does nothing', () => {
		const button = cell( 'other:thing' );
		const event = new window.MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );

		button.dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( false );
		expect( fetch.calls ).toHaveLength( 0 );
	} );
} );

describe( 'a toggle that asks first', () => {
	test( 'says what it means on the first press, and saves on the second', async () => {
		fetch = answering( {
			body: { on: true, since: '2026-03-03 10:00:00' },
		} );

		const button = cell( 'demo:consent' );

		button.click();

		const slot = document.querySelector( '.sugarcommerce-toggle__message' );

		expect( fetch.calls ).toHaveLength( 0 );
		expect( button.getAttribute( 'data-armed' ) ).toBe( '1' );
		expect( slot.textContent ).toBe(
			'Only do this if they agreed. Press again to mark them as subscribed.'
		);
		expect( slot.hidden ).toBe( false );
		expect( slot.classList.contains( 'is-error' ) ).toBe( false );
		expect( speak ).toHaveBeenCalledWith( slot.textContent );

		button.click();
		await flush();

		expect( fetch.calls ).toHaveLength( 1 );
		expect( button.hasAttribute( 'data-armed' ) ).toBe( false );
		expect( slot.hidden ).toBe( true );
		expect( button.getAttribute( 'aria-pressed' ) ).toBe( 'true' );
		expect( button.getAttribute( 'title' ) ).toBe(
			'Since ' +
				day( '2026-03-03 10:00:00' ) +
				' · Mark as Not Subscribed'
		);
		// A toggle with a name of its own keeps its label.
		expect( button.hasAttribute( 'aria-label' ) ).toBe( false );
		expect(
			storage.get( 'sugarcommerce-toggle-noted:demo:consent:on' )
		).toBe( '1' );
	} );

	test( 'asks only once, ever', () => {
		storage.set( 'sugarcommerce-toggle-noted:demo:consent:on', '1' );

		cell( 'demo:consent' ).click();

		expect( fetch.calls ).toHaveLength( 1 );
	} );

	test( 'does not ask the way that needs no asking', () => {
		cell( 'demo:consent', '7', true ).click();

		expect( fetch.calls ).toHaveLength( 1 );
	} );

	test( 'still works where the browser keeps nothing', () => {
		failingStorage = true;

		const button = cell( 'demo:consent' );

		button.click();
		button.click();

		expect( fetch.calls ).toHaveLength( 1 );
	} );
} );

test( 'a day is short and local', () => {
	expect( day( '' ) ).toBe( '' );
	expect( day( 'not a date' ) ).toBe( '' );
	expect( day( '2026-03-03T10:00:00' ) ).toMatch( /^3 \S+ 2026$/ );
} );
