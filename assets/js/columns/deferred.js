/**
 * A column switched on in Screen Options that was not worked out.
 *
 * Some columns of a list ask another table for what they show, and do not
 * ask while they are hidden: their cells hold a mark and nothing else.
 * WordPress shows a column the moment its box is ticked, without asking
 * the server for anything, so one of those would appear as a column of
 * marks. The list has to be loaded again to draw it -- but only once the
 * choice is saved, or it loads with the column still hidden.
 *
 * WordPress saves the choice in a request of its own, made with jQuery,
 * and the only way to hear that one finish is jQuery's ajaxComplete. So
 * this saves the same choice itself, the same way core does -- the
 * `hidden-columns` action with the screen's nonce and the columns that are
 * now hidden -- and loads the list again when its own request answers.
 * Both requests save the same list, so it does not matter which lands
 * last.
 *
 * Nothing happens for a column that was drawn in full, which is most of
 * them.
 */

/**
 * The columns core counts as hidden now: its own `columns.hidden()`.
 *
 * @param {Document} doc The document.
 * @return {string} Their ids, comma-separated.
 */
export function hiddenColumns( doc ) {
	return Array.from( doc.querySelectorAll( '.manage-column[id]' ) )
		.filter( ( heading ) => heading.classList.contains( 'hidden' ) )
		.map( ( heading ) => heading.id )
		.join( ',' );
}

/**
 * Whether a column's cells are only marks waiting to be worked out.
 *
 * @param {Document} doc    The document.
 * @param {string}   column The column's name.
 * @return {boolean} Deferred.
 */
export function isDeferred( doc, column ) {
	return (
		doc.querySelectorAll(
			'.wp-list-table td.column-' +
				column +
				' .sugarcommerce-column-deferred'
		).length > 0
	);
}

/**
 * Listen for a deferred column being switched on.
 *
 * Listened for on the document, as the box's click bubbles up: core's own
 * handler, bound on the box itself, has shown the column and marked the
 * heading by then, so the hidden list read here is the one core saves.
 *
 * @param {Document} doc            The document.
 * @param {Object}   options        Options.
 * @param {string}   options.url    admin-ajax.php.
 * @param {string}   options.screen Core's pagenow.
 * @param {Function} options.fetch  window.fetch, or a fake.
 * @param {Function} options.reload Loads the page again.
 * @return {Function} Stops listening.
 */
export function watchDeferredColumns( doc, { url, screen, fetch, reload } ) {
	let waiting = false;

	const listener = ( event ) => {
		const box =
			event.target instanceof doc.defaultView.Element
				? event.target.closest( '#adv-settings .hide-column-tog' )
				: null;

		if ( ! box || ! box.checked || waiting ) {
			return;
		}

		if ( ! isDeferred( doc, box.value ) ) {
			return;
		}

		waiting = true;

		const nonce = doc.getElementById( 'screenoptionnonce' );
		const body = new URLSearchParams( {
			action: 'hidden-columns',
			hidden: hiddenColumns( doc ),
			screenoptionnonce: nonce ? nonce.value : '',
			page: screen,
		} );

		fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		} )
			.catch( () => {} )
			.then( () => reload() );
	};

	doc.addEventListener( 'click', listener );

	return () => doc.removeEventListener( 'click', listener );
}
