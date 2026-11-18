/**
 * A column switched on in Screen Options that was not worked out.
 *
 * Some columns of a list ask another table for what they show, and do not
 * ask while they are hidden: their cells hold a mark and nothing else.
 * WordPress shows a column the moment its box is ticked, without asking
 * the server for anything, so one of those would appear as a column of
 * marks. This waits for WordPress to have saved the choice -- it does that
 * in a request of its own -- and loads the list again, which draws the
 * column properly.
 *
 * Nothing happens for a column that was drawn in full, which is most of
 * them.
 */
( function () {
	'use strict';

	const $ = window.jQuery;

	let waiting = false;

	$( document ).on( 'click', '#adv-settings .hide-column-tog', function () {
		if ( ! this.checked ) {
			return;
		}

		const cells = document.querySelectorAll(
			'.wp-list-table td.column-' +
				this.value +
				' .sugarcommerce-column-deferred'
		);

		if ( cells.length ) {
			waiting = true;
		}
	} );

	$( document ).on( 'ajaxComplete', function ( event, xhr, settings ) {
		const sent =
			settings && typeof settings.data === 'string' ? settings.data : '';

		if ( waiting && sent.indexOf( 'action=hidden-columns' ) !== -1 ) {
			waiting = false;
			window.location.reload();
		}
	} );
} )();
