/**
 * Tests for the reorder script's drag.
 *
 * A row being dragged is lifted out of its table, and three things keep the
 * table looking like itself while it is: the gap it will drop into spans
 * the columns that are shown, the lifted row's cells keep the widths they
 * had, and they let go of them when it lands. The PHP suite draws the
 * handle and never runs a line of the drag, which is how a list with a
 * column switched off in Screen Options came to grow empty columns down
 * its right edge whenever a row was picked up.
 *
 * Run against a few stand-in elements and a jQuery whose sortable() keeps
 * the options it is given -- the callbacks are what is under test, and
 * sortable itself is core's. Deliberately no jsdom: this library has no
 * JavaScript dependencies and a test suite is a poor reason to acquire the
 * first one.
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const root = path.join( __dirname, '..', '..', 'assets' );
const source = fs.readFileSync( path.join( root, 'js', 'reorder.js' ), 'utf8' );
const css = fs.readFileSync( path.join( root, 'css', 'reorder.css' ), 'utf8' );

let failures = 0;
let passed = 0;

/**
 * Assert two values match.
 *
 * @param {*}      actual   What happened.
 * @param {*}      expected What should have.
 * @param {string} what     The name of the thing.
 */
function is( actual, expected, what ) {
	if ( actual === expected ) {
		passed++;
		return;
	}

	console.error(
		`  ${ what }: expected ${ JSON.stringify( expected ) }, got ${ JSON.stringify( actual ) }`
	);
	failures++;
}

/**
 * A table cell.
 *
 * Its width is what the browser would measure. Like a browser, it is only
 * right until something in the row changes: once any cell of the row has a
 * width set, every cell measures differently.
 *
 * @param {Object}  state   The row's shared state.
 * @param {number}  width   Its width on the screen.
 * @param {boolean} hidden  Whether core hides it (a column switched off).
 * @param {number}  colSpan How many columns it spans.
 * @return {Object} The cell.
 */
function cell( state, width, hidden = false, colSpan = 1 ) {
	return {
		tagName: 'TD',
		colSpan,
		hidden,
		style: {},
		getBoundingClientRect() {
			return { width: state.changed ? width - 7 : width };
		},
	};
}

/**
 * A row of the list, with the reorder handle in its first cell.
 *
 * @param {Array} cells The widths of its cells, or [ width, hidden, colSpan ].
 * @return {Object} The row.
 */
function row( cells ) {
	const state = { changed: false };
	const children = cells.map( ( spec ) =>
		cell( state, ...[].concat( spec ) )
	);

	children.forEach( ( child ) => {
		child.style = new Proxy(
			{},
			{
				set( target, name, value ) {
					target[ name ] = value;
					state.changed = true;
					return true;
				},
			}
		);
	} );

	return {
		tagName: 'TR',
		children,
		querySelector( selector ) {
			if ( '.sugarcommerce-reorder__number' === selector ) {
				return { textContent: '1' };
			}

			return '.sugarcommerce-reorder' === selector
				? { dataset: { id: '1' } }
				: null;
		},
	};
}

/**
 * Just enough of a jQuery collection for what a sortable callback is
 * handed, so the script may use either it or the elements inside it.
 *
 * @param {Object|Object[]} elements One element or several.
 * @param {Function}        onSort   Given the options passed to sortable().
 * @return {Object} The collection.
 */
function wrap( elements, onSort = () => {} ) {
	const all = [].concat( elements );
	const px = ( value ) =>
		'number' === typeof value ? value + 'px' : value;

	return {
		length: all.length,
		get: ( index ) => ( undefined === index ? all[ 0 ] : all[ index ] ),
		children: () => wrap( all[ 0 ].children ),
		map: ( each ) => ( {
			get: () =>
				all.map( ( element, index ) =>
					each.call( element, index, element )
				),
		} ),
		each( each ) {
			all.forEach( ( element, index ) =>
				each.call( element, index, element )
			);
			return this;
		},
		css( name, value ) {
			all.forEach( ( element ) => {
				element.style[ name ] = px( value );
			} );
			return this;
		},
		outerWidth: () => all[ 0 ].getBoundingClientRect().width,
		html( markup ) {
			const span = /colspan="(\d+)"/.exec( markup );

			all[ 0 ].replaceChildren( {
				tagName: 'TD',
				colSpan: span ? Number( span[ 1 ] ) : 1,
			} );
			return this;
		},
		sortable: onSort,
	};
}

/**
 * Load the script over a list of rows.
 *
 * @param {Object[]} rows The rows.
 * @return {Object} The options the script gave sortable().
 */
