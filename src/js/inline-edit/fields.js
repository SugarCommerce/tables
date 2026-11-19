/**
 * Filling the quick editor from the row it is opened over.
 *
 * Every field takes its current value from the hidden `#inline_{id}` block
 * the checkbox column printed: a div per field, classed by the field's
 * name, holding its value as text.
 */

/**
 * A value out of the hidden data block, or null when it is absent.
 *
 * Looked up by class name, not by a selector built from the name, so a
 * name a selector cannot hold is simply not found.
 *
 * @param {Element|null} data The block.
 * @param {string}       name The field.
 * @return {?string} The value.
 */
export function readField( data, name ) {
	const found = data ? Array.from( data.getElementsByClassName( name ) ) : [];

	return found.length
		? found.map( ( field ) => field.textContent ).join( '' )
		: null;
}

/**
 * Whether a select carries an option with this value.
 *
 * @param {HTMLSelectElement} select The select.
 * @param {string}            value  The value.
 * @return {boolean} Whether it is on offer.
 */
export function offers( select, value ) {
	return Array.from( select.querySelectorAll( 'option' ) ).some(
		( option ) => option.value === value
	);
}

/**
 * What to call a value the field does not offer.
 *
 * Only the stored value is known here, so it is the value made readable --
 * `partially_refunded` as "Partially refunded" -- and a dash for nothing at
 * all.
 *
 * @param {string} value The stored value.
 * @return {string} A label for it.
 */
export function describe( value ) {
	const text = String( value ).replace( /[_-]+/g, ' ' ).trim();

	return text ? text.charAt( 0 ).toUpperCase() + text.slice( 1 ) : '—';
}

/**
 * Put the row's values into the editor's fields.
 *
 * A field with no block entry is left at its default rather than being
 * blanked, so a control added by the markup hook is not cleared by opening
 * the editor.
 *
 * @param {Element}      editor The open editor.
 * @param {Element|null} data   The row's data block.
 */
export function fillFields( editor, data ) {
	editor.querySelectorAll( '[name]' ).forEach( ( field ) => {
		const name = field.getAttribute( 'name' );

		if (
			'item_id' === name ||
			'table_id' === name ||
			'row_title' === name
		) {
			return;
		}

		// A checklist's boxes and its sentinel, all named `name[]`. Left to
		// fillChecklists(): read as a single value here, a box would be
		// ticked or not by whether the list read "1".
		if ( /\[\]$/.test( name ) ) {
			return;
		}

		const value = readField( data, name );

		if ( null === value ) {
			return;
		}

		if ( 'checkbox' === field.type ) {
			field.checked = '1' === value || 'yes' === value;
			return;
		}

		// A select only takes a value it actually offers. Assigning one it
		// does not have leaves selectedIndex at -1, which renders blank and
		// drops the field out of what is posted.
		//
		// Leaving it on its first option was worse. An order that is
		// Refunded, edited through a list offering Pending, Complete,
		// Failed and Cancelled, opened reading "Pending" -- and pressing
		// Update, having changed nothing, made it so.
		//
		// So the row's own value is put in the list for as long as this
		// editor is open: the control shows what the row is, and submitting
		// it untouched changes nothing, because the server only accepts what
		// the field declared. The clone is thrown away on close, so the next
		// row starts clean.
		if ( 'SELECT' === field.tagName && ! offers( field, value ) ) {
			const option = document.createElement( 'option' );

			option.value = value;
			option.textContent = describe( value );
			field.prepend( option );
		}

		field.value = value;
	} );
}
