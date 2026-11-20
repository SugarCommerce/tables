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
 * this saves the same choice itself, through the table's REST route, into
 * the user option core reads -- and loads the list again when its own
 * request answers. Both requests save the same list, so it does not matter
 * which lands last.
 *
 * Nothing happens for a column that was drawn in full, which is most of
 * them.
 */

/**
 * The columns core counts as hidden now: its own `columns.hidden()`.
 *
 * @param {Document} doc The document.
 * @return {string[]} Their ids.
 */
export function hiddenColumns( doc ) {
	return Array.from( doc.querySelectorAll( '.manage-column[id]' ) )
		.filter( ( heading ) => heading.classList.contains( 'hidden' ) )
		.map( ( heading ) => heading.id );
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
 * @param {Document} doc              The document.
 * @param {Object}   options          Options.
 * @param {string}   options.path     The table's hidden-columns route.
 * @param {string}   options.screen   Core's pagenow.
 * @param {Function} options.apiFetch api-fetch, or a fake.
 * @param {Function} options.reload   Loads the page again.
 * @return {Function} Stops listening.
 */
export function watchDeferredColumns(
	doc,
	{ path, screen, apiFetch, reload }
) {
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

		apiFetch( {
			path,
			method: 'POST',
			data: { screen, hidden: hiddenColumns( doc ) },
		} )
			.catch( () => {} )
			.then( () => reload() );
	};

	doc.addEventListener( 'click', listener );

	return () => doc.removeEventListener( 'click', listener );
}