function load( rows ) {
	let options = null;

	const $ = ( element ) =>
		wrap( element, ( given ) => {
			options = given;
		} );

	$.fn = { sortable() {} };

	const list = {
		children: rows,
		addEventListener() {},
		parentElement: {
			querySelector: ( selector ) =>
				'thead tr' === selector ? heading : null,
		},
	};
	const context = {
		jQuery: $,
		sugarCommerceReorder: { i18n: {} },
		getComputedStyle: ( element ) => ( {
			display: element.hidden ? 'none' : 'table-cell',
		} ),
		document: {
			getElementById: ( id ) => ( 'the-list' === id ? list : null ),
			createElement: ( tag ) => ( {
				tagName: tag.toUpperCase(),
				colSpan: 1,
			} ),
			querySelector: () => null,
		},
		URLSearchParams,
		parseInt,
		Number,
		Array,
		String,
		Infinity,
	};

	context.window = context;

	vm.runInNewContext( source, context, { filename: 'reorder.js' } );

	return options;
}

/**
 * A placeholder row, as sortable makes one.
 *
 * @return {Object} The row.
 */
function placeholder() {
	return {
		children: [ { tagName: 'TD' }, { tagName: 'TD' } ],
		replaceChildren( ...nodes ) {
			this.children = nodes;
		},
	};
}

// The table's heading row, held while a row is dragged.
const heading = row( [ 34, 182, [ 0, true ], 141, [ 0, true ], 98 ] );

// A tick box, four columns, two of them switched off in Screen Options.
const lifted = row( [ 34, 182, [ 0, true ], 141, [ 0, true ], 98 ] );
const options = load( [
	lifted,
	row( [ 34, 182, [ 0, true ], 141, [ 0, true ], 98 ] ),
] );

is( null !== options, true, 'the list is made sortable' );

// The gap spans the columns that are shown, not the ones that are hidden.
const gap = placeholder();

options.start( {}, { item: wrap( lifted ), placeholder: wrap( gap ) } );

is( gap.children.length, 1, 'the gap is one cell' );
is( gap.children[ 0 ].tagName, 'TD', "the gap's cell is a td" );
is(
	gap.children[ 0 ].colSpan,
	4,
	'the gap spans the four shown columns, not all six'
);

// A cell that spans two shown columns counts as two.
const wide = row( [ 34, [ 300, false, 2 ], [ 0, true ], 98 ] );
const wideGap = placeholder();

options.start( {}, { item: wrap( wide ), placeholder: wrap( wideGap ) } );
is(
	wideGap.children[ 0 ].colSpan,
	4,
	'a cell across two columns counts as two'
);

// With every column shown, the gap spans every one.
const full = row( [ 34, 182, 141, 98 ] );
const fullGap = placeholder();

options.start( {}, { item: wrap( full ), placeholder: wrap( fullGap ) } );
is(
	fullGap.children[ 0 ].colSpan,
	4,
	'with nothing hidden, the gap spans every column'
);

// The lifted row's cells keep the widths they were measured at, before any
// was set, as border boxes.
const returned = options.helper( {}, wrap( lifted ) );

is( returned.get(), lifted, 'the helper is the row itself' );
[ 34, 182, null, 141, null, 98 ].forEach( ( width, index ) => {
	const style = lifted.children[ index ].style;

	if ( null === width ) {
		is( style.width, undefined, `hidden cell ${ index } is left alone` );
		return;
	}

	is( style.width, width + 'px', `cell ${ index } keeps the width it had` );
	is(
		style.boxSizing,
		'border-box',
		`cell ${ index }'s width is its border box`
	);
} );

// The heading is held too, so the columns do not move under the drag.
is( heading.children[ 1 ].style.width, '182px', 'the heading keeps its widths while a row is dragged' );

// Landing lets the widths go.
options.stop( {}, { item: wrap( lifted ) } );
is( heading.children[ 1 ].style.width || '', '', 'the heading lets go of its widths on landing' );
lifted.children.forEach( ( child, index ) => {
	is( child.style.width || '', '', `cell ${ index } lets go of its width` );
	is(
		child.style.boxSizing || '',
		'',
		`cell ${ index } lets go of its box sizing`
	);
} );

// The lifted row is a table of its own, and lays its cells out as the list
// does: by the widths it is given, not by what is in them.
const rule = css.match(
	/\.wp-list-table tr\.ui-sortable-helper\s*\{([^}]*)\}/
);

is( Boolean( rule ), true, 'the lifted row has a rule' );
is(
	/table-layout:\s*fixed/.test( rule ? rule[ 1 ] : '' ),
	true,
	'the lifted row lays out its cells fixed'
);

if ( failures ) {
	console.error( `reorder.js: ${ failures } failed, ${ passed } passed` );
	process.exit( 1 );
}

console.log( `reorder.js: ${ passed } passed` );
