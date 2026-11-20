/**
 * Dragging a table's rows into another order, with pointer events.
 *
 * What jQuery UI's sortable did for this list, and only that, so the list
 * needs neither it, jQuery, nor touch punch: a pointer event is a mouse, a
 * pen and a finger alike. The behaviour and the classes are sortable's, so
 * the stylesheet and anything else that looked for them still finds them:
 *
 * - the list is `ui-sortable` and each handle `ui-sortable-handle`;
 * - a drag starts on a handle, never on anything `cancel` names, once the
 *   pointer has moved;
 * - the row is lifted where it is (`ui-sortable-helper`), held at its size
 *   and moved along the list's height only, never past its ends;
 * - a placeholder row, of the class given and `ui-sortable-placeholder`,
 *   as tall as the row, marks where it will land, and moves past a row as
 *   soon as the pointer is over it (sortable's `pointer` tolerance);
 * - near the top or bottom of the window the page scrolls;
 * - on release the row takes the placeholder's place, `update` is called
 *   if that is a new place, then `stop`.
 *
 * The callbacks are sortable's too, with the row and the placeholder as
 * elements: helper( event, row ), start( event, { item, placeholder } ),
 * update( event, { item } ), stop( event, { item } ).
 */

// How near the window's edge, and how far a step, the page scrolls by while
// a row is dragged there: sortable's own numbers.
const SCROLL_EDGE = 20;
const SCROLL_STEP = 20;

/**
 * Make a list's rows draggable.
 *
 * @param {Element}  list                The tbody.
 * @param {Object}   options             Options.
 * @param {string}   options.handle      What a drag starts on.
 * @param {string}   options.cancel      What a drag never starts on.
 * @param {string}   options.placeholder The placeholder row's class.
 * @param {Function} options.helper      Called as the row is lifted.
 * @param {Function} options.start       Called once the placeholder is in.
 * @param {Function} options.update      Called when the row lands somewhere new.
 * @param {Function} options.stop        Called when the row has landed.
 * @return {Function} Stops listening.
 */
