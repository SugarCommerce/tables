/**
 * Quick Edit and Bulk Edit on a list. See inline-edit/editor.js.
 *
 * The editor is reachable from a plugin's own script -- to open or close it,
 * as core's posts screen offers `inlineEditPost` -- in this build's entry of
 * the registry: `window.SugarCommerceTables[ handle ].editor`, the handle
 * being this script's (`{prefix}-sugarcommerce-tables-inline-edit`). Never a
 * fixed global: two plugins' copies would each set it, and the last would
 * answer for both.
 */

import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

import { claim, own } from './lib/config';
import { bindEditor } from './inline-edit/bind';
import { createEditor } from './inline-edit/editor';
import { post } from './inline-edit/post';

const entry = own();

// Made at once, as the old script made it, so a plugin's script loaded after
// this one finds the editor without waiting for the page; the listening
// waits for the page, and happens inside this build's screen only.
if ( entry && ! entry.editor ) {
	entry.editor = createEditor( document, {
		settings: entry,
		post: ( path, body ) => post( path, body, apiFetch ),
		speak,
	} );

	domReady( () => {
		const mine = claim( document, entry );

		if ( mine ) {
			bindEditor( mine.root, entry.editor );
		}
	} );
}
