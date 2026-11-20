/**
 * Keeping a table looking like itself while one of its rows is lifted out.
 *
 * A table row lifted out of its table forgets its column widths, and the
 * row being dragged collapses to the width of its text. Each cell is held
 * at the width it had. Landing lets them go.
 */

/**
 * The cells of a row that take up a column on the screen.
 *
 * A column switched off in Screen Options is still in every row, as a
 * cell core hides. It takes no room, so it is no column.
 *
 * @param {Element} row The row.
 * @return {Element[]} Its cells that are shown.
 */
export function visibleCells( row ) {
	return Array.from( row.children ).filter(
		( cell ) => 'none' !== window.getComputedStyle( cell ).display
	);
}

/**
 * How many columns a row spans on the screen.
 *
 * @param {Element} row The row.
 * @return {number} Its shown columns.
 */
export function span( row ) {
	return visibleCells( row ).reduce(
		( total, cell ) => total + ( cell.colSpan || 1 ),
		0
	);
}

/**
 * Hold each shown cell of a row at the width it has now.
 *
 * Measured first and set after, so setting one cannot move the next
 * before it is read; and set on the cell's border box, which is what was
 * measured, not its content, which is narrower by the padding.
 *
 * @param {Element} row The row.
 */
export function hold( row ) {
	const cells = visibleCells( row );
	const widths = cells.map( ( cell ) => cell.getBoundingClientRect().width );

	cells.forEach( ( cell, index ) => {
		cell.style.boxSizing = 'border-box';
		cell.style.width = widths[ index ] + 'px';
	} );
}

/**
 * Let a row's cells take whatever width the table gives them again.
 *
 * @param {Element} row The row.
 */
export function release( row ) {
	Array.from( row.children ).forEach( ( cell ) => {
		cell.style.boxSizing = '';
		cell.style.width = '';
	} );
}

/**
 * The gap a row will drop into: one cell across the columns that are
 * shown.
 *
 * Spanning the hidden ones as well gives the table columns its header does
 * not have, and while the row is held every other row is squeezed up to
 * leave an empty band down the right where they would be.
 *
 * @param {Element} placeholder The placeholder row.
 * @param {Element} row         The row being dragged.
 */
export function fillGap( placeholder, row ) {
	const cell = document.createElement( 'td' );

	cell.colSpan = span( row );
	placeholder.replaceChildren( cell );
}
