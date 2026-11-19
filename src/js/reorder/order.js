/**
 * The order the rows are in, and keeping it.
 *
 * One function behind every way of moving a row -- the drag, the arrow
 * keys, the up and down buttons -- each ending by posting the ids as they
 * now sit. The network and the screen reader are handed in, so the whole
 * thing runs under a test.
 */

import { fill } from '../lib/text';

/**
 * The order of one list.
 *
 * @param {Element}  list           The list's tbody (#the-list).
 * @param {Object}   options        Options.
 * @param {Object}   options.config sugarCommerceReorder.
 * @param {Function} options.fetch  window.fetch, or a fake.
 * @param {Function} options.speak  wp.a11y.speak, or a fake.
 * @return {Object} rows(), save(), step() and renumber().
 */
export function createOrder( list, { config, fetch, speak } ) {
	const i18n = config.i18n || {};

	/**
	 * The rows that can be moved.
	 *
	 * @return {Element[]} Rows, top to bottom.
	 */
	const rows = () =>
		Array.from( list.children ).filter( ( row ) =>
			row.querySelector( '.sugarcommerce-reorder' )
		);

	/**
	 * What a row is called.
	 *
	 * @param {Element} row The row.
	 * @return {string} Its name.
	 */
	const nameOf = ( row ) => {
		const title = row.querySelector( '.row-title, .column-primary strong' );

		return title ? title.textContent.trim() : '';
	};

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
	const renumber = () => {
		rows().forEach( ( row, index ) => {
			row.querySelector( '.sugarcommerce-reorder__number' ).textContent =
				String( ( Number.isFinite( first ) ? first : 1 ) + index );
		} );
	};

	/**
	 * Say that the order could not be kept, where it will be seen.
	 */
	const failed = () => {
		speak( i18n.failed, 'assertive' );

		if ( document.querySelector( '.sugarcommerce-reorder-failed' ) ) {
			return;
		}

		const notice = document.createElement( 'div' );
		const text = document.createElement( 'p' );

		notice.className = 'notice notice-error sugarcommerce-reorder-failed';
		text.textContent = i18n.failed;
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
	};

	// Only the newest save matters. Three quick presses of an arrow key are
	// three posts, and whichever answers last must not be the first.
	let pending = 0;

	/**
	 * Keep the order the rows are now in.
	 *
	 * @param {Element} moved The row that was moved, to say where it went.
	 * @return {Promise<void>} Settles when the server has answered.
	 */
	const save = ( moved ) => {
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
				i18n.moved,
				nameOf( moved ),
				String( all.indexOf( moved ) + 1 ),
				String( all.length )
			),
			'assertive'
		);

		return fetch( config.url, {
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
	};

	/**
	 * Move a row one place up or down.
	 *
	 * @param {Element} row The row.
	 * @param {number}  by  -1 for up, 1 for down.
	 * @return {boolean} Whether it moved.
	 */
	const step = ( row, by ) => {
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
	};

	return { rows, renumber, save, step };
}
