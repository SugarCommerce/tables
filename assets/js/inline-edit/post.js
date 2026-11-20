/**
 * Saving the quick editor through the table's REST route.
 *
 * The form goes as it is, urlencoded -- a checklist's ticked boxes as an
 * array, as a browser posts them -- so the server reads the fields the way
 * a form sends them. api-fetch sends the REST nonce and renews it. The
 * answer is the row's markup and what to say about the save.
 */

/**
 * Post the editor's fields.
 *
 * @param {string}   path     The route, under the REST root.
 * @param {string}   body     The urlencoded fields.
 * @param {Function} apiFetch api-fetch, or a fake.
 * @return {Promise<{ok: boolean, row: string, notice: ?Object}>} The answer.
 */
export function post( path, body, apiFetch ) {
	return apiFetch( {
		path,
		method: 'POST',
		body: new URLSearchParams( body ),
	} ).then(
		( answer ) => ( {
			ok: true,
			row: String( ( answer && answer.row ) || '' ),
			notice: ( answer && answer.notice ) || null,
		} ),
		() => ( { ok: false, row: '', notice: null } )
	);
}
