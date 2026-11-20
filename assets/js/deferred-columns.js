/**
 * Loading a list again when a column it did not work out is switched on
 * in Screen Options. See columns/deferred.js.
 */

import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

import { deferredColumnsConfig, screenName } from './lib/config';
import { watchDeferredColumns } from './columns/deferred';

domReady( () => {
	watchDeferredColumns( document, {
		path: deferredColumnsConfig().path || '',
		screen: screenName(),
		apiFetch,
		reload: () => window.location.reload(),
	} );
} );
