/**
 * Turning the controls inside an element into a request body, the way
 * jQuery's serialize() did.
 *
 * Quick Edit's controls sit in a table row, not a form of their own -- the
 * list's form surrounds the whole table -- so FormData cannot be pointed
 * at them. The rules are the browser's for a form submission: a control
 * needs a name and must not be disabled; buttons, files and images are not
 * sent; a box or a radio only when it is ticked; each selected option of a
 * select; line breaks as CRLF.
 */

const SENT = /^(?:input|select|textarea)$/i;
const NOT_SENT = /^(?:submit|button|image|reset|file)$/i;

/**
 * Each name and value the controls inside an element would post.
 *
 * @param {Element} root The element.
 * @return {Array<[string, string]>} Pairs, in document order.
 */
export function fields( root ) {
	const pairs = [];

	root.querySelectorAll( 'input, select, textarea' ).forEach( ( control ) => {
		if (
			! control.name ||
			control.matches( ':disabled' ) ||
			! SENT.test( control.nodeName ) ||
			NOT_SENT.test( control.type )
		) {
			return;
		}

		if (
			/^(?:checkbox|radio)$/i.test( control.type ) &&
			! control.checked
		) {
			return;
		}

		const values =
			'SELECT' === control.nodeName
				? Array.from( control.options )
						.filter( ( option ) => option.selected )
						.map( ( option ) => option.value )
				: [ control.value ];

		values.forEach( ( value ) =>
			pairs.push( [
				control.name,
				String( value ).replace( /\r?\n/g, '\r\n' ),
			] )
		);
	} );

	return pairs;
}

/**
 * The controls inside an element as a urlencoded body.
 *
 * Encoded the way jQuery encoded it -- encodeURIComponent, a space as %20
 * -- so the server reads exactly what it read before.
 *
 * @param {Element} root The element.
 * @return {string} name=value&name=value.
 */
export function serialize( root ) {
	return fields( root )
		.map(
			( [ name, value ] ) =>
				encodeURIComponent( name ) + '=' + encodeURIComponent( value )
		)
		.join( '&' );
}
