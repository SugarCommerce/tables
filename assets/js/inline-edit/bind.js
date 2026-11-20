/**
 * What opens, saves and closes the two editors.
 *
 * The quick editor is a clone of `#inline-edit`, made each time it opens,
 * so everything inside it is listened to from the whole screen -- the
 * build's `.wrap`, which the clone is put inside: a clone has none of the
 * template's listeners. (jQuery's clone( true ) copied them; delegating from
 * the template's container instead had left Update and Cancel dead in the
 * open editor once already.) The bulk row is moved rather than cloned, so it
 * keeps its own.
 */

import { all, on } from '../lib/dom';

// The open quick editor: a clone of #inline-edit, never the bulk row.
const QUICK = 'tr.inline-editor:not(#bulk-edit)';

/**
 * Whether a key event is Escape.
 *
 * @param {KeyboardEvent} event The event.
 * @return {boolean} Escape.
 */
const isEscape = ( event ) => 'Escape' === event.key || 27 === event.keyCode;

/**
 * Listen for everything the editors answer to, inside one screen.
 *
 * Every listener is on the screen or inside it -- the build's own `.wrap`,
 * which holds the list, its bulk controls and both editors -- so another
 * plugin's copy of the library, on the same page, never answers this one's
 * buttons.
 *
 * @param {Document|Element} root   The screen (its `.wrap`), or a whole document.
 * @param {Object}           editor createEditor()'s editor.
 * @return {Function} Stops listening.
 */
export function bindEditor( root, editor ) {
	const bulk = root.querySelector( '#bulk-edit' );
	const quick = root.querySelector( '#inline-edit' );
	const stops = [];
	const listen = ( target, type, handler ) => {
		target.addEventListener( type, handler );
		stops.push( () => target.removeEventListener( type, handler ) );
	};
	const stop = () => stops.forEach( ( off ) => off() );

	if ( ! bulk && ! quick ) {
		return stop;
	}

	// Both, because core has a set of bulk controls above the table and
	// another below it, and either can be the one used.
	[ 'doaction', 'doaction2' ].forEach( ( id ) => {
		const button = root.querySelector( '#' + id );

		if ( ! button ) {
			return;
		}

		listen( button, 'click', ( event ) => {
			const select = root.querySelector(
				`select[name="${ id.substr( 2 ) }"]`
			);

			if ( ! select || 'edit' !== select.value ) {
				return;
			}

			event.preventDefault();
			editor.setBulk();
		} );
	} );

	if ( bulk ) {
		all( '.cancel', bulk ).forEach( ( button ) =>
			listen( button, 'click', () => editor.revert() )
		);

		// A half-ticked box posts as unticked, which is what the server
		// expects of one -- its flag beside it is what keeps the rows' own
		// answers. Cleared on the way out, as core does, so nothing is left
		// drawn half-ticked behind the reloading page.
		all( 'input[name="bulk_edit"]', bulk ).forEach( ( button ) =>
			listen( button, 'click', () =>
				all(
					'ul[data-checklist] input[type="checkbox"]',
					bulk
				).forEach( ( box ) => {
					box.indeterminate = false;
				} )
			)
		);

		listen( bulk, 'keyup', ( event ) => {
			if ( isEscape( event ) ) {
				editor.revert();
			}
		} );

		// Removing one from the list unticks its row, so the form posts
		// what the list shows. Core does the same, and it is the only thing
		// keeping the two in step.
		stops.push(
			on( bulk, 'click', '.ntdelbutton', ( event, button ) => {
				const id = button.id.substr( 1 );

				all( 'table.widefat input', root )
					.filter( ( input ) => input.value === id )
					.forEach( ( input ) => {
						input.checked = false;
					} );

				button.parentElement.remove();

				if ( ! root.querySelector( '#bulk-titles-list li' ) ) {
					editor.revert();
				}
			} )
		);
	}

	if ( ! quick ) {
		return stop;
	}

	// The row is replaced after every save, and a listener on the old row
	// would stop firing; #the-list is never moved.
	const list = root.querySelector( '#the-list' );

	if ( list ) {
		stops.push(
			on( list, 'click', '.editinline', ( event, button ) => {
				event.preventDefault();

				button.setAttribute( 'aria-expanded', 'true' );
				editor.edit( button );
			} )
		);
	}

	stops.push(
		on( root, 'click', `${ QUICK } .save`, () => editor.save() ),
		on( root, 'click', `${ QUICK } .cancel`, () => editor.revert() ),
		on( root, 'keydown', `${ QUICK } td`, ( event ) => {
			if (
				'Enter' === event.key &&
				! event.target.classList.contains( 'cancel' )
			) {
				event.preventDefault();
				editor.save();
			}
		} ),
		on( root, 'keyup', QUICK, ( event ) => {
			if ( isEscape( event ) ) {
				editor.revert();
			}
		} )
	);

	return stop;
}
