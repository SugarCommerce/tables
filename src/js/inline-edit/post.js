/**
 * Posting the quick editor to admin-ajax.
 *
 * As jQuery's $.post sent it -- a urlencoded body and the
 * X-Requested-With header -- so the save action reads the same request it
 * always has. The answer is the row's markup, so it is read as text, with
 * the response's headers kept for the notice that travels beside it.
 */

/**
 * Post a body.
 *
 * @param {string}   url   admin-ajax.php.
 * @param {string}   body  The urlencoded body.
 * @param {Function} fetch window.fetch, or a fake.
 * @return {Promise<{ok: boolean, text: string, header: Function}>} The answer.
 */
export function post( url, body, fetch ) {
	return fetch( url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With': 'XMLHttpRequest',
		},
		body,
	} ).then( ( response ) =>
		response.text().then( ( text ) => ( {
			ok: response.ok,
			text,
			header: ( name ) => response.headers.get( name ),
		} ) )
	);
}
