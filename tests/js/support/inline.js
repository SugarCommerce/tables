/**
 * A list page with Quick Edit and Bulk Edit, as InlineEdit and the list
 * table print it: rows with their hidden `#inline_{id}` data blocks, core's
 * bulk controls above and below, and the hidden table holding the two
 * editor rows.
 *
 * jsdom draws nothing, so "shown" is faked the way a browser decides it:
 * an element is shown unless it, or something around it, is display: none.
 */

const ROWS = [
	{ id: '11', title: 'PG-1001', status: 'complete', labels: '1,2' },
	{ id: '12', title: 'PG-1002 <b>', status: 'refunded', labels: '2' },
	{ id: '13', title: 'PG-1003', status: 'pending', labels: '' },
];

/**
 * A row of the list.
 *
 * @param {Object} row Its values.
 * @return {string} Markup.
 */
export const rowMarkup = ( row ) => `
	<tr id="item-${ row.id }">
		<th class="check-column"><input type="checkbox" name="item[]" value="${ row.id }">
			<div class="hidden" id="inline_${ row.id }"><div class="row_title">${ row.title.replace( '<', '&lt;' ).replace( '>', '&gt;' ) }</div><div class="status">${ row.status }</div><div class="labels">${ row.labels }</div><div class="vip">${ row.vip || '0' }</div></div>
		</th>
		<td class="column-primary"><strong><a class="row-title" href="#">${ row.title.replace( '<', '&lt;' ).replace( '>', '&gt;' ) }</a></strong>
			<div class="row-actions"><span class="inline"><button type="button" class="button-link editinline" aria-expanded="false">Quick Edit</button></span></div>
		</td>
		<td class="column-status">${ row.status }</td>
		<td class="column-notes hidden">-</td>
	</tr>`;

const CHECKLIST = ( bulk ) => `
	<fieldset class="inline-edit-col-center inline-edit-categories"><div class="inline-edit-col">
		<input type="hidden" name="labels[]" value="" />
		<ul class="cat-checklist labels-checklist" data-checklist="labels">
			<li id="${ bulk ? 'b' : 'q' }-l1"><label class="selectit"><input value="1" type="checkbox" name="labels[]" /> Gift</label></li>
			<li id="${ bulk ? 'b' : 'q' }-l2"><label class="selectit"><input value="2" type="checkbox" name="labels[]" /> Rush</label></li>
			<li id="${ bulk ? 'b' : 'q' }-l3"><label class="selectit"><input value="3" type="checkbox" name="labels[]" /> Fragile</label></li>
		</ul>
	</div></fieldset>`;

/**
 * Draw the page.
 *
 * @return {Object} The parts the tests reach for.
 */
export function inlinePage() {
	document.head.innerHTML =
		'<style>.hidden { display: none; } .screen-reader-text { position: absolute; }</style>';
	document.body.innerHTML = `
		<div class="wrap"><h1>Orders</h1><hr class="wp-header-end">
		<form id="list">
		<div class="tablenav top"><select name="action" id="bulk-action-selector-top"><option value="-1">Bulk actions</option><option value="complete">Mark complete</option><option value="edit">Bulk edit</option></select><input type="submit" id="doaction" value="Apply"></div>
		<table class="wp-list-table widefat">
			<thead><tr>
				<td class="check-column"></td>
				<th class="column-primary">Order</th>
				<th class="column-status">Status</th>
				<th class="column-notes hidden">Notes</th>
			</tr></thead>
			<tbody id="the-list">${ ROWS.map( rowMarkup ).join( '' ) }</tbody>
		</table>
		<div class="tablenav bottom"><select name="action2" id="bulk-action-selector-bottom"><option value="-1">Bulk actions</option><option value="edit">Bulk edit</option></select><input type="submit" id="doaction2" value="Apply"></div>
		<table style="display: none"><tbody id="inlineedit">
			<tr id="inline-edit" class="inline-edit-row inline-edit-row-post quick-edit-row" style="display: none">
				<td colspan="9" class="colspanchange"><div class="inline-edit-wrapper">
					<fieldset class="inline-edit-col-left"><div class="inline-edit-col">
						<p class="inline-edit-row-title"><span class="title">Order</span> <strong class="inline-edit-row-title-text" data-row-title></strong></p>
					</div></fieldset>
					${ CHECKLIST( false ) }
					<fieldset class="inline-edit-col-right"><div class="inline-edit-col">
						<label><span class="title">Status</span><select name="status"><option value="pending">Pending</option><option value="complete">Complete</option><option value="failed">Failed</option></select></label>
						<label><input type="checkbox" name="vip" value="1"> VIP</label>
						<label><span class="title">Note</span><textarea name="note">line one
line two</textarea></label>
						<label><input type="text" name="extra" value="from the hook"></label>
						<input type="text" name="locked" value="x" disabled>
					</div></fieldset>
					<div class="submit inline-edit-save">
						<input type="hidden" name="_inline_edit" value="qnonce" />
						<input type="hidden" name="table_id" value="orders" />
						<input type="hidden" name="item_id" value="0" />
						<button type="button" class="button button-primary save">Update</button>
						<button type="button" class="button cancel">Cancel</button>
						<span class="spinner"></span>
						<div class="notice notice-error notice-alt inline hidden"><p class="error"></p></div>
					</div>
				</div></td>
			</tr>
			<tr id="bulk-edit" class="inline-edit-row bulk-edit-row" style="display: none">
				<td colspan="9" class="colspanchange"><div class="inline-edit-wrapper">
					<fieldset class="inline-edit-col-left"><div class="inline-edit-col"><div id="bulk-title-div"><div id="bulk-titles"></div></div></div></fieldset>
					${ CHECKLIST( true ) }
					<fieldset class="inline-edit-col-right"><div class="inline-edit-col">
						<label><span class="title">Status</span><select name="status"><option value="">— No change —</option><option value="complete">Complete</option></select></label>
					</div></fieldset>
					<div class="submit inline-edit-save">
						<input type="submit" name="bulk_edit" id="bulk_edit" class="button button-primary" value="Update">
						<button type="button" class="button cancel">Cancel</button>
					</div>
				</div></td>
			</tr>
		</tbody></table>
		</form></div>`;

	// Shown unless it or an ancestor is display: none, or it is a hidden
	// input: what offsetWidth and getClientRects() say in a browser.
	const shown = ( element ) => {
		// The browser's own stylesheet hides these.
		if ( element.matches( 'input[type="hidden"]' ) ) {
			return false;
		}

		for (
			let at = element;
			at && at.nodeType === 1;
			at = at.parentElement
		) {
			if ( 'none' === window.getComputedStyle( at ).display ) {
				return false;
			}
		}

		return true;
	};

	Object.defineProperty( window.HTMLElement.prototype, 'offsetWidth', {
		configurable: true,
		get() {
			return shown( this ) ? 10 : 0;
		},
	} );

	return {
		list: document.getElementById( 'the-list' ),
		bulk: document.getElementById( 'bulk-edit' ),
		rows: ROWS,
	};
}

/**
 * A post that answers with a row and a notice, or a failure.
 *
 * @param {Object} answer { ok, text, notice } -- text is the row's markup.
 * @return {Function} The post, with `.calls`.
 */
export function fakePost( answer ) {
	const calls = [];
	const post = ( path, body ) => {
		calls.push( [ path, body ] );

		if ( answer instanceof Error ) {
			return Promise.reject( answer );
		}

		return Promise.resolve( {
			ok: answer.ok ?? true,
			row: answer.text ?? '',
			notice: answer.notice ?? null,
		} );
	};

	post.calls = calls;

	return post;
}