export function sortable(
	list,
	{
		handle,
		cancel = '',
		placeholder: placeholderClass = '',
		helper = () => {},
		start = () => {},
		update = () => {},
		stop = () => {},
	}
) {
	const view = list.ownerDocument.defaultView;
	let drag = null;

	list.classList.add( 'ui-sortable' );

	/**
	 * Mark the handles, as sortable did when it started.
	 */
	const markHandles = () =>
		list
			.querySelectorAll( handle )
			.forEach( ( grip ) => grip.classList.add( 'ui-sortable-handle' ) );

	markHandles();

	/**
	 * The rows the placeholder can move among: everything in the list that
	 * is shown, but the row being dragged and the placeholder itself.
	 *
	 * @return {Element[]} Rows, top to bottom.
	 */
	const others = () =>
		Array.from( list.children ).filter(
			( row ) =>
				row !== drag.item &&
				row !== drag.placeholder &&
				row.getClientRects().length > 0
		);

	/**
	 * Lift the row and put the placeholder where it was.
	 *
	 * @param {PointerEvent} event The move that started it.
	 */
	const lift = ( event ) => {
		const { item } = drag;
		const box = item.getBoundingClientRect();

		helper( event, item );

		drag.stored = {
			position: item.style.position,
			top: item.style.top,
			left: item.style.left,
			width: item.style.width,
			height: item.style.height,
			zIndex: item.style.zIndex,
		};
		drag.grab = drag.startY - box.top;
		drag.left = box.left;
		drag.height = box.height;
		drag.next = item.nextElementSibling;

		// One cell per cell of the row, spanning what it spanned, as
		// sortable drew it; `start` is where a list redraws it.
		const placeholder = document.createElement( 'tr' );

		placeholder.className = (
			'ui-sortable-placeholder ' + placeholderClass
		).trim();
		Array.from( item.children ).forEach( ( cell ) => {
			const gap = document.createElement( 'td' );

			gap.colSpan = cell.colSpan || 1;
			placeholder.appendChild( gap );
		} );
		placeholder.style.height = box.height + 'px';
		item.after( placeholder );
		drag.placeholder = placeholder;

		item.style.position = 'fixed';
		item.style.left = box.left + 'px';
		item.style.top = box.top + 'px';
		item.style.width = box.width + 'px';
		item.style.height = box.height + 'px';
		item.style.zIndex = '1000';

		start( event, { item, placeholder } );

		item.classList.add( 'ui-sortable-helper' );
		list.ownerDocument.body.style.userSelect = 'none';
		drag.started = true;
	};

	/**
	 * Follow the pointer: move the row, and move the placeholder past a row
	 * the moment the pointer is over it -- below it on the way down, above
	 * it on the way up. That is sortable's `pointer` tolerance, and what
	 * makes a drag of two rows' height move a row two places, whatever
	 * height the placeholder or the lifted row is drawn at. Past either end
	 * of the list, it goes to that end.
	 *
	 * @param {number} y The pointer's height in the window.
	 */
	const follow = ( y ) => {
		const { item, placeholder } = drag;
		const bounds = list.getBoundingClientRect();
		const top = Math.min(
			Math.max( y - drag.grab, bounds.top ),
			bounds.bottom - drag.height
		);

		item.style.top = top + 'px';

		if ( y !== drag.lastY ) {
			drag.down = y > drag.lastY;
			drag.lastY = y;
		}

		const rows = others();
		const first = rows[ 0 ];
		const last = rows[ rows.length - 1 ];
		const over = rows.find( ( row ) => {
			const box = row.getBoundingClientRect();

			return y >= box.top && y < box.bottom;
		} );

		let target = over;
		let after = drag.down;

		if ( ! over && first && y < first.getBoundingClientRect().top ) {
			target = first;
			after = false;
		} else if (
			! over &&
			last &&
			y >= last.getBoundingClientRect().bottom
		) {
			target = last;
			after = true;
		}

		if ( target && after && target.nextElementSibling !== placeholder ) {
			target.after( placeholder );
		} else if (
			target &&
			! after &&
			placeholder.nextElementSibling !== target
		) {
			list.insertBefore( placeholder, target );
		}

		if ( y < SCROLL_EDGE ) {
			view.scrollBy( 0, -SCROLL_STEP );
		} else if ( view.innerHeight - y < SCROLL_EDGE ) {
			view.scrollBy( 0, SCROLL_STEP );
		}
	};

	/**
	 * Put the row down where the placeholder is, or back where it was.
	 *
	 * @param {Event}   event  The release.
	 * @param {boolean} commit False to put it back (a cancelled touch).
	 */
	const land = ( event, commit ) => {
		const { item, placeholder, stored, next } = drag;
		const before = drag.previous;

		list.insertBefore( item, commit ? placeholder : next );

		placeholder.remove();
		Object.assign( item.style, stored );
		item.classList.remove( 'ui-sortable-helper' );
		list.ownerDocument.body.style.userSelect = '';
		drag = null;

		if ( commit && item.previousElementSibling !== before ) {
			update( event, { item } );
		}

		stop( event, { item } );
	};

	const onMove = ( event ) => {
		if ( ! drag || event.pointerId !== drag.pointer ) {
			return;
		}

		event.preventDefault();

		if ( ! drag.started ) {
			// Sortable's distance: one pixel, either way.
			if (
				Math.max(
					Math.abs( event.clientX - drag.startX ),
					Math.abs( event.clientY - drag.startY )
				) < 1
			) {
				return;
			}

			lift( event );
		}

		follow( event.clientY );
	};

	const onEnd = ( event ) => {
		if ( ! drag || event.pointerId !== drag.pointer ) {
			return;
		}

		view.removeEventListener( 'pointermove', onMove );
		view.removeEventListener( 'pointerup', onEnd );
		view.removeEventListener( 'pointercancel', onEnd );

		if ( drag.started ) {
			land( event, 'pointercancel' !== event.type );
		} else {
			drag = null;
		}
	};

	const onDown = ( event ) => {
		if ( drag || 0 !== event.button || ! event.isPrimary ) {
			return;
		}

		const target =
			event.target instanceof view.Element ? event.target : null;
		const grip = target ? target.closest( handle ) : null;
		const item = grip ? grip.closest( 'tr' ) : null;

		if (
			! item ||
			item.parentElement !== list ||
			( cancel && target.closest( cancel ) )
		) {
			return;
		}

		markHandles();

		drag = {
			item,
			pointer: event.pointerId,
			startX: event.clientX,
			startY: event.clientY,
			lastY: event.clientY,
			down: true,
			previous: item.previousElementSibling,
			started: false,
		};

		// No text selected as the pointer sweeps down the list, which is
		// what sortable's own preventDefault on the press was for.
		event.preventDefault();

		view.addEventListener( 'pointermove', onMove );
		view.addEventListener( 'pointerup', onEnd );
		view.addEventListener( 'pointercancel', onEnd );
	};

	list.addEventListener( 'pointerdown', onDown );

	return () => list.removeEventListener( 'pointerdown', onDown );
}
