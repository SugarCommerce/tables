/**
 * What PHP handed the scripts, and where they may listen.
 *
 * The only module that reads the page's configuration. Each build of this
 * library -- the plain one, or a Strauss-prefixed copy inside another plugin
 * -- files each script's settings in `window.SugarCommerceTables`, keyed by
 * the script's handle (Support\ScriptConfig): Reorder::enqueue(),
 * Traits\AssetManager, ToggleRoute::enqueue(), HiddenColumns::enqueue().
 * Two copies load two of each script, and a fixed global would leave
 * whichever printed last owning both copies' routes.
 *
 * A script finds its own entry from the id WordPress stamps on its <script>
 * element, "{handle}-js". The entry names its build, as the screen's `.wrap`
 * does (`data-sugarcommerce-tables`), and a script listens inside that
 * screen alone. `pagenow` is core's own, printed on every admin screen.
 */

// Read while the script runs. Once it has, document.currentScript is null.
const script = document.currentScript;

/**
 * The entry a script's element names in a registry.
 *
 * @param {Element|null} element  The <script> element, or null.
 * @param {Object}       registry window.SugarCommerceTables.
 * @return {Object|undefined} The build's entry, if it published one.
 */
export function resolve( element, registry ) {
	const handle =
		element && element.id ? element.id.replace( /-js$/, '' ) : '';

	return handle ? ( registry || {} )[ handle ] : undefined;
}

/**
 * This script's own settings: its build, its REST route, its words.
 *
 * @return {Object|undefined} The entry, or nothing when PHP printed none.
 */
export function own() {
	return resolve( script, window.SugarCommerceTables );
}

/**
 * The screen a build's scripts belong to: the `.wrap` that names it.
 *
 * @param {Document} doc   The document.
 * @param {string}   build The build, as its entry names it.
 * @return {Element|null} The screen, or null when it is another build's.
 */
export function rootOf( doc, build ) {
	if ( ! build ) {
		return null;
	}

	return (
		Array.from(
			doc.querySelectorAll( '[data-sugarcommerce-tables]' )
		).find(
			( element ) =>
				element.getAttribute( 'data-sugarcommerce-tables' ) === build
		) || null
	);
}

/**
 * Begin, once: this script's settings and its screen, or nothing.
 *
 * Nothing without settings, without its own build's screen on the page, or
 * a second time -- the flag that it has begun is kept in its own entry,
 * never in a name another copy shares.
 *
 * @param {Document}         doc   The document.
 * @param {Object|undefined} entry This script's entry (own()).
 * @return {?{config: Object, root: Element}} What to work with, or null.
 */
export function claim( doc, entry ) {
	const root = entry ? rootOf( doc, entry.build ) : null;

	if ( ! root || entry.bound ) {
		return null;
	}

	entry.bound = true;

	return { config: entry, root };
}

/**
 * Core's name for this admin screen, which it saves a screen's choices
 * under.
 *
 * @return {string} The screen, or an empty string.
 */
export function screenName() {
	return window.pagenow || '';
}
