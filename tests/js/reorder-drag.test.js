/**
 * Dragging a row.
 *
 * A row being dragged is lifted out of its table, and three things keep the
 * table looking like itself while it is: the gap it will drop into spans
 * the columns that are shown, the lifted row's cells keep the widths they
 * had, and they let go of them when it lands. The PHP suite draws the
 * handle and never runs a line of the drag, which is how a list with a
 * column switched off in Screen Options came to grow empty columns down
 * its right edge whenever a row was picked up.
 *
 * Ported from the node script that ran the jQuery UI callbacks against a
 * stub; these drive the drag itself, with pointer events.
 */

import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { beforeEach, describe, expect, test, vi } from 'vitest';

import { fillGap, hold, release, span } from '../../assets/js/reorder/cells';
import { initReorder } from '../../assets/js/reorder/init';
import {
	ROW,
	TOP,
	fakeApiFetch,
	flush,
	ids,
	pointer,
	reorderTable,
} from './support/table';

let parts;
let apiFetch;
let speak;

/**
 * Press on a row's handle and move the pointer, without letting go.
 *
 * @param {number} index Which row.
 * @param {number} to    How far down the window to move to.
 * @param {Object} extra Anything else the events carry.
 * @return {Element} The handle.
 */
function pickUp( index, to, extra = {} ) {
	const handle = parts.list.querySelectorAll(
		'.sugarcommerce-reorder__handle'
	)[ index ];
	const from = TOP + index * ROW + ROW / 2;

	handle.dispatchEvent( pointer( 'pointerdown', from, extra ) );
	window.dispatchEvent( pointer( 'pointermove', from + 2, extra ) );
	window.dispatchEvent( pointer( 'pointermove', to, extra ) );

	return handle;
}

/**
 * Let go.
 *
 * @param {number} at    Where.
 * @param {string} type  pointerup, or pointercancel.
 * @param {Object} extra Anything else the event carries.
 */
const letGo = ( at, type = 'pointerup', extra = {} ) =>
	window.dispatchEvent( pointer( type, at, extra ) );

const placeholder = () =>
	parts.list.querySelector( 'tr.sugarcommerce-reorder-placeholder' );

beforeEach( () => {
	parts = reorderTable( 5 );
	apiFetch = fakeApiFetch();
	speak = vi.fn();
	initReorder( parts.list, {
		config: {
			path: 'sugarcommerce-tables/v1/demo/reorder',
			i18n: { moved: '“%1$s” moved to position %2$s of %3$s.' },
		},
		apiFetch,
		speak,
	} );
} );

describe( 'the gap a row drops into', () => {
	test( 'is one cell spanning the shown columns, not all six', () => {
		pickUp( 1, TOP + ROW * 3 );

		const gap = placeholder();

		expect( gap ).not.toBeNull();
		expect( gap.classList.contains( 'ui-sortable-placeholder' ) ).toBe(
			true
		);
		expect( gap.children ).toHaveLength( 1 );
		expect( gap.children[ 0 ].tagName ).toBe( 'TD' );
		expect( gap.children[ 0 ].colSpan ).toBe( 4 );
		expect( gap.style.height ).toBe( ROW + 'px' );

		letGo( TOP + ROW * 3 );
	} );

	test( 'counts a cell across two columns as two', () => {
		const row = document.createElement( 'tr' );

		row.innerHTML =
			'<td></td><td colspan="2"></td><td class="hidden"></td><td></td>';
		document.querySelector( 'tbody' ).appendChild( row );

		const gap = document.createElement( 'tr' );

		fillGap( gap, row );

		expect( gap.children ).toHaveLength( 1 );
		expect( gap.children[ 0 ].colSpan ).toBe( 4 );
	} );

	test( 'spans every column when none is hidden', () => {
		const row = document.createElement( 'tr' );

		row.innerHTML = '<td></td><td></td><td></td><td></td>';
		document.querySelector( 'tbody' ).appendChild( row );

		expect( span( row ) ).toBe( 4 );
	} );
} );

