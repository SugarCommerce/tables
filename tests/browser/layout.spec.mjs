/**
 * A table's layout is core's: row actions in the first column after the
 * tick box, and ten columns of long words still inside the screen.
 *
 * The table is drawn by the package itself (render.php: its classes, its
 * primary column, its cells and row actions, registered with no
 * `primary_column` as most lists are) and shown under WordPress's own
 * stylesheets and this package's. A release once dropped core's `fixed`
 * layout and skipped an `id` column for the primary one: a list's Edit |
 * Delete moved under the customer and a long address pushed the table past
 * the right edge of its box.
 */
import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { core, ours, pkg } from './support.mjs';

const drawn = JSON.parse(
	execFileSync( process.env.PHP_BINARY ?? 'php', [
		join( pkg, 'tests/browser/render.php' ),
	] ).toString()
);

const page = `<!doctype html><html><head><meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	${ [ ...core, ours ].map( ( css ) => `<style>${ css }</style>` ).join( '' ) }
	</head><body class="wp-admin wp-core-ui js admin-table admin-table-shop_subscriptions">
	<div id="wpwrap"><div id="wpcontent"><div id="wpbody"><div id="wpbody-content"><div class="wrap">
	<form id="list"><table class="wp-list-table ${ drawn.classes }">
	<thead>${ drawn.head }</thead><tbody id="the-list">${ drawn.rows }</tbody>
	</table></form>
	</div></div></div></div></div></body></html>`;

/**
 * Where the first row's actions are, and how wide everything is.
 *
 * @param {import('@playwright/test').Page} browser
 * @return {Promise<Object>} The measurements.
 */
function measure( browser ) {
	return browser.evaluate( () => {
		const table = document.querySelector( '.wp-list-table' );
		const row = table.querySelector( '#the-list tr' );
		const actions = row.querySelector( '.row-actions' );
		const cell = actions.closest( 'th, td' );
		const shown = Array.from( row.children ).filter(
			( child ) => 'none' !== window.getComputedStyle( child ).display
		);
		const box = cell.getBoundingClientRect();
		const links = actions.getBoundingClientRect();

		return {
			layout: window.getComputedStyle( table ).tableLayout,
			cell: cell.className,
			index: shown.indexOf( cell ),
			inside: links.left >= box.left - 1 && links.right <= box.right + 1,
			viewport: window.innerWidth,
			scroll: document.documentElement.scrollWidth,
			table: table.getBoundingClientRect().right,
			box: table.parentElement.getBoundingClientRect().right,
		};
	} );
}

test( 'the primary column is the first after the tick box', () => {
	expect( drawn.primary ).toBe( 'id' );
	expect( drawn.classes.split( ' ' ) ).toContain( 'fixed' );
} );

for ( const width of [ 1440, 390 ] ) {
	test( `row actions live in the first data column at ${ width }px`, async ( {
		page: browser,
	} ) => {
		await browser.setViewportSize( { width, height: 900 } );
		await browser.setContent( page );
		await browser.hover( '#row-11 > :nth-child(2)' );

		const at = await measure( browser );

		expect( at.cell ).toContain( 'column-id' );
		expect( at.cell ).toContain( 'column-primary' );
		expect( at.index ).toBe( 1 );
		expect( at.inside ).toBe( true );
	} );
}

test( 'ten columns of long words stay inside the screen at 1440px', async ( {
	page: browser,
} ) => {
	await browser.setViewportSize( { width: 1440, height: 900 } );
	await browser.setContent( page );

	const at = await measure( browser );

	expect( at.scroll ).toBeLessThanOrEqual( at.viewport );
	expect( at.table ).toBeLessThanOrEqual( at.box );
	expect( at.layout ).toBe( 'fixed' );
} );
