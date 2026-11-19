/**
 * A list table in a jsdom page, with the layout a browser would give it.
 *
 * jsdom lays nothing out, so every box measures zero. Here each row of the
 * list measures as a browser would draw it: rows stacked in the order they
 * are in, a row taken out of the flow (position: fixed, display: none)
 * taking no room, the fixed one wherever its `top` says. A cell measures
 * its width -- until any cell of its row has a width set, after which every
 * cell measures differently, as a table re-laid out does. That is what
 * catches a script that sets one width before it has read the next.
 */

export const ROW = 40;
export const TOP = 100;

/**
 * Whether an element is out of the flow.
 *
 * @param {Element} element The element.
 * @return {boolean} Out.
 */
const outOfFlow = ( element ) =>
	'fixed' === element.style.position ||
	'none' === window.getComputedStyle( element ).display;

/**
 * Give a row of cells their widths, and the rule that setting one changes
 * them all.
 *
 * @param {Element}  row    The row.
 * @param {number[]} widths A width per cell.
 */
function measureCells( row, widths ) {
	let changed = false;

	Array.from( row.children ).forEach( ( cell, index ) => {
		const style = cell.style;
		const setProperty = style.setProperty.bind( style );

		// The script sets style.width; jsdom's CSSStyleDeclaration routes
		// that through setProperty.
		Object.defineProperty( style, 'width', {
			configurable: true,
			get: () => style.getPropertyValue( 'width' ),
			set: ( value ) => {
				if ( value ) {
					changed = true;
				}
				setProperty( 'width', value );
			},
		} );

		cell.getBoundingClientRect = () => {
			const width = widths[ index ] ?? 0;

			return { width: changed ? width - 7 : width, height: ROW };
		};
	} );
}

/**
 * Stack the list's rows as a browser would.
 *
 * The placeholder may be drawn taller than a row -- its cell has a height
 * of its own in reorder.css, padding on top -- and the rows under it move
 * down by the difference.
 *
 * @param {Element} list        The tbody.
 * @param {number}  placeholder The placeholder's height.
 */
function stack( list, placeholder ) {
	const heightOf = ( row ) =>
		row.classList.contains( 'sugarcommerce-reorder-placeholder' )
			? placeholder
			: ROW;

	const place = ( row ) => {
		if ( 'fixed' === row.style.position ) {
			return parseFloat( row.style.top ) || 0;
		}

		let top = TOP;

		for ( const before of list.children ) {
			if ( before === row ) {
				break;
			}

			if ( ! outOfFlow( before ) ) {
				top += heightOf( before );
			}
		}

		return top;
	};

	// Every row, the placeholder included the moment it is made.
	window.HTMLTableRowElement.prototype.getBoundingClientRect = function () {
		const top = this.parentElement === list ? place( this ) : 0;
		const height = heightOf( this );

		return { top, bottom: top + height, height, left: 10, width: 500 };
	};
	window.HTMLTableRowElement.prototype.getClientRects = function () {
		return 'none' === window.getComputedStyle( this ).display
			? []
			: [ this.getBoundingClientRect() ];
	};

	list.getBoundingClientRect = () => {
		const height = Array.from( list.children )
			.filter( ( row ) => ! outOfFlow( row ) )
			.reduce( ( total, row ) => total + heightOf( row ), 0 );

		return { top: TOP, bottom: TOP + height, height };
	};
}

/**
 * A reorderable list: a heading and rows with a handle, a number, a name
 * and two cells core hides because their columns are switched off.
 *
 * @param {number}   count               How many rows.
 * @param {Object}   options             Options.
 * @param {number}   options.first       The number the first row shows.
 * @param {number[]} options.widths      A width per cell, hidden ones included.
 * @param {number}   options.placeholder How tall the placeholder is drawn.
 * @return {{table: Element, list: Element, heading: Element}} The parts.
 */
export function reorderTable(
	count,
	{ first = 1, widths = [ 34, 182, 0, 141, 0, 98 ], placeholder = ROW } = {}
) {
	document.head.innerHTML = '<style>.hidden { display: none; }</style>';
	document.body.innerHTML = `
		<div class="wrap"><h1>List</h1><hr class="wp-header-end">
		<table class="wp-list-table widefat">
			<thead><tr>
				<td class="check-column"></td>
				<th class="column-primary">Name</th>
				<th class="column-country hidden">Country</th>
				<th class="column-position">Position</th>
				<th class="column-notes hidden">Notes</th>
				<th class="column-date">Date</th>
			</tr></thead>
			<tbody id="the-list"></tbody>
		</table></div>`;

	const list = document.getElementById( 'the-list' );

	for ( let i = 0; i < count; i++ ) {
		const id = String( 11 + i );

		list.insertAdjacentHTML(
			'beforeend',
			`<tr id="item-${ id }">
				<th class="check-column"><input type="checkbox" value="${ id }"></th>
				<td class="column-primary"><strong><a class="row-title" href="#">Row ${ id }</a></strong></td>
				<td class="column-country hidden">GB</td>
				<td class="column-position"><span class="sugarcommerce-reorder" data-id="${ id }"><button type="button" class="sugarcommerce-reorder__handle">Move</button><span class="sugarcommerce-reorder__number">${
					first + i
				}</span><button type="button" class="button sugarcommerce-reorder__step" data-step="-1">Up</button><button type="button" class="button sugarcommerce-reorder__step" data-step="1">Down</button></span></td>
				<td class="column-notes hidden"></td>
				<td class="column-date">Today</td>
			</tr>`
		);
	}

	const heading = document.querySelector( 'thead tr' );

	measureCells( heading, widths );
	Array.from( list.children ).forEach( ( row ) =>
		measureCells( row, widths )
	);
	stack( list, placeholder );

	window.scrollBy = () => {};

	return { table: list.parentElement, list, heading };
}

/**
 * The ids of the list's rows, top to bottom.
 *
 * @param {Element} list The tbody.
 * @return {string[]} Ids.
 */
export const ids = ( list ) =>
	Array.from( list.querySelectorAll( '.sugarcommerce-reorder' ) ).map(
		( handle ) => handle.dataset.id
	);

/**
 * A pointer event.
 *
 * @param {string} type  pointerdown, pointermove, pointerup, pointercancel.
 * @param {number} y     Where, down the window.
 * @param {Object} extra Anything else: pointerId, button, pointerType.
 * @return {PointerEvent} The event.
 */
export const pointer = ( type, y, extra = {} ) =>
	new window.PointerEvent( type, {
		bubbles: true,
		cancelable: true,
		clientX: 50,
		clientY: y,
		pointerId: 1,
		isPrimary: true,
		button: 'pointermove' === type ? -1 : 0,
		...extra,
	} );

/**
 * A fetch that answers every request with the same JSON, and keeps them.
 *
 * @param {*} answer What to answer.
 * @return {Function} The fetch; its `.calls` are [ url, options ] pairs.
 */
export function fakeFetch( answer = { success: true } ) {
	const calls = [];
	const fetch = ( url, options ) => {
		calls.push( [ url, options ] );

		return Promise.resolve( {
			ok: true,
			json: () => Promise.resolve( answer ),
		} );
	};

	fetch.calls = calls;

	return fetch;
}

/**
 * Let every settled promise's callbacks run.
 *
 * @return {Promise<void>} After them.
 */
export const flush = () => new Promise( ( resolve ) => setTimeout( resolve ) );
