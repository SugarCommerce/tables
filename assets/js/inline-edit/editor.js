/**
 * Quick Edit and Bulk Edit, as core does them.
 *
 * Modelled on wp-admin/js/inline-edit-post.js, which is post-specific: it
 * reads `#inline_{id} .post_title`, submits `post[]`, and knows about
 * taxonomies and page parents. This is the same two editors against a table
 * whose rows are not posts.
 *
 * They do not work the same way, and the difference is core's, not an
 * inconsistency introduced here.
 *
 * Bulk Edit never saves from script. It reveals the row and lists what was
 * ticked; pressing Update submits the surrounding list form, selection
 * included, and the page reloads. That is why the bulk half has no request.
 *
 * Quick Edit does save from script. It reveals the row over a single row,
 * fills the inputs from the hidden `#inline_{id}` block, posts the fields,
 * and swaps in the row markup the server sends back. The response is the
 * rendered row rather than a success flag on purpose -- a status column, a
 * formatted amount and a row action set all change with the save, and a
 * script rebuilding them would be a second renderer drifting from the first.
 */

import { all, fadeIn, hide, isShown, one, show, siblings } from '../lib/dom';
import { serialize } from '../lib/form';
import { bulkChecklists, fillChecklists } from './checklists';
import { fillFields, readField } from './fields';
import { readNotice, showNotice } from './notice';

/**
 * How many columns the list shows: what an editor row must span.
 *
 * @param {Document} doc The document.
 * @return {number} Shown header cells of the first list.
 */
const shownColumns = ( doc ) => {
	const head = one( '.widefat thead', doc );

	return head
		? all( 'th, td', head ).filter( ( cell ) => isShown( cell ) ).length
		: 0;
};

/**
 * The two editors over one document.
 *
 * @param {Document} doc              The document.
 * @param {Object}   options          Options.
 * @param {Object}   options.settings This build's inline-edit entry of window.SugarCommerceTables.
 * @param {Function} options.post     Posts the fields: ( path, body ) =>
 *                                    Promise<{ ok, row, notice }>.
 * @param {Function} options.speak    wp.a11y.speak, or a fake.
 * @return {Object} The editor.
 */
