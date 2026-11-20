/**
 * What PHP handed the scripts.
 *
 * The only module that reads the page's globals. Each object is localized
 * by the class that enqueues its script, under the name it has always had:
 * `sugarCommerceReorder` (Reorder::enqueue()), `sugarCommerceInlineEdit`
 * (Traits\AssetManager), `sugarCommerceToggles` (ToggleRoute::enqueue()),
 * `sugarCommerceDeferredColumns` (HiddenColumns::enqueue()). `pagenow` is
 * core's own, printed on every admin screen.
 */

/**
 * The reorder script's settings: the REST route it saves through, and the
 * two sentences it says.
 *
 * @return {Object} sugarCommerceReorder, or a shape with no words in it.
 */
export function reorderConfig() {
	return window.sugarCommerceReorder || { i18n: {} };
}

/**
 * Quick and Bulk Edit's settings: Quick Edit's REST route, the strings.
 *
 * @return {Object} sugarCommerceInlineEdit, or an empty object.
 */
export function inlineEditConfig() {
	return window.sugarCommerceInlineEdit || {};
}

/**
 * The toggle cells' settings: the REST nonce and each column's toggle.
 *
 * @return {Object} sugarCommerceToggles, or an empty object.
 */
export function toggleConfig() {
	return window.sugarCommerceToggles || {};
}

/**
 * The deferred columns' settings: the route their choice is saved through.
 *
 * @return {Object} sugarCommerceDeferredColumns, or an empty object.
 */
export function deferredColumnsConfig() {
	return window.sugarCommerceDeferredColumns || {};
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
