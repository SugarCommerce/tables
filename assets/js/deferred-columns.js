/**
 * Loading a list again when a column it did not work out is switched on
 * in Screen Options. See columns/deferred.js. Only the columns of this
 * build's own screen are looked at (lib/config.js).
 */

import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

import { claim, own, screenName } from './lib/config';
import { watchDeferredColumns } from './columns/deferred';

const entry = own();

domReady( () => {
	const mine = claim( document, entry );

	if ( mine ) {
		watchDeferredColumns( document, {
			root: mine.root,
			path: mine.config.path || '',
			screen: screenName(),
			apiFetch,
			reload: () => window.location.reload(),
		} );
	}
} );
