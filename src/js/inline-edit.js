/**
 * Quick Edit and Bulk Edit on a list. See inline-edit/editor.js.
 *
 * The editor stays reachable as `window.sugarCommerceInlineEditor`, as it
 * always has been -- core's posts screen does the same with
 * `inlineEditPost` -- for a plugin that opens or closes it from its own
 * script.
 */

import { speak } from '@wordpress/a11y';
import domReady from '@wordpress/dom-ready';

import { ajaxUrl, inlineEditConfig } from './lib/config';
import { bindEditor } from './inline-edit/bind';
import { createEditor } from './inline-edit/editor';
import { post } from './inline-edit/post';

// Made at once, as the old script made it, so a plugin's script loaded after
// this one finds the global without waiting for the page; the listening
// waits for the page.
const editor = createEditor( document, {
	settings: inlineEditConfig(),
	ajaxUrl: ajaxUrl(),
	post: ( url, body ) => post( url, body, window.fetch.bind( window ) ),
	speak,
} );

window.sugarCommerceInlineEditor = editor;

domReady( () => bindEditor( document, editor ) );