export function createEditor( doc, { settings, post, speak } ) {
	const checklists = bulkChecklists( doc, settings );
	const spinners = () => all( 'table.widefat .spinner', doc );
	const failure = () => settings.error || 'The change could not be saved.';

	/**
	 * Say in the editor that the save did not happen.
	 *
	 * Saying so is the whole point -- the alternative is a row that silently
	 * snaps back to what it was.
	 *
	 * @param {Element} row The open editor.
	 */
	const showError = ( row ) => {
		const error = row ? one( '.error', row ) : null;

		if ( ! error ) {
			return;
		}

		error.textContent = failure();
		error.parentElement.classList.remove( 'hidden' );
		show( error.parentElement );
	};

	const editor = {
		/**
		 * The row being quick edited, or an empty string.
		 */
		what: '',

		/**
		 * The row id out of an element inside it.
		 *
		 * @param {Element} element Anything inside the row.
		 * @return {string} The id.
		 */
		getId( element ) {
			const row = element.closest( 'tr' );

			return ( ( row && row.id ) || '' ).replace( 'item-', '' );
		},

		/**
		 * Open the quick editor over a row.
		 *
		 * @param {string|Element} id The row, or its id.
		 * @return {boolean} False, as a handler's answer.
		 */
		edit( id ) {
			editor.revert();

			if ( 'object' === typeof id ) {
				id = editor.getId( id );
			}

			editor.what = id;

			const template = doc.getElementById( 'inline-edit' );
			const item = doc.getElementById( 'item-' + id );

			if ( ! template || ! item ) {
				return false;
			}

			const row = template.cloneNode( true );
			const span = String( shownColumns( doc ) );

			all( 'td', row ).forEach( ( cell ) =>
				cell.setAttribute( 'colspan', span )
			);

			// An empty row above it so the zebra striping survives, which is
			// what core does and why the row below is not suddenly the wrong
			// colour.
			//
			// The data row is hidden and nothing else. It used to be given
			// the editor's own class, inline-edit-row, which core styles
			// with `tr.inline-edit-row td { padding: 0 }` -- and Cancel
			// showed the row again with the class still on it, so every
			// cell came back with no padding at all. Core hides the row and
			// leaves its classes alone.
			const spacer = doc.createElement( 'tr' );

			spacer.className = 'hidden';
			hide( item );
			item.after( spacer, row );

			row.id = 'edit-' + id;
			row.classList.add( 'inline-editor' );
			show( row );

			const data = doc.getElementById( 'inline_' + id );
			const itemId = one( 'input[name="item_id"]', row );
			const title = one( '[data-row-title]', row );

			if ( itemId ) {
				itemId.value = id;
			}

			// Shown, not edited: the editor says which row it is for.
			if ( title ) {
				title.textContent = readField( data, 'row_title' ) ?? '';
			}

			fillFields( row, data );
			fillChecklists( row, data );

			const first = all( 'input, select, textarea, button', row ).find(
				( control ) => isShown( control )
			);

			if ( first ) {
				first.focus();
			}

			return false;
		},

		/**
		 * Post the open quick editor and swap in the row it returns.
		 *
		 * @return {Promise<void>|boolean} Settles when it is drawn; false
		 *                                 with nothing open.
		 */
		save() {
			const id = editor.what;

			if ( ! id ) {
				return false;
			}

			const row = doc.getElementById( 'edit-' + id );

			spinners().forEach( ( spinner ) =>
				spinner.classList.add( 'is-active' )
			);

			const body = row ? serialize( row ) : '';

			const done = ( answer ) => {
				spinners().forEach( ( spinner ) =>
					spinner.classList.remove( 'is-active' )
				);

				if ( ! answer.ok ) {
					showError( row );
					return;
				}

				// A save that returns nothing did not save.
				if ( ! answer.row || ! answer.row.trim() ) {
					showError( row );
					return;
				}

				// tr.hidden, not .hidden: the data block each row carries is
				// a div.hidden, and a bare class selector here removed every
				// one of them -- so the next Quick Edit opened with empty
				// fields, on rows that had never been touched.
				const old = doc.getElementById( 'item-' + id );

				if ( old ) {
					siblings( old, 'tr.hidden' ).forEach( ( spacer ) =>
						spacer.remove()
					);
					old.remove();
				}

				row.insertAdjacentHTML( 'beforebegin', answer.row );
				row.remove();

				const item = doc.getElementById( 'item-' + id );

				editor.what = '';

				if ( ! item ) {
					return;
				}

				hide( item );

				return fadeIn( item, 400 ).then( () => {
					const button = one( '.editinline', item );

					if ( button ) {
						button.setAttribute( 'aria-expanded', 'false' );
						button.focus();
					}

					const notice = readNotice( answer.notice );

					if ( notice ) {
						showNotice( doc, notice );
					}

					speak(
						( notice && notice.message ) ||
							settings.saved ||
							'Changes saved.'
					);
				} );
			};

			return post(
				( settings.path || '' ) + '/' + encodeURIComponent( id ),
				body
			).then( done, () => done( { ok: false } ) );
		},

		/**
		 * Move the bulk row into the table and list what was selected.
		 */
		setBulk() {
			editor.revert();

			const bulk = doc.getElementById( 'bulk-edit' );
			const body = one( 'table.widefat tbody', doc );

			if ( ! bulk || ! body ) {
				return;
			}

			const span = String( shownColumns( doc ) );
			const spacer = doc.createElement( 'tr' );

			all( 'td', bulk ).forEach( ( cell ) =>
				cell.setAttribute( 'colspan', span )
			);

			spacer.className = 'hidden';
			body.prepend( spacer, bulk );

			bulk.classList.add( 'inline-editor' );
			show( bulk );

			const ticked = all(
				'tbody .check-column input[type="checkbox"]',
				doc
			).filter( ( box ) => box.checked );

			// Nothing ticked is nothing to edit.
			if ( ! ticked.length ) {
				editor.revert();
				return;
			}

			const list = doc.createElement( 'ul' );

			list.id = 'bulk-titles-list';
			list.setAttribute( 'role', 'list' );

			ticked.forEach( ( box ) => {
				const item = doc.createElement( 'li' );
				const remove = doc.createElement( 'button' );
				const label = doc.createElement( 'span' );
				const title = doc.createElement( 'span' );

				item.className = 'ntdelitem';
				remove.type = 'button';
				remove.id = '_' + box.value;
				remove.className = 'button-link ntdelbutton';
				label.className = 'screen-reader-text';
				label.textContent =
					settings.removeFromBulk || 'Remove from Bulk Edit';
				title.className = 'ntdeltitle';
				title.setAttribute( 'aria-hidden', 'true' );
				title.textContent =
					readField(
						doc.getElementById( 'inline_' + box.value ),
						'row_title'
					) || '';

				remove.appendChild( label );
				item.append( remove, title );
				list.appendChild( item );
			} );

			doc.getElementById( 'bulk-titles' )?.replaceChildren( list );

			checklists.reset();
			checklists.set( ticked );

			one( 'select', bulk )?.focus();
		},

		/**
		 * Close whichever editor is open and put the table back.
		 *
		 * @return {boolean} False, as a handler's answer.
		 */
		revert() {
			const open = one( 'table.widefat tr.inline-editor', doc );

			if ( ! open || ! open.id ) {
				return false;
			}

			spinners().forEach( ( spinner ) =>
				spinner.classList.remove( 'is-active' )
			);

			if ( 'bulk-edit' === open.id ) {
				open.classList.remove( 'inline-editor' );
				hide( open );
				siblings( open, 'tr.hidden' ).forEach( ( spacer ) =>
					spacer.remove()
				);
				doc.getElementById( 'bulk-titles' )?.replaceChildren();
				checklists.reset();
				doc.getElementById( 'inlineedit' )?.append( open );

				return false;
			}

			siblings( open, 'tr.hidden' ).forEach( ( spacer ) =>
				spacer.remove()
			);
			open.remove();

			editor.what = '';

			const item = doc.getElementById(
				'item-' + open.id.replace( 'edit-', '' )
			);

			if ( item ) {
				show( item );

				const button = one( '.editinline', item );

				if ( button ) {
					button.setAttribute( 'aria-expanded', 'false' );
					button.focus();
				}
			}

			return false;
		},
	};

	return editor;
}
