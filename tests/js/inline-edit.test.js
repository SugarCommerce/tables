/**
 * Quick Edit and Bulk Edit.
 *
 * The PHP suite used to read inline-edit.js for the shapes of the fixes
 * that went into it: a data row never given the editor's class, a value
 * the select does not offer shown rather than replaced, a checklist filled
 * from its comma list and skipped by the loop that fills the rest, the
 * bulk checklists tri-state and reset, and the action posted. These open
 * the editors and press their buttons.
 */

import { beforeEach, describe, expect, test, vi } from 'vitest';

import { bindEditor } from '../../assets/js/inline-edit/bind';
import { createEditor } from '../../assets/js/inline-edit/editor';
import {
	describe as describeValue,
	readField,
} from '../../assets/js/inline-edit/fields';
import { readNotice } from '../../assets/js/inline-edit/notice';
import { post as send } from '../../assets/js/inline-edit/post';
import { fakePost, inlinePage, rowMarkup } from './support/inline';

const SETTINGS = {
	path: 'sugarcommerce-tables/v1/orders/quick-edit',
	error: 'The change could not be saved.',
	saved: 'Changes saved.',
	removeFromBulk: 'Remove from Bulk Edit',
	someHaveThis: 'Some selected items have this',
};

let page;
let editor;
let post;
let speak;
let unbind = () => {};

/**
 * Put the page up and the editors on it.
 *
 * @param {Object} answer What a save is answered with.
 */
function setUp( answer = { text: '' } ) {
	page = inlinePage();
	post = fakePost( answer );
	speak = vi.fn();
	editor = createEditor( document, {
		settings: SETTINGS,
		post: ( ...args ) => post( ...args ),
		speak,
	} );
	unbind();
	// Bound to the screen, as the entry binds it: its build's .wrap.
	unbind = bindEditor( document.querySelector( '.wrap' ), editor );
}

const open = ( id ) =>
	document.querySelector( `#item-${ id } .editinline` ).click();

const quick = ( id ) => document.getElementById( 'edit-' + id );

const key = ( target, type, name ) => {
	const event = new window.KeyboardEvent( type, {
		key: name,
		bubbles: true,
		cancelable: true,
	} );

	target.dispatchEvent( event );

	return event;
};

const fade = () => new Promise( ( resolve ) => setTimeout( resolve, 450 ) );

beforeEach( () => setUp() );

describe( 'opening Quick Edit', () => {
	test( 'puts the editor over the row, a spacer above it, the row hidden', () => {
		open( '12' );

		const row = document.getElementById( 'item-12' );
		const spacer = row.nextElementSibling;
		const form = quick( '12' );

		expect( row.style.display ).toBe( 'none' );
		expect( spacer.matches( 'tr.hidden' ) ).toBe( true );
		expect( spacer.nextElementSibling ).toBe( form );
		expect( form.classList.contains( 'inline-editor' ) ).toBe( true );
		expect( window.getComputedStyle( form ).display ).not.toBe( 'none' );
		expect( form.querySelector( 'td' ).getAttribute( 'colspan' ) ).toBe(
			'3'
		);
		expect( editor.what ).toBe( '12' );
		expect(
			row.querySelector( '.editinline' ).getAttribute( 'aria-expanded' )
		).toBe( 'true' );
	} );

	test( "never gives the data row the editor's class", () => {
		open( '12' );
		quick( '12' ).querySelector( '.cancel' ).click();

		const row = document.getElementById( 'item-12' );

		expect( row.className ).toBe( '' );
		expect( row.style.display ).toBe( '' );
	} );

	test( 'says which row it is for, as text', () => {
		open( '12' );

		const form = quick( '12' );

		expect( form.querySelector( '[data-row-title]' ).textContent ).toBe(
			'PG-1002 <b>'
		);
		expect( form.querySelector( '[data-row-title] b' ) ).toBeNull();
		expect( form.querySelector( 'input[name="item_id"]' ).value ).toBe(
			'12'
		);
	} );

	test( 'focuses the first control that is shown', () => {
		open( '11' );

		expect( document.activeElement ).toBe(
			quick( '11' ).querySelector( 'ul input[value="1"]' )
		);
	} );

	test( 'shows a value the select does not offer, rather than its first', () => {
		open( '12' );

		const select = quick( '12' ).querySelector( 'select[name="status"]' );

		expect( select.value ).toBe( 'refunded' );
		expect( select.options[ 0 ].value ).toBe( 'refunded' );
		expect( select.options[ 0 ].textContent ).toBe( 'Refunded' );

		// The template is left as it was, for the next row.
		expect(
			document.querySelectorAll( '#inline-edit option[value="refunded"]' )
		).toHaveLength( 0 );
	} );

	test( 'selects a value the select does offer', () => {
		open( '13' );

		const select = quick( '13' ).querySelector( 'select[name="status"]' );

		expect( select.value ).toBe( 'pending' );
		expect( select.options ).toHaveLength( 3 );
	} );

	test( 'ticks a checklist from its comma list, and leaves its sentinel alone', () => {
		open( '11' );

		const form = quick( '11' );
		const ticked = Array.from(
			form.querySelectorAll( 'ul[data-checklist] input:checked' )
		).map( ( box ) => box.value );

		expect( ticked ).toEqual( [ '1', '2' ] );
		expect(
			form.querySelector( 'input[type="hidden"][name="labels[]"]' ).value
		).toBe( '' );
	} );

	test( 'ticks a box field from 1 or yes, and leaves a field with no value at its default', () => {
		document.querySelector( '#inline_13 .vip' ).textContent = 'yes';
		open( '13' );

		expect(
			quick( '13' ).querySelector( 'input[name="vip"]' ).checked
		).toBe( true );
		expect(
			quick( '13' ).querySelector( 'input[name="extra"]' ).value
		).toBe( 'from the hook' );
	} );

	test( 'closes any editor already open', () => {
		open( '11' );
		open( '13' );

		expect( quick( '11' ) ).toBeNull();
		expect( document.getElementById( 'item-11' ).style.display ).toBe( '' );
		expect(
			document.querySelectorAll( '#the-list tr.inline-editor' )
		).toHaveLength( 1 );
		expect(
			document.querySelectorAll( '#the-list > tr.hidden' )
		).toHaveLength( 1 );
	} );
} );

