/**
 * The two ways of moving a row that are not a drag: the arrow keys on its
 * handle, and -- on a screen too narrow to drag a row along -- the up and
 * down buttons.
 */

/**
 * Listen for the keys and the buttons on a list.
 *
 * @param {Element}  list The tbody.
 * @param {Function} step Moves a row: ( row, -1 | 1 ).
 */
export function bindControls( list, step ) {
	list.addEventListener( 'keydown', ( event ) => {
		const handle = event.target.closest( '.sugarcommerce-reorder__handle' );

		if ( ! handle || ! [ 'ArrowUp', 'ArrowDown' ].includes( event.key ) ) {
			return;
		}

		// Either way: an arrow key on a handle is never a request to
		// scroll the page.
		event.preventDefault();

		step( handle.closest( 'tr' ), 'ArrowUp' === event.key ? -1 : 1 );

		// Moving the row moved the button, and a browser may drop focus
		// from an element that has been re-inserted.
		handle.focus();
	} );

	list.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '.sugarcommerce-reorder__step' );

		if ( ! button ) {
			return;
		}

		event.preventDefault();

		step( button.closest( 'tr' ), parseInt( button.dataset.step, 10 ) );
		button.focus();
	} );
}