describe( 'the lifted row', () => {
	test( 'keeps the widths its cells had, measured before any was set, as border boxes', () => {
		const row = parts.list.children[ 1 ];

		pickUp( 1, TOP + ROW * 3 );

		expect( row.classList.contains( 'ui-sortable-helper' ) ).toBe( true );
		expect( row.style.position ).toBe( 'fixed' );

		const cells = Array.from( row.children ).map( ( cell ) => [
			cell.style.width,
			cell.style.boxSizing,
		] );

		// The two hidden cells are left alone.
		expect( cells ).toEqual( [
			[ '34px', 'border-box' ],
			[ '182px', 'border-box' ],
			[ '', '' ],
			[ '141px', 'border-box' ],
			[ '', '' ],
			[ '98px', 'border-box' ],
		] );

		letGo( TOP + ROW * 3 );
	} );

	test( 'leaves the heading alone: a fixed table keeps its columns', () => {
		pickUp( 1, TOP + ROW * 3 );

		Array.from( parts.heading.children ).forEach( ( cell, index ) => {
			expect( cell.style.width, `heading ${ index }` ).toBe( '' );
		} );

		letGo( TOP + ROW * 3 );
	} );

	test( 'lets go of every width when it lands', () => {
		const row = parts.list.children[ 1 ];

		pickUp( 1, TOP + ROW * 3 );
		letGo( TOP + ROW * 3 );

		Array.from( row.children ).forEach( ( cell, index ) => {
			expect( cell.style.width, `cell ${ index }` ).toBe( '' );
			expect( cell.style.boxSizing, `cell ${ index }` ).toBe( '' );
		} );
		expect( row.classList.contains( 'ui-sortable-helper' ) ).toBe( false );
		expect( row.style.position ).toBe( '' );
		expect( row.style.top ).toBe( '' );
		expect( placeholder() ).toBeNull();
	} );

	test( 'stays inside the list, top and bottom', () => {
		const row = parts.list.children[ 2 ];

		pickUp( 2, 0 );
		expect( parseFloat( row.style.top ) ).toBe( TOP );

		window.dispatchEvent( pointer( 'pointermove', 5000 ) );
		expect( parseFloat( row.style.top ) ).toBe( TOP + 4 * ROW );

		letGo( 5000 );
	} );

	test( 'lays its cells out fixed, by the widths it is given', () => {
		const css = readFileSync(
			join( __dirname, '../../assets/css/reorder.css' ),
			'utf8'
		);
		const rule = css.match(
			/\.wp-list-table tr\.ui-sortable-helper\s*\{([^}]*)\}/
		);

		expect( rule ).not.toBeNull();
		expect( rule[ 1 ] ).toMatch( /table-layout:\s*fixed/ );
	} );
} );

