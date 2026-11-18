/**
 * Toggle cells: a switch in a list, saved over REST.
 *
 * Pressing one turns it over at once and saves; the answer redraws it, and
 * if the save is refused it turns back and says so beside itself, so what
 * the list shows is never a state the record does not have. The button is
 * changed in place, never redrawn, which is what keeps the focus on it, and
 * the new state is read out.
 *
 * A toggle may ask first. Turning consent on records something the person
 * agreed to elsewhere: the first time somebody does it, the line says so
 * and waits for a second press; after that it only says so in the tooltip.
 *
 * Delegated from the document rather than bound to each button: Quick Edit
 * replaces a row after it saves, and a handler bound to the old row's
 * button would be gone with it.
 */
( function () {
	'use strict';

	const settings = window.sugarCommerceToggles || {};
	const columns = settings.columns || {};
	const NOTED = 'sugarcommerce-toggle-noted:';

	/**
	 * Fill the %s in a translated sentence.
	 *
	 * @param {string} text  The sentence.
	 * @param {string} value What goes in it.
	 * @return {string} The sentence.
	 */
	function fill( text, value ) {
		return String( text || '' ).replace( '%s', value );
	}

	/**
	 * Whether somebody has been told once what this press means.
	 *
	 * @param {string} key The toggle and the direction.
	 * @return {boolean} Told.
	 */
	function noted( key ) {
		try {
			return window.localStorage.getItem( NOTED + key ) === '1';
		} catch {
			return false;
		}
	}

	/**
	 * Remember that they have been.
	 *
	 * @param {string} key The toggle and the direction.
	 */
	function note( key ) {
		try {
			window.localStorage.setItem( NOTED + key, '1' );
		} catch {
			// Told again on the next page; no harm.
		}
	}

	/**
	 * The page's own live region, for a page without wp.a11y.
	 *
	 * @return {Element} The region.
	 */
	function live() {
		let region = document.getElementById( 'sugarcommerce-toggle-live' );

		if ( ! region ) {
			region = document.createElement( 'div' );
			region.id = 'sugarcommerce-toggle-live';
			region.className = 'screen-reader-text';
			region.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( region );
		}

		return region;
	}

	/**
	 * Say something to a screen reader.
	 *
	 * @param {string} words What to say.
	 */
	function say( words ) {
		if ( ! words ) {
			return;
		}

		if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak( words );
			return;
		}

		const region = live();

		// Emptied first, so the same words twice are still read twice.
		region.textContent = '';
		window.setTimeout( function () {
			region.textContent = words;
		}, 50 );
	}

	/**
	 * A stored or ISO date as a short day: "3 Mar 2026".
	 *
	 * @param {string} value The date.
	 * @return {string} The day, or empty.
	 */
	function day( value ) {
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
	function action( config, on ) {
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
	function draw( button, config, on, since ) {
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

			button.setAttribute(
				'aria-label',
				row ? fill( row, name ) : on ? config.turnOff : config.turnOn
			);
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
	function message( button, words, wrong ) {
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

	/**
	 * Save the other state, and draw what the server says.
	 *
	 * @param {Element} button The toggle.
	 * @param {Object}  config Its column's settings.
	 */
	function toggle( button, config ) {
		const request = config.request || {};
		const was = button.getAttribute( 'aria-pressed' ) === 'true';
		const wasTitle = button.getAttribute( 'title' );
		const on = ! was;
		const name = button.getAttribute( 'data-name' ) || '';
		const body = Object.assign( {}, request.data || {} );

		body[ request.key ] = on;

		button.setAttribute( 'aria-busy', 'true' );
		message( button, '', false );
		draw( button, config, on, '' );

		window
			.fetch(
				String( request.url ).replace(
					'{id}',
					encodeURIComponent( button.getAttribute( 'data-id' ) )
				),
				{
					method: request.method || 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': settings.nonce || '',
					},
					body: JSON.stringify( body ),
				}
			)
			.then( function ( response ) {
				return response
					.json()
					.catch( function () {
						return {};
					} )
					.then( function ( answer ) {
						if ( ! response.ok ) {
							throw new Error( ( answer && answer.message ) || '' );
						}

						return answer || {};
					} );
			} )
			.then( function ( answer ) {
				const now =
					request.state && request.state in answer
						? !! answer[ request.state ]
						: on;
				let since = answer.since_text || '';

				if ( ! since && request.since && answer[ request.since ] ) {
					const when = day( answer[ request.since ] );

					since = when ? fill( config.sinceLabel, when ) : '';
				}

				draw( button, config, now, since );

				const done = now ? config.doneOn : config.doneOff;

				say(
					done
						? fill( done, name )
						: ( name ? name + ': ' : '' ) +
								( now ? config.on : config.off )
				);
			} )
			// The same few words whatever went wrong, unless the toggle
			// asked for the server's own: what an API says -- a cookie
			// check, a capability's name -- is usually for a developer.
			.catch( function ( error ) {
				draw( button, config, was, '' );
				button.setAttribute( 'title', wasTitle );

				const why =
					( config.sayWhy && error && error.message ) ||
					config.failed;

				message( button, why, true );
				say( why );
			} )
			.finally( function () {
				button.removeAttribute( 'aria-busy' );
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		const button =
			event.target instanceof window.Element
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
		const direction = key + ( turningOn ? ':on' : ':off' );

		// Once, ever, before the first press that means it: say what it
		// means, and wait for the press that does.
		if (
			confirm &&
			! noted( direction ) &&
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
			note( direction );
		}

		button.removeAttribute( 'data-armed' );
		toggle( button, config );
	} );
} )();
