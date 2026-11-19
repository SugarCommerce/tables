/**
 * Drawing a toggle as on or off, and the words beside it.
 *
 * The button is changed in place, never redrawn, which is what keeps the
 * focus on it.
 */

import { fill } from '../lib/text';

/**
 * A stored or ISO date as a short day: "3 Mar 2026".
 *
 * @param {string} value The date.
 * @return {string} The day, or empty.
 */
export function day( value ) {
	const date = value ? new Date( value ) : null;

	if ( ! date || isNaN( date.getTime() ) ) {
		return '';
	}

	const month = date.toLocaleDateString(
		document.documentElement.lang || undefined,
		{ month: 'short' }
	);

	return date.getDate() + ' ' + month + ' ' + date.getFullYear();
}

/**
 * What pressing it does, as its tooltip says.
 *
 * @param {Object}  config The toggle.
 * @param {boolean} on     Whether it is on.
 * @return {string} The tooltip.
 */
export function action( config, on ) {
	const words = on ? config.turnOff : config.turnOn;
	const confirm = on ? config.confirmOff : config.confirmOn;

	return confirm ? words + ' ' + confirm : words;
}

/**
 * Draw a toggle as on or off.
 *
 * @param {Element} button The toggle.
 * @param {Object}  config Its column's settings.
 * @param {boolean} on     Whether it is on.
 * @param {string}  since  When it was turned on, already worded; or empty.
 */
export function draw( button, config, on, since ) {
	const box = button.closest( '.sugarcommerce-toggle-cell' );
	const name = button.getAttribute( 'data-name' ) || '';
	const icons = config.icons || {};
	let title = action( config, on );

	if ( on && since ) {
		title = since + ' · ' + title;
	}

	button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
	button.setAttribute( 'title', title );

	if ( ! config.name ) {
		const row = on ? config.turnOffRow : config.turnOnRow;
		const words = on ? config.turnOff : config.turnOn;

		button.setAttribute( 'aria-label', row ? fill( row, name ) : words );
	}

	const word = button.querySelector( '.sugarcommerce-toggle__word' );

	if ( word ) {
		word.textContent = on ? config.on : config.off;
	}

	if ( box ) {
		box.classList.toggle( 'is-on', on );
	}

	// The glyph for the state, or none: a check is for "on" only. The
	// server drew both -- an SVG or a Dashicons span, already escaped --
	// so this only swaps one for the other.
	const markup = icons[ on ? 'on' : 'off' ] || '';
	const icon = button.querySelector( '.sugarcommerce-icon' );

	if ( icon ) {
		icon.remove();
	}

	if ( markup ) {
		const template = document.createElement( 'template' );

		template.innerHTML = markup;

		if ( template.content.firstElementChild ) {
			button.insertBefore(
				template.content.firstElementChild,
				button.firstChild
			);
		}
	}
}

/**
 * Show, or clear, the words beside the button.
 *
 * @param {Element} button The toggle.
 * @param {string}  words  What to say; empty to clear.
 * @param {boolean} wrong  Whether something went wrong.
 */
export function message( button, words, wrong ) {
	const box = button.closest( '.sugarcommerce-toggle-cell' );
	const slot = box
		? box.querySelector( '.sugarcommerce-toggle__message' )
		: null;

	if ( ! slot ) {
		return;
	}

	slot.textContent = words;
	slot.hidden = ! words;
	slot.classList.toggle( 'is-error', !! wrong );

	if ( wrong && words ) {
		slot.id =
			slot.id ||
			'sugarcommerce-toggle-message-' +
				button.getAttribute( 'data-toggle' ).replace( /\W/g, '-' ) +
				'-' +
				button.getAttribute( 'data-id' );
		button.setAttribute( 'aria-describedby', slot.id );
	} else {
		button.removeAttribute( 'aria-describedby' );
	}
}
