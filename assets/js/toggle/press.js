/**
 * A press on a toggle.
 *
 * A toggle may ask first. Turning consent on records something the person
 * agreed to elsewhere: the first time somebody does it, the line says so
 * and waits for a second press; after that it only says so in the tooltip.
 *
 * Delegated from the screen rather than bound to each button: Quick Edit
 * replaces a row after it saves, and a handler bound to the old row's
 * button would be gone with it. The screen is the build's own `.wrap`, so
 * another plugin's copy of the library never presses this one's toggles.
 */

import { message } from './draw';
import { save } from './save';

const NOTED = 'sugarcommerce-toggle-noted:';

/**
 * Whether somebody has been told once what this press means.
 *
 * @param {Storage|null} storage localStorage, or nothing.
 * @param {string}       key     The toggle and the direction.
 * @return {boolean} Told.
 */
export function noted( storage, key ) {
	try {
		return storage.getItem( NOTED + key ) === '1';
	} catch {
		return false;
	}
}

/**
 * Remember that they have been.
 *
 * @param {Storage|null} storage localStorage, or nothing.
 * @param {string}       key     The toggle and the direction.
 */
export function note( storage, key ) {
	try {
		storage.setItem( NOTED + key, '1' );
	} catch {
		// Told again on the next page; no harm.
	}
}

/**
 * Listen for presses on every toggle in a screen.
 *
 * What somebody has been told once is remembered under the build's name
 * too: another plugin's toggle of the same name means something else.
 *
 * @param {Document|Element} root             The build's screen, or a whole document.
 * @param {Object}           options          Options.
 * @param {Object}           options.settings This build's entry of window.SugarCommerceTables.
 * @param {Function}         options.storage  Returns localStorage (it can throw).
 * @param {Function}         options.apiFetch api-fetch, or a fake.
 * @param {Function}         options.speak    wp.a11y.speak, or a fake.
 */
export function bindToggles( root, { settings, storage, apiFetch, speak } ) {
	const columns = settings.columns || {};
	const view = ( root.ownerDocument || root ).defaultView;
	const build = settings.build ? settings.build + ':' : '';
	const say = ( words ) => words && speak( words );

	const store = () => {
		try {
			return storage();
		} catch {
			return null;
		}
	};

	root.addEventListener( 'click', ( event ) => {
		const button =
			event.target instanceof view.Element
				? event.target.closest( 'button.sugarcommerce-toggle' )
				: null;

		if ( ! button ) {
			return;
		}

		const key = button.getAttribute( 'data-toggle' ) || '';
		const config = columns[ key ];

		if ( ! config ) {
			return;
		}

		event.preventDefault();

		// One save at a time for a toggle. A second press while the first
		// is on its way would race it, and whichever answered last would
		// win.
		if ( button.getAttribute( 'aria-busy' ) === 'true' ) {
			return;
		}

		const turningOn = button.getAttribute( 'aria-pressed' ) !== 'true';
		const confirm = turningOn ? config.confirmOn : config.confirmOff;
		const direction = build + key + ( turningOn ? ':on' : ':off' );

		// Once, ever, before the first press that means it: say what it
		// means, and wait for the press that does.
		if (
			confirm &&
			! noted( store(), direction ) &&
			button.getAttribute( 'data-armed' ) !== '1'
		) {
			const words = [ confirm, config.pressAgain ]
				.filter( Boolean )
				.join( ' ' );

			button.setAttribute( 'data-armed', '1' );
			message( button, words, false );
			say( words );

			return;
		}

		if ( confirm ) {
			note( store(), direction );
		}

		button.removeAttribute( 'data-armed' );
		save( button, config, {
			apiFetch,
			speak: say,
		} );
	} );
}
