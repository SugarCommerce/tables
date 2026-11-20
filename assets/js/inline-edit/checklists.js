/**
 * The editors' checklists: what a row is in, ticked.
 *
 * Core's way with a post's categories. The data block holds the ids as a
 * comma list; the quick editor ticks exactly those, and the bulk editor
 * ticks what every selected row has, half-ticks what only some have and
 * leaves the rest clear.
 */

import { readField } from './fields';

/**
 * The ids a data block lists for a checklist.
 *
 * @param {Element|null} data The block.
 * @param {string}       name The checklist.
 * @return {string[]} The ids.
 */
const idsIn = ( data, name ) => {
	const ids = readField( data, name );

	return ids ? ids.split( ',' ) : [];
};

/**
 * A checklist's boxes.
 *
 * @param {Element} list The ul.
 * @return {HTMLInputElement[]} Its boxes.
 */
const boxes = ( list ) =>
	Array.from( list.querySelectorAll( 'input[type="checkbox"]' ) );

/**
 * Tick what the row is in, in each checklist of the open quick editor.
 *
 * @param {Element}      editor The open editor.
 * @param {Element|null} data   The row's data block.
 */
export function fillChecklists( editor, data ) {
	editor.querySelectorAll( 'ul[data-checklist]' ).forEach( ( list ) => {
		const ids = idsIn( data, list.dataset.checklist );

		boxes( list ).forEach( ( box ) => {
			box.checked = ids.includes( box.value );
		} );
	} );
}

/**
 * The bulk editor's checklists, which remember how they were opened.
 *
 * A half-ticked box -- indeterminate, with a hidden
 * `indeterminate_{name}[]` saying so to the server, which then leaves each
 * row's own answer for it alone -- becomes a real answer for all of them
 * the moment it is touched, so the flag goes. Opening the editor again
 * forgets what the last selection left.
 *
 * @param {Document} doc      The document.
 * @param {Object}   settings This build's inline-edit entry of window.SugarCommerceTables.
 * @return {{set: Function, reset: Function}} The two.
 */
export function bulkChecklists( doc, settings ) {
	let listening = null;

	const lists = () =>
		Array.from( doc.querySelectorAll( '#bulk-edit ul[data-checklist]' ) );

	/**
	 * Take a box's half-ticked flag off it.
	 *
	 * @param {HTMLInputElement} box The box.
	 */
	const unflag = ( box ) => {
		box.removeAttribute( 'aria-label' );
		box.parentElement
			.querySelectorAll( 'input[type="hidden"]' )
			.forEach( ( flag ) => flag.remove() );
	};

	/**
	 * Put the checklists back to nothing ticked.
	 *
	 * The bulk row is moved, not cloned, so what the last selection left
	 * in it is still there when it opens again.
	 */
	const reset = () => {
		if ( listening ) {
			listening.abort();
			listening = null;
		}

		lists().forEach( ( list ) =>
			boxes( list ).forEach( ( box ) => {
				box.checked = false;
				box.indeterminate = false;
				unflag( box );
			} )
		);
	};

	/**
	 * Open the checklists on what the ticked rows share.
	 *
	 * @param {HTMLInputElement[]} rows The ticked rows' checkboxes.
	 */
	const set = ( rows ) => {
		listening = new window.AbortController();

		lists().forEach( ( list ) => {
			const name = list.dataset.checklist;
			const counts = {};

			rows.forEach( ( row ) => {
				idsIn(
					doc.getElementById( 'inline_' + row.value ),
					name
				).forEach( ( id ) => {
					counts[ id ] = ( counts[ id ] || 0 ) + 1;
				} );
			} );

			boxes( list ).forEach( ( box ) => {
				const count = counts[ box.value ] || 0;

				box.checked = count > 0 && count === rows.length;

				if ( count < 1 || count === rows.length ) {
					return;
				}

				const label = box.parentElement.textContent.trim();
				const flag = doc.createElement( 'input' );

				flag.type = 'hidden';
				flag.name = 'indeterminate_' + name + '[]';
				flag.value = box.value;

				box.indeterminate = true;
				box.setAttribute(
					'aria-label',
					label +
						': ' +
						( settings.someHaveThis ||
							'Some selected items have this' )
				);
				box.after( flag );
				box.addEventListener( 'change', () => unflag( box ), {
					once: true,
					signal: listening.signal,
				} );
			} );
		} );
	};

	return { set, reset };
}
