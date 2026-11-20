/**
 * Loading a list again when a column it did not work out is switched on
 * in Screen Options. See columns/deferred.js.
 */

import domReady from '@wordpress/dom-ready';

import { ajaxUrl, screenName } from './lib/config';
import { watchDeferredColumns } from './columns/deferred';

domReady( () => {
	watchDeferredColumns( document, {
		url: ajaxUrl(),
		screen: screenName(),
		fetch: ( ...args ) => window.fetch( ...args ),
		reload: () => window.location.reload(),
	} );
} );
