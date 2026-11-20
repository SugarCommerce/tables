/**
 * Saving a toggle's other state over REST.
 *
 * Pressing one turns it over at once and saves; the answer redraws it, and
 * if the save is refused it turns back and says so beside itself, so what
 * the list shows is never a state the record does not have.
 */

import { fill } from '../lib/text';
import { day, draw, message } from './draw';

/**
 * Save the other state, and draw what the server says.
 *
 * @param {Element}  button        The toggle.
 * @param {Object}   config        Its column's settings.
 * @param {Object}   options       Options.
 * @param {string}   options.nonce The REST nonce.
 * @param {Function} options.fetch window.fetch, or a fake.
 * @param {Function} options.speak wp.a11y.speak, or a fake.
 * @return {Promise<void>} Settles when it is drawn either way.
 */
export function save( button, config, { nonce, fetch, speak } ) {
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

	return (
		fetch(
			String( request.url ).replace(
				'{id}',
				encodeURIComponent( button.getAttribute( 'data-id' ) )
			),
			{
				method: request.method || 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce || '',
				},
				body: JSON.stringify( body ),
			}
		)
			.then( ( response ) =>
				response
					.json()
					.catch( () => ( {} ) )
					.then( ( answer ) => {
						if ( ! response.ok ) {
							throw new Error(
								( answer && answer.message ) || ''
							);
						}

						return answer || {};
					} )
			)
			.then( ( answer ) => {
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

				speak(
					done
						? fill( done, name )
						: ( name ? name + ': ' : '' ) +
								( now ? config.on : config.off )
				);
			} )
			// The same few words whatever went wrong, unless the toggle asked
			// for the server's own: what an API says -- a cookie check, a
			// capability's name -- is usually for a developer.
			.catch( ( error ) => {
				draw( button, config, was, '' );
				button.setAttribute( 'title', wasTitle );

				const why =
					( config.sayWhy && error && error.message ) ||
					config.failed;

				message( button, why, true );
				speak( why );
			} )
			.finally( () => {
				button.removeAttribute( 'aria-busy' );
			} )
	);
}