describe( 'a drag', () => {
	test( 'drops the row where the pointer is, and posts the new order', async () => {
		pickUp( 1, TOP + ROW * 3 + 25 );
		letGo( TOP + ROW * 3 + 25 );
		await flush();

		expect( ids( parts.list ) ).toEqual( [ '11', '13', '14', '12', '15' ] );
		expect(
			Array.from(
				parts.list.querySelectorAll( '.sugarcommerce-reorder__number' )
			).map( ( number ) => number.textContent )
		).toEqual( [ '1', '2', '3', '4', '5' ] );

		expect( apiFetch.calls ).toHaveLength( 1 );

		expect( apiFetch.calls[ 0 ][ 0 ] ).toEqual( {
			path: 'sugarcommerce-tables/v1/demo/reorder',
			method: 'POST',
			data: { ids: [ 11, 13, 14, 12, 15 ] },
		} );
		expect( speak ).toHaveBeenCalledWith(
			'“Row 12” moved to position 4 of 5.',
			'assertive'
		);
	} );

	test( 'moves up as well as down, to the very top', async () => {
		pickUp( 3, 0 );
		letGo( 0 );
		await flush();

		expect( ids( parts.list ) ).toEqual( [ '14', '11', '12', '13', '15' ] );
	} );

	test( 'and to the very bottom', async () => {
		pickUp( 0, 5000 );
		letGo( 5000 );
		await flush();

		expect( ids( parts.list ) ).toEqual( [ '12', '13', '14', '15', '11' ] );
	} );

	test( 'that lands where it started posts nothing', async () => {
		pickUp( 1, TOP + ROW * 1 + 10 );
		letGo( TOP + ROW * 1 + 10 );
		await flush();

		expect( ids( parts.list ) ).toEqual( [ '11', '12', '13', '14', '15' ] );
		expect( apiFetch.calls ).toHaveLength( 0 );
	} );

	test( 'a press that never moves is not a drag', async () => {
		const handle = parts.list.querySelector(
			'.sugarcommerce-reorder__handle'
		);

		handle.dispatchEvent( pointer( 'pointerdown', TOP + 20 ) );
		expect( placeholder() ).toBeNull();
		letGo( TOP + 20 );
		await flush();

		expect( apiFetch.calls ).toHaveLength( 0 );
		expect( parts.list.children[ 0 ].style.position ).toBe( '' );
	} );

	test( 'a cancelled touch puts the row back and posts nothing', async () => {
		pickUp( 1, TOP + ROW * 3 + 25, { pointerType: 'touch' } );
		letGo( TOP + ROW * 3 + 25, 'pointercancel', { pointerType: 'touch' } );
		await flush();

		expect( ids( parts.list ) ).toEqual( [ '11', '12', '13', '14', '15' ] );
		expect( placeholder() ).toBeNull();
		expect( apiFetch.calls ).toHaveLength( 0 );
	} );

	test( 'the gap passes a row as soon as the pointer is over it, going down', async () => {
		// The fourth row's slot runs 220 to 260 while the first is lifted;
		// at 225 the pointer has only just reached it.
		pickUp( 0, TOP + ROW * 3 + 5 );
		letGo( TOP + ROW * 3 + 5 );
		await flush();

		expect( ids( parts.list ) ).toEqual( [ '12', '13', '14', '11', '15' ] );
	} );

	test( 'and goes above a row the pointer is over, going up', async () => {
		pickUp( 3, TOP + ROW + 25 );
		letGo( TOP + ROW + 25 );
		await flush();

		expect( ids( parts.list ) ).toEqual( [ '11', '14', '12', '13', '15' ] );
	} );

	test( 'over the gap itself, nothing moves', async () => {
		// Up a little, then down to the gap's bottom edge.
		pickUp( 2, TOP + ROW * 2 + 5 );
		window.dispatchEvent( pointer( 'pointermove', TOP + ROW * 3 - 2 ) );
		letGo( TOP + ROW * 3 - 2 );
		await flush();

		expect( ids( parts.list ) ).toEqual( [ '11', '12', '13', '14', '15' ] );
		expect( apiFetch.calls ).toHaveLength( 0 );
	} );

	test( 'a placeholder drawn taller than the row does not shorten the move', async () => {
		// reorder.css gives the gap's cell a height of its own, and the
		// rows under it sit lower than they did.
		parts = reorderTable( 5, { placeholder: ROW + 24 } );
		initReorder( parts.list, { config: { i18n: {} }, apiFetch, speak } );

		// From the middle of the second row, down a little over two rows.
		pickUp( 1, TOP + ROW * 3 + 25 );
		letGo( TOP + ROW * 3 + 25 );
		await flush();

		expect( ids( parts.list ) ).toEqual( [ '11', '13', '14', '12', '15' ] );
	} );

	test( 'a finger drags as a mouse does', async () => {
		pickUp( 0, TOP + ROW * 2 + 25, { pointerType: 'touch', pointerId: 7 } );
		letGo( TOP + ROW * 2 + 25, 'pointerup', {
			pointerType: 'touch',
			pointerId: 7,
		} );
		await flush();

		expect( ids( parts.list ) ).toEqual( [ '12', '13', '11', '14', '15' ] );
	} );

	test( 'follows only the pointer that started it', () => {
		const row = parts.list.children[ 1 ];

		pickUp( 1, TOP + ROW * 3 );
		window.dispatchEvent( pointer( 'pointerup', TOP, { pointerId: 2 } ) );

		expect( row.classList.contains( 'ui-sortable-helper' ) ).toBe( true );

		letGo( TOP + ROW * 3 );
	} );

	test.each( [
		[ 'a step button', '.sugarcommerce-reorder__step' ],
		[ 'a tick box', 'input[type="checkbox"]' ],
		[ 'a link', 'a.row-title' ],
		[ 'a row cell', '.column-date' ],
	] )( 'never starts on %s', ( name, selector ) => {
		const target = parts.list.children[ 1 ].querySelector( selector );

		target.dispatchEvent( pointer( 'pointerdown', TOP + ROW + 20 ) );
		window.dispatchEvent( pointer( 'pointermove', TOP + ROW * 4 ) );

		expect( placeholder() ).toBeNull();
		letGo( TOP + ROW * 4 );
	} );

	test( 'never starts on a link, even one inside a handle', () => {
		const grip = parts.list.children[ 1 ].querySelector(
			'.sugarcommerce-reorder__handle'
		);

		grip.insertAdjacentHTML( 'beforeend', '<a href="#">help</a>' );
		grip.querySelector( 'a' ).dispatchEvent(
			pointer( 'pointerdown', TOP + ROW + 20 )
		);
		window.dispatchEvent( pointer( 'pointermove', TOP + ROW * 4 ) );

		expect( placeholder() ).toBeNull();
		letGo( TOP + ROW * 4 );
	} );

	test( 'never starts with a button other than the main one', () => {
		const handle = parts.list.children[ 1 ].querySelector(
			'.sugarcommerce-reorder__handle'
		);

		handle.dispatchEvent(
			pointer( 'pointerdown', TOP + ROW + 20, { button: 2 } )
		);
		window.dispatchEvent( pointer( 'pointermove', TOP + ROW * 4 ) );

		expect( placeholder() ).toBeNull();
	} );

	test( "keeps sortable's classes on the list and its handles", () => {
		expect( parts.list.classList.contains( 'ui-sortable' ) ).toBe( true );
		expect(
			parts.list
				.querySelector( '.sugarcommerce-reorder__handle' )
				.classList.contains( 'ui-sortable-handle' )
		).toBe( true );
	} );
} );

test( 'a list of one row is not made draggable', () => {
	parts = reorderTable( 1 );
	initReorder( parts.list, { config: { i18n: {} }, apiFetch, speak } );

	expect( parts.list.classList.contains( 'ui-sortable' ) ).toBe( false );
} );

test( 'hold and release, on their own', () => {
	const row = parts.list.children[ 0 ];

	hold( row );
	expect( row.children[ 3 ].style.width ).toBe( '141px' );

	release( row );
	expect( row.children[ 3 ].style.width ).toBe( '' );
} );
