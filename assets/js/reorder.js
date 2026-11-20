/**
 * Putting a list's rows in order.
 *
 * A list table sorts by a column and knows nothing of an order somebody
 * chose, so moving a row is done here. There are three ways to do it and
 * one function behind all of them: drag the handle, press the arrow keys
 * while it has focus, or -- on a screen too narrow to drag a row along --
 * tap the up and down buttons. Each ends by sending the ids as they now sit to the table's REST route.
 */

import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

import { reorderConfig } from './lib/config';
import { initReorder } from './reorder/init';

domReady( () => {
	const list = document.getElementById( 'the-list' );

	if ( list ) {
		initReorder( list, {
			config: reorderConfig(),
			apiFetch,
			speak,
		} );
	}
} );