describe( 'closing Quick Edit', () => {
	test( 'Cancel puts the row back and the focus on its Quick Edit', () => {
		open( '12' );
		quick( '12' ).querySelector( '.cancel' ).click();

		const button = document.querySelector( '#item-12 .editinline' );

		expect( quick( '12' ) ).toBeNull();
		expect(
			document.querySelectorAll( '#the-list > tr.hidden' )
		).toHaveLength( 0 );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( document.activeElement ).toBe( button );
		expect( editor.what ).toBe( '' );
	} );

	test( 'so does Escape', () => {
		open( '12' );
		key( quick( '12' ).querySelector( 'select' ), 'keyup', 'Escape' );

		expect( quick( '12' ) ).toBeNull();
	} );
} );

describe( 'saving Quick Edit', () => {
	const saved = ( status ) =>
		rowMarkup( { id: '12', title: 'PG-1002', status, labels: '2' } );

	test( "posts every field, as the form sends them, to the row's route", () => {
		open( '11' );
		quick( '11' ).querySelector( '.save' ).click();

		expect( post.calls ).toHaveLength( 1 );
		expect( post.calls[ 0 ][ 0 ] ).toBe(
			'sugarcommerce-tables/v1/orders/quick-edit/11'
		);
		expect( post.calls[ 0 ][ 1 ] ).toBe(
			'labels%5B%5D=&labels%5B%5D=1&labels%5B%5D=2&status=complete&note=line%20one%0D%0Aline%20two&extra=from%20the%20hook&_inline_edit=qnonce&table_id=orders&item_id=11'
		);
		expect(
			document
				.querySelector( 'table.widefat .spinner, #the-list .spinner' )
				.classList.contains( 'is-active' )
		).toBe( true );
	} );

	test( 'swaps in the row the server drew, and says what it did above the list', async () => {
		setUp( {
			text: saved( 'complete' ),
			notice: {
				message: 'Order PG-1002 is now complete.',
				type: 'success',
			},
		} );

		const added = vi.fn();

		document.addEventListener( 'wp-updates-notice-added', added );

		open( '12' );
		await editor.save();
		await fade();

		const row = document.getElementById( 'item-12' );

		expect( row.querySelector( '.column-status' ).textContent ).toBe(
			'complete'
		);
		expect( window.getComputedStyle( row ).display ).not.toBe( 'none' );
		expect( row.style.opacity ).toBe( '' );
		expect( document.querySelectorAll( '[id="item-12"]' ) ).toHaveLength(
			1
		);
		expect( document.querySelector( '.inline-editor' ) ).toBeNull();
		expect(
			document.querySelectorAll( '#the-list > tr.hidden' )
		).toHaveLength( 0 );
		expect( editor.what ).toBe( '' );

		// tr.hidden went, never the div.hidden data block every row carries.
		expect(
			document.querySelectorAll( '#the-list div.hidden[id^="inline_"]' )
		).toHaveLength( 3 );

		const notice = document.querySelector(
			'.sugarcommerce-table-quick-notice'
		);

		expect( notice.className ).toBe(
			'notice is-dismissible sugarcommerce-table-quick-notice notice-success'
		);
		expect( notice.textContent ).toBe( 'Order PG-1002 is now complete.' );
		expect(
			notice.previousElementSibling.matches( 'hr.wp-header-end' )
		).toBe( true );
		expect( added ).toHaveBeenCalledTimes( 1 );
		expect( speak ).toHaveBeenCalledWith(
			'Order PG-1002 is now complete.'
		);
		expect( document.activeElement ).toBe(
			row.querySelector( '.editinline' )
		);
		expect(
			row.querySelector( '.editinline' ).getAttribute( 'aria-expanded' )
		).toBe( 'false' );
		expect( document.querySelector( '.spinner.is-active' ) ).toBeNull();

		// The next one replaces it.
		open( '12' );
		await editor.save();
		await fade();

		expect(
			document.querySelectorAll( '.sugarcommerce-table-quick-notice' )
		).toHaveLength( 1 );
	} );

	test( 'with no notice from the server, says the change was saved', async () => {
		setUp( { text: saved( 'failed' ) } );

		open( '12' );
		await editor.save();
		await fade();

		expect( speak ).toHaveBeenCalledWith( 'Changes saved.' );
		expect(
			document.querySelector( '.sugarcommerce-table-quick-notice' )
		).toBeNull();
	} );

	test.each( [
		[ 'nothing', { text: '' } ],
		[ 'an error status', { ok: false, text: '' } ],
		[ 'no answer at all', new Error( 'offline' ) ],
	] )( 'that answers %s says so in the editor', async ( name, answer ) => {
		setUp( answer );

		open( '12' );
		await editor.save();

		const error = quick( '12' ).querySelector( '.error' );

		expect( error.textContent ).toBe( 'The change could not be saved.' );
		expect( error.parentElement.classList.contains( 'hidden' ) ).toBe(
			false
		);
		expect(
			window.getComputedStyle( error.parentElement ).display
		).not.toBe( 'none' );
		expect( document.getElementById( 'item-12' ).style.display ).toBe(
			'none'
		);
		expect( document.querySelector( '.spinner.is-active' ) ).toBeNull();
	} );

	test( 'Enter saves, except on Cancel', () => {
		open( '12' );

		const event = key(
			quick( '12' ).querySelector( 'select' ),
			'keydown',
			'Enter'
		);

		expect( event.defaultPrevented ).toBe( true );
		expect( post.calls ).toHaveLength( 1 );

		key( quick( '12' ).querySelector( '.cancel' ), 'keydown', 'Enter' );
		expect( post.calls ).toHaveLength( 1 );
	} );

	test( 'with nothing open, does nothing', () => {
		expect( editor.save() ).toBe( false );
		expect( post.calls ).toHaveLength( 0 );
	} );
} );

