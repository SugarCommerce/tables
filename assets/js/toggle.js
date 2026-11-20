/**
 * Toggle cells: a switch in a list, saved over REST.
 *
 * Pressing one turns it over at once and saves; the answer redraws it, and
 * if the save is refused it turns back and says so beside itself. The new
 * state is read out.
 */

import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

import { toggleConfig } from './lib/config';
import { bindToggles } from './toggle/press';

domReady( () => {
	bindToggles( document, {
		settings: toggleConfig(),
		storage: () => window.localStorage,
		apiFetch,
		speak,
	} );
} );
