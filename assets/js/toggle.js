/**
 * Toggle cells: a switch in a list, saved over REST.
 *
 * Pressing one turns it over at once and saves; the answer redraws it, and
 * if the save is refused it turns back and says so beside itself. The new
 * state is read out. Only the toggles inside this build's own screen are
 * answered (lib/config.js).
 */

import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

import { claim, own } from './lib/config';
import { bindToggles } from './toggle/press';

const entry = own();

domReady( () => {
	const mine = claim( document, entry );

	if ( mine ) {
		bindToggles( mine.root, {
			settings: mine.config,
			storage: () => window.localStorage,
			apiFetch,
			speak,
		} );
	}
} );