describe( 'Bulk Edit', () => {
	const tick = ( ...ids ) =>
		ids.forEach( ( id ) => {
			document.querySelector(
				`#the-list input[name="item[]"][value="${ id }"]`
			).checked = true;
		} );

	const apply = ( button = 'doaction', select = 'action' ) => {
		document.querySelector( `select[name="${ select }"]` ).value = 'edit';

		const event = new window.MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );

		document.getElementById( button ).dispatchEvent( event );

		return event;
	};

	const box = ( value ) =>
		document.querySelector( `#bulk-edit ul input[value="${ value }"]` );

	test( 'opens at the top of the list with what was ticked', () => {
		tick( '11', '12' );

		const event = apply();
		const list = page.list;

		expect( event.defaultPrevented ).toBe( true );
		expect( list.children[ 0 ].matches( 'tr.hidden' ) ).toBe( true );
		expect( list.children[ 1 ] ).toBe( page.bulk );
		expect( page.bulk.classList.contains( 'inline-editor' ) ).toBe( true );
		expect( window.getComputedStyle( page.bulk ).display ).not.toBe(
			'none'
		);
		expect(
			page.bulk.querySelector( 'td' ).getAttribute( 'colspan' )
		).toBe( '3' );

		const items = document.querySelectorAll(
			'#bulk-titles-list[role="list"] li.ntdelitem'
		);

		expect( items ).toHaveLength( 2 );
		expect( items[ 1 ].querySelector( 'button.ntdelbutton' ).id ).toBe(
			'_12'
		);
		expect(
			items[ 1 ].querySelector( '.screen-reader-text' ).textContent
		).toBe( 'Remove from Bulk Edit' );
		expect( items[ 1 ].querySelector( '.ntdeltitle' ).textContent ).toBe(
			'PG-1002 <b>'
		);
		expect( items[ 1 ].querySelector( '.ntdeltitle b' ) ).toBeNull();
		expect( document.activeElement ).toBe(
			page.bulk.querySelector( 'select' )
		);
	} );

	test( 'opens from the controls below the list too', () => {
		tick( '13' );
		apply( 'doaction2', 'action2' );

		expect( page.list.children[ 1 ] ).toBe( page.bulk );
	} );

	test( 'leaves any other bulk action to the form', () => {
		tick( '11' );
		document.querySelector( 'select[name="action"]' ).value = 'complete';

		const event = new window.MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );

		document.getElementById( 'doaction' ).dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( false );
		expect( page.bulk.parentElement.id ).toBe( 'inlineedit' );
	} );

	test( 'with nothing ticked, does not open', () => {
		apply();

		expect( page.bulk.parentElement.id ).toBe( 'inlineedit' );
		expect( page.bulk.style.display ).toBe( 'none' );
		expect(
			document.querySelectorAll( '#the-list > tr.hidden' )
		).toHaveLength( 0 );
	} );

	test( 'ticks what every row has, half-ticks what some have, and says so', () => {
		tick( '11', '12' );
		apply();

		// 11 has 1 and 2; 12 has 2.
		expect( box( '2' ).checked ).toBe( true );
		expect( box( '2' ).indeterminate ).toBe( false );
		expect( box( '3' ).checked ).toBe( false );
		expect( box( '3' ).indeterminate ).toBe( false );

		const some = box( '1' );
		const flag = some.parentElement.querySelector( 'input[type="hidden"]' );

		expect( some.checked ).toBe( false );
		expect( some.indeterminate ).toBe( true );
		expect( some.getAttribute( 'aria-label' ) ).toBe(
			'Gift: Some selected items have this'
		);
		expect( flag.name ).toBe( 'indeterminate_labels[]' );
		expect( flag.value ).toBe( '1' );
		expect( flag.previousElementSibling ).toBe( some );
		expect(
			document.querySelectorAll(
				'#bulk-edit input[name^="indeterminate_"]'
			)
		).toHaveLength( 1 );
	} );

	test( 'a half-ticked box touched becomes a real answer', () => {
		tick( '11', '12' );
		apply();

		const some = box( '1' );

		some.click();

		expect( some.hasAttribute( 'aria-label' ) ).toBe( false );
		expect(
			some.parentElement.querySelector( 'input[type="hidden"]' )
		).toBeNull();
	} );

	test( 'opens clean each time', () => {
		tick( '11', '12' );
		apply();
		page.bulk.querySelector( '.cancel' ).click();

		tick( '13' );
		document.querySelector( '#the-list input[value="11"]' ).checked = false;
		document.querySelector( '#the-list input[value="12"]' ).checked = false;
		apply();

		[ '1', '2', '3' ].forEach( ( value ) => {
			expect( box( value ).checked ).toBe( false );
			expect( box( value ).indeterminate ).toBe( false );
			expect( box( value ).hasAttribute( 'aria-label' ) ).toBe( false );
		} );
		expect(
			document.querySelectorAll(
				'#bulk-edit input[name^="indeterminate_"]'
			)
		).toHaveLength( 0 );
	} );

	test( 'Update clears every half-tick before the form posts', () => {
		tick( '11', '12' );
		apply();
		document
			.getElementById( 'bulk_edit' )
			.addEventListener( 'click', ( event ) => event.preventDefault() );
		document.getElementById( 'bulk_edit' ).click();

		expect( box( '1' ).indeterminate ).toBe( false );
		// The flag stays: it is what keeps each row's own answer.
		expect(
			box( '1' ).parentElement.querySelector( 'input[type="hidden"]' )
		).not.toBeNull();
	} );

	test( 'removing a row from the list unticks it, and the last one closes the editor', () => {
		tick( '11', '12' );
		apply();

		document.getElementById( '_12' ).click();

		expect(
			document.querySelector( '#the-list input[value="12"]' ).checked
		).toBe( false );
		expect(
			document.querySelectorAll( '#bulk-titles-list li' )
		).toHaveLength( 1 );
		expect( page.bulk.parentElement ).toBe( page.list );

		document.getElementById( '_11' ).click();

		expect( page.bulk.parentElement.id ).toBe( 'inlineedit' );
	} );

	test( 'Cancel and Escape put it back where it lives', () => {
		tick( '11' );
		apply();
		page.bulk.querySelector( '.cancel' ).click();

		expect( page.bulk.parentElement.id ).toBe( 'inlineedit' );
		expect( page.bulk.classList.contains( 'inline-editor' ) ).toBe( false );
		expect( page.bulk.style.display ).toBe( 'none' );
		expect(
			document.getElementById( 'bulk-titles' ).children
		).toHaveLength( 0 );
		expect(
			document.querySelectorAll( '#the-list > tr.hidden' )
		).toHaveLength( 0 );

		apply();
		key( page.bulk.querySelector( 'select' ), 'keyup', 'Escape' );

		expect( page.bulk.parentElement.id ).toBe( 'inlineedit' );
	} );

	test( 'Quick Edit closes it', () => {
		tick( '11' );
		apply();
		open( '13' );

		expect( page.bulk.parentElement.id ).toBe( 'inlineedit' );
		expect( quick( '13' ) ).not.toBeNull();
	} );
} );

