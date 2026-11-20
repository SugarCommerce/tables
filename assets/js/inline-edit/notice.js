/**
 * What a quick edit did, as the server worded it.
 *
 * It comes beside the row in the route's answer: "Order #115 is now
 * complete.", in the notice area above the list where a bulk action's
 * sentence appears, replacing the last one this put there.
 */

/**
 * The notice out of the answer, checked.
 *
 * @param {?Object} notice The answer's `notice`.
 * @return {?{message: string, type: string}} The notice, or null.
 */
export function readNotice( notice ) {
	if ( ! notice || ! notice.message ) {
		return null;
	}

	return {
		message: String( notice.message ),
		type: /^(success|warning|error|info)$/.test( notice.type )
			? notice.type
			: 'success',
	};
}

/**
 * Show a notice above the list.
 *
 * Dismissible the way core makes a notice added after the page loaded
 * dismissible: by saying one was added. Core listens for
 * `wp-updates-notice-added` on the document with jQuery, which hears a
 * native event of that name like any other.
 *
 * @param {Document}                        doc    The document.
 * @param {{message: string, type: string}} notice The notice.
 */
export function showNotice( doc, notice ) {
	const box = doc.createElement( 'div' );
	const text = doc.createElement( 'p' );

	box.className = `notice is-dismissible sugarcommerce-table-quick-notice notice-${ notice.type }`;

	// Text, never markup: a name is somebody's to choose.
	text.textContent = notice.message;
	box.appendChild( text );

	doc.querySelectorAll( '.sugarcommerce-table-quick-notice' ).forEach(
		( old ) => old.remove()
	);

	const end = doc.querySelector( '.wrap hr.wp-header-end' );

	if ( end ) {
		end.after( box );
	}

	doc.dispatchEvent( new window.Event( 'wp-updates-notice-added' ) );
}
