/**
 * Putting a list's rows in order.
 *
 * A list table sorts by a column and knows nothing of an order somebody
 * chose, so moving a row is done here. There are three ways to do it and
 * one function behind all of them: drag the handle, press the arrow keys
 * while it has focus, or -- on a screen too narrow to drag a row along --
 * tap the up and down buttons. Each ends by posting the ids as they now sit.
 */
( function () {
	'use strict';

	const $ = window.jQuery;

	const config = window.sugarCommerceReorder || { i18n: {} };
	const list = document.getElementById( 'the-list' );

	if ( ! list ) {
		return;
	}

	/**
	 * Fill the numbered places in a translated sentence.
	 *
	 * @param {string}    text   The sentence, with %1$s, %2$s or a bare %s.
	 * @param {...string} values What goes in them, in order.
	 * @return {string} The sentence.
	 */
	function fill( text, ...values ) {
		return String( text || '' )
			.replace( /%(\d)\$s/g, ( match, n ) => values[ n - 1 ] ?? '' )
			.replace( '%s', values[ 0 ] ?? '' );
	}

	/**
	 * Say something to a screen reader, when there is a way to.
	 *
	 * @param {string} message What to say.
	 */
	function speak( message ) {
		if ( window.wp && window.wp.a11y && message ) {
			window.wp.a11y.speak( message, 'assertive' );
		}
	}

	/**
	 * The rows that can be moved.
	 *
	 * @return {Element[]} Rows, top to bottom.
	 */
	function rows() {
		return Array.from( list.children ).filter( ( row ) =>
			row.querySelector( '.sugarcommerce-reorder' )
		);
	}

	/**
	 * What a row is called.
	 *
	 * @param {Element} row The row.
	 * @return {string} Its name.
	 */
	function nameOf( row ) {
		const title = row.querySelector( '.row-title, .column-primary strong' );

		return title ? title.textContent.trim() : '';
	}

	// The lowest number on the page, which is where the numbering starts
	// again after a move: a second page begins at 101, not 1.
	const first = rows().reduce( ( lowest, row ) => {
		const shown = parseInt(
			row.querySelector( '.sugarcommerce-reorder__number' ).textContent,
			10
		);

		return Number.isNaN( shown ) ? lowest : Math.min( lowest, shown );
	}, Infinity );

	/**
	 * Write each row's new place into it.
	 */
	function renumber() {
		rows().forEach( ( row, index ) => {
			row.querySelector( '.sugarcommerce-reorder__number' ).textContent =
				String( ( Number.isFinite( first ) ? first : 1 ) + index );
		} );
	}

	/**
	 * Say that the order could not be kept, where it will be seen.
	 */
	function failed() {
		speak( config.i18n.failed );

		if ( document.querySelector( '.sugarcommerce-reorder-failed' ) ) {
			return;
		}

		const notice = document.createElement( 'div' );
		const text = document.createElement( 'p' );

		notice.className = 'notice notice-error sugarcommerce-reorder-failed';
		text.textContent = config.i18n.failed;
		notice.appendChild( text );

		const heading = document.querySelector( '.wrap .wp-header-end' );

		if ( heading ) {
			heading.insertAdjacentElement( 'afterend', notice );
		} else {
			list.closest( 'table' ).insertAdjacentElement(
				'beforebegin',
				notice
			);
		}
	}

	// Only the newest save matters. Three quick presses of an arrow key are
	// three posts, and whichever answers last must not be the first.
	let pending = 0;

	/**
	 * Keep the order the rows are now in.
	 *
	 * @param {Element} moved The row that was moved, to say where it went.
	 */
	function save( moved ) {
		const all = rows();
		const body = new URLSearchParams();
		const ticket = ++pending;

		renumber();

		body.append( 'action', config.action || '' );
		body.append( 'nonce', config.nonce || '' );

		all.forEach( ( row ) => {
			body.append(
				'ids[]',
				row.querySelector( '.sugarcommerce-reorder' ).dataset.id
			);
		} );

		speak(
			fill(
				config.i18n.moved,
				nameOf( moved ),
				String( all.indexOf( moved ) + 1 ),
				String( all.length )
			)
		);

		window
			.fetch( config.url, {
				method: 'POST',
				credentials: 'same-origin',
				body,
			} )
			.then( ( response ) => response.json() )
			.then( ( answer ) => {
				if ( ticket === pending && ! ( answer && answer.success ) ) {
					failed();
				}
			} )
			.catch( () => {
				if ( ticket === pending ) {
					failed();
				}
			} );
	}

	/**
	 * Move a row one place up or down.
	 *
	 * @param {Element} row The row.
	 * @param {number}  by  -1 for up, 1 for down.
	 * @return {boolean} Whether it moved.
	 */
	function step( row, by ) {
		const all = rows();
		const other = all[ all.indexOf( row ) + by ];

		if ( ! other ) {
			return false;
		}

		if ( by < 0 ) {
			list.insertBefore( row, other );
		} else {
			list.insertBefore( other, row );
		}

		save( row );

		return true;
	}

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

	if ( rows().length > 1 && $ && $.fn.sortable ) {
		$( list ).sortable( {
			items: '> tr',
			handle: '.sugarcommerce-reorder__handle',

			// Sortable will not start a drag on a button unless told it
			// may, and the handle is one so that it can take focus and the
			// arrow keys. Named in full rather than emptied: a drag must
			// still never begin on a tick box or a link.
			cancel: 'input, textarea, select, option, a, .sugarcommerce-reorder__step',
			axis: 'y',
			containment: 'parent',
			tolerance: 'pointer',
			placeholder: 'sugarcommerce-reorder-placeholder',
			forcePlaceholderSize: true,

			// A table row lifted out of its table forgets its column
			// widths, and the row being dragged collapses to the width of
			// its text. Each cell is told the width it had.
			helper( event, row ) {
				const widths = row
					.children()
					.map( function () {
						return $( this ).outerWidth();
					} )
					.get();

				row.children().each( function ( index ) {
					$( this ).css( 'width', widths[ index ] );
				} );

				return row;
			},
			start( event, ui ) {
				ui.placeholder.html(
					'<td colspan="' + ui.item.children().length + '"></td>'
				);
			},
			stop( event, ui ) {
				ui.item.children().css( 'width', '' );
			},
			update( event, ui ) {
				save( ui.item.get( 0 ) );
			},
		} );
	}
} )();