describe( 'the pieces', () => {
	test( 'a value off the data block, or null when there is none', () => {
		const block = document.getElementById( 'inline_11' );

		expect( readField( block, 'status' ) ).toBe( 'complete' );
		expect( readField( block, 'nothing' ) ).toBeNull();
		expect( readField( null, 'status' ) ).toBeNull();
		expect( readField( block, 'a[b]' ) ).toBeNull();
	} );

	test( 'a stored value made readable', () => {
		expect( describeValue( 'partially_refunded' ) ).toBe(
			'Partially refunded'
		);
		expect( describeValue( 'on-hold' ) ).toBe( 'On hold' );
		expect( describeValue( '' ) ).toBe( '—' );
	} );

	test( "the answer's notice read, with its type kept to the four", () => {
		const header = ( value ) => value;

		expect(
			readNotice( header( { message: 'Done.', type: 'warning' } ) )
		).toEqual( {
			message: 'Done.',
			type: 'warning',
		} );
		expect(
			readNotice( header( { message: 'Done.', type: 'evil x' } ) ).type
		).toBe( 'success' );
		expect( readNotice( header( { type: 'success' } ) ) ).toBeNull();
		expect( readNotice( 'not an object' ) ).toBeNull();
		expect( readNotice( null ) ).toBeNull();
	} );
} );

describe( "the quick editor's request", () => {
	test( 'goes to the route through api-fetch, the form as it is', async () => {
		const apiFetch = vi.fn( () =>
			Promise.resolve( { row: '<tr></tr>', notice: { message: 'Hi.' } } )
		);

		const answer = await send(
			'sugarcommerce-tables/v1/orders/quick-edit/11',
			'status=complete&labels%5B%5D=2',
			apiFetch
		);

		const options = apiFetch.mock.calls[ 0 ][ 0 ];

		expect( options.path ).toBe(
			'sugarcommerce-tables/v1/orders/quick-edit/11'
		);
		expect( options.method ).toBe( 'POST' );
		expect( options.body.toString() ).toBe(
			'status=complete&labels%5B%5D=2'
		);
		expect( answer ).toEqual( {
			ok: true,
			row: '<tr></tr>',
			notice: { message: 'Hi.' },
		} );
	} );

	test( 'a refusal is a failed save', async () => {
		const answer = await send( 'x', '', () =>
			Promise.reject( { code: 'rest_forbidden' } )
		);

		expect( answer ).toEqual( { ok: false, row: '', notice: null } );
	} );
} );
