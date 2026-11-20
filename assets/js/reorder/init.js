/**
 * A list's reorder, put together: the order, the keys and buttons, and the
 * drag.
 */

import { fillGap, hold, release } from './cells';
import { bindControls } from './controls';
import { createOrder } from './order';
import { sortable } from './sortable';

/**
 * Make a list reorderable.
 *
 * @param {Element}  list           The list's tbody.
 * @param {Object}   options        Options.
 * @param {Object}   options.config sugarCommerceReorder.
 * @param {Function} options.fetch  window.fetch, or a fake.
 * @param {Function} options.speak  wp.a11y.speak, or a fake.
 * @return {Object} The list's order (createOrder()).
 */
export function initReorder( list, { config, fetch, speak: say } ) {
	const order = createOrder( list, {
		config,
		fetch,
		speak: ( message, politeness ) => message && say( message, politeness ),
	} );

	bindControls( list, order.step );

	const table = list.parentElement;
	const heading = table ? table.querySelector( 'thead tr' ) : null;

	if ( order.rows().length > 1 ) {
		sortable( list, {
			handle: '.sugarcommerce-reorder__handle',

			// The handle is a button, so that it can take focus and the
			// arrow keys; a drag must still never begin on a tick box, a
			// link or a step button.
			cancel: 'input, textarea, select, option, a, .sugarcommerce-reorder__step',
			placeholder: 'sugarcommerce-reorder-placeholder',

			// The lifted row's cells are held at the widths they had, and
			// so are the heading's, so the columns do not move under it.
			helper( event, row ) {
				hold( row );

				if ( heading ) {
					hold( heading );
				}
			},
			start( event, { item, placeholder } ) {
				fillGap( placeholder, item );
			},
			stop( event, { item } ) {
				release( item );

				if ( heading ) {
					release( heading );
				}
			},
			update( event, { item } ) {
				order.save( item );
			},
		} );
	}

	return order;
}
