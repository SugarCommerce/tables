/**
 * The few DOM helpers the scripts share, so none of them needs jQuery.
 *
 * show() and hide() do what jQuery's did, because core's list tables and
 * the rows PHP prints were written against them: hide() puts
 * `display: none` on the element, show() takes it off again, and if a
 * stylesheet still hides the element -- `.hidden`, or the inline style
 * PHP printed on a template row -- gives it the display its tag has.
 */

/**
 * The first element matching a selector.
 *
 * @param {string}           selector CSS selector.
 * @param {Document|Element} root     Where to look.
 * @return {Element|null} The element.
 */
export const one = ( selector, root = document ) =>
	root.querySelector( selector );

/**
 * Every element matching a selector, as an array.
 *
 * @param {string}           selector CSS selector.
 * @param {Document|Element} root     Where to look.
 * @return {Element[]} The elements.
 */
export const all = ( selector, root = document ) =>
	Array.from( root.querySelectorAll( selector ) );

/**
 * Listen for an event on whatever matches a selector, now or later.
 *
 * Delegated, so a row Quick Edit replaces, or an editor cloned after the
 * page loaded, is listened to as well as the one that was there first.
 *
 * @param {Document|Element} root     Where to listen.
 * @param {string}           type     Event type.
 * @param {string}           selector What the event must come from.
 * @param {Function}         handler  Called with ( event, matched element ).
 * @return {Function} Stops listening.
 */
export function on( root, type, selector, handler ) {
	const listener = ( event ) => {
		const target =
			event.target instanceof window.Element
				? event.target.closest( selector )
				: null;

		if ( target && root.contains( target ) ) {
			handler( event, target );
		}
	};

	root.addEventListener( type, listener );

	return () => root.removeEventListener( type, listener );
}

/**
 * Whether an element takes up room on the page: jQuery's `:visible`.
 *
 * @param {Element} element The element.
 * @return {boolean} Shown.
 */
export const isShown = ( element ) =>
	!! (
		element.offsetWidth ||
		element.offsetHeight ||
		element.getClientRects().length
	);

/**
 * Hide an element.
 *
 * @param {Element|null} element The element.
 */
export function hide( element ) {
	if ( element ) {
		element.style.display = 'none';
	}
}

/**
 * The display an element's tag has when nothing hides it.
 *
 * @param {Element} element The element.
 * @return {string} table-row for a row, and so on.
 */
function naturalDisplay( element ) {
	return (
		{
			TR: 'table-row',
			TD: 'table-cell',
			TH: 'table-cell',
			TBODY: 'table-row-group',
			LI: 'list-item',
			SPAN: 'inline',
			A: 'inline',
		}[ element.tagName ] || 'block'
	);
}

/**
 * Show an element.
 *
 * @param {Element|null} element The element.
 */
export function show( element ) {
	if ( ! element ) {
		return;
	}

	element.style.display = '';

	if ( 'none' === window.getComputedStyle( element ).display ) {
		element.style.display = naturalDisplay( element );
	}
}

/**
 * Fade an element in from nothing, then resolve.
 *
 * @param {Element} element  The element.
 * @param {number}  duration Milliseconds.
 * @return {Promise<void>} Settles when it is fully shown.
 */
export function fadeIn( element, duration = 400 ) {
	return new Promise( ( resolve ) => {
		element.style.opacity = '0';
		show( element );

		// Read once, so the browser has drawn it at nothing before the
		// transition starts; otherwise it starts where it ends.
		void element.offsetWidth;

		element.style.transition = `opacity ${ duration }ms`;
		element.style.opacity = '1';

		setTimeout( () => {
			element.style.transition = '';
			element.style.opacity = '';
			resolve();
		}, duration );
	} );
}

/**
 * An element's siblings that match a selector, not counting itself.
 *
 * @param {Element} element  The element.
 * @param {string}  selector CSS selector.
 * @return {Element[]} The siblings.
 */
export function siblings( element, selector ) {
	return element.parentElement
		? Array.from( element.parentElement.children ).filter(
				( sibling ) =>
					sibling !== element && sibling.matches( selector )
			)
		: [];
}
