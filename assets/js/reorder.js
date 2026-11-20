/**
 * Putting a list's rows in order.
 *
 * A list table sorts by a column and knows nothing of an order somebody
 * chose, so moving a row is done here. There are three ways to do it and
 * one function behind all of them: drag the handle, press the arrow keys
 * while it has focus, or -- on a screen too narrow to drag a row along --
 * tap the up and down buttons. Each ends by sending the ids as they now sit to the table's REST route.
 *
 * The list is the one inside this build's own screen (lib/config.js).
 */

import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

import { claim, own } from './lib/config';
import { initReorder } from './reorder/init';

const entry = own();

domReady( () => {
	const mine = claim( document, entry );
	const list = mine ? mine.root.querySelector( '#the-list' ) : null;

	if ( list ) {
		initReorder( list, {
			config: mine.config,
			apiFetch,
			speak,
		} );
	}
} );
