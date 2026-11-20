/**
 * A row is as tall pointed at, focused or neither -- and as tall as a post's
 * on Posts with the same content.
 *
 * Core keeps a row's line of actions under its title at all times, moved
 * off to the left until the row is hovered or `.row-actions.visible` (which
 * common.js adds on keyboard focus). A stylesheet here once took that line
 * away while hidden, and every row grew 14px when the pointer crossed it.
 *
 * Two pages from WordPress's own stylesheets -- found in the WordPress this
 * package sits in, or in `WP_CORE_DIR` -- with the same row on each: a
 * Posts row under core's styles alone, and the row a table here draws
 * under this package's stylesheet as well.
 */
import { test, expect } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname( fileURLToPath( import.meta.url ) );
const pkg = resolve( here, '../..' );

/**
 * The WordPress whose stylesheets the pages are built from.
 *
 * @return {string} Its root folder.
 */
function wordpress() {
	const candidates = [ process.env.WP_CORE_DIR ].filter( Boolean );

	for ( let dir = pkg; dirname( dir ) !== dir; dir = dirname( dir ) ) {
		candidates.push( dir );
	}

	const root = candidates.find( ( dir ) =>
		existsSync( join( dir, 'wp-admin/css/list-tables.css' ) )
	);

	if ( ! root ) {
		throw new Error(
			'No WordPress found above this package: set WP_CORE_DIR to one.'
		);
	}

	return root;
}

const core = [
	'wp-includes/css/dashicons.css',
	'wp-includes/css/buttons.css',
	'wp-admin/css/common.css',
	'wp-admin/css/forms.css',
	'wp-admin/css/list-tables.css',
	'wp-admin/css/edit.css',
].map( ( file ) => readFileSync( join( wordpress(), file ), 'utf8' ) );

const ours = readFileSync( join( pkg, 'assets/css/admin-tables.css' ), 'utf8' );

const actions =
	'<div class="row-actions">' +
	'<span class="edit"><a href="#edit">Edit</a> | </span>' +
	'<span class="inline"><button type="button" class="button-link">Quick&nbsp;Edit</button> | </span>' +
	'<span class="trash"><a href="#trash" class="submitdelete">Trash</a> | </span>' +
	'<span class="view"><a href="#view">View</a></span>' +
	'</div>' +
	'<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>';

/**
 * A list table's page, as the admin draws it.
 *
 * @param {Object}   page
 * @param {string}   page.body    The body's classes.
 * @param {string}   page.table   The table's classes.
 * @param {string}   page.primary The primary column's classes.
 * @param {string[]} page.styles  Its stylesheets.
 * @return {string} The page.
 */
function list( { body, table, primary, styles } ) {
	return `<!doctype html><html><head><meta charset="utf-8">
		<meta name="viewport" content="width=device-width,initial-scale=1">
		${ styles.map( ( css ) => `<style>${ css }</style>` ).join( '' ) }
		</head><body class="wp-admin wp-core-ui js ${ body }">
		<div id="wpwrap"><div id="wpcontent"><div id="wpbody"><div id="wpbody-content"><div class="wrap">
		<table class="${ table }">
		<thead><tr><td id="cb" class="manage-column column-cb check-column"><input type="checkbox"></td>
		<th scope="col" class="manage-column ${ primary }">Title</th>
		<th scope="col" class="manage-column column-author">Author</th>
		<th scope="col" class="manage-column column-date">Date</th></tr></thead>
		<tbody>${ [ 1, 2 ]
			.map(
				( id ) => `<tr id="row-${ id }">
				<td class="check-column"><input type="checkbox"></td>
				<th scope="row" class="${ primary } has-row-actions column-primary" data-colname="Title">
				<strong><a class="row-title" href="#${ id }">Spring sale</a></strong>${ actions }</th>
				<td class="column-author" data-colname="Author">admin</td>
				<td class="column-date" data-colname="Date">2026/05/11</td></tr>`
			)
			.join( '' ) }</tbody></table>
		</div></div></div></div></div>
		<script>
		// common.js: a row's actions shown while anything in its cell has focus.
		document.addEventListener( 'focusin', ( event ) => {
			document.querySelectorAll( '.row-actions.visible' ).forEach( ( el ) => el.classList.remove( 'visible' ) );
			event.target.closest( '.table-view-list .has-row-actions' )?.querySelector( '.row-actions' )?.classList.add( 'visible' );
		} );
		</script></body></html>`;
}

const pages = {
	posts: list( {
		body: 'edit-php post-type-post',
		table: 'wp-list-table widefat fixed striped table-view-list posts',
		primary: 'title column-title',
		styles: core,
	} ),
	// What Table draws: no `fixed`, always core's list view, the primary
	// column named after its field.
	table: list( {
		body: 'admin-table admin-table-discounts',
		table: 'wp-list-table widefat striped table-view-list discounts',
		primary: 'name column-name',
		styles: [ ...core, ours ],
	} ),
};

/**
 * The first row's height and its actions' state: at rest, pointed at, and
 * with the keyboard on its first action.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          html
 * @return {Promise<Object>} Heights and whether the actions were shown.
 */
async function measure( page, html ) {
	await page.setContent( html );

	const row = page.locator( '#row-1' );
	const state = () =>
		row.evaluate( ( tr ) => {
			const box = tr
				.querySelector( '.row-actions' )
				.getBoundingClientRect();

			return {
				height: tr.getBoundingClientRect().height,
				shown: box.left >= 0 && box.height > 0,
			};
		} );

	await page.mouse.move( 0, 0 );
	const rest = await state();

	// The pointer on the title itself (on a phone core lays the toggle
	// button over the cell, which a hover by locator waits behind).
	const title = await row.locator( '.row-title' ).boundingBox();
	await page.mouse.move( title.x + 2, title.y + title.height / 2 );
	const hover = await state();

	// The keyboard goes from the title to its first action.
	await page.mouse.move( 0, 0 );
	await row.locator( '.row-title' ).focus();
	await page.keyboard.press( 'Tab' );
	const focus = {
		...( await state() ),
		on: await row.evaluate(
			( tr ) => tr.ownerDocument.activeElement.textContent
		),
	};

	return { rest, hover, focus };
}

for ( const width of [ 1440, 390 ] ) {
	test( `a row keeps its height at ${ width }px, as a post's does`, async ( {
		page,
	} ) => {
		await page.setViewportSize( { width, height: 900 } );

		const posts = await measure( page, pages.posts );
		const table = await measure( page, pages.table );

		// The pages are what they claim to be: core hides the actions at
		// rest and shows them on hover and on focus.
		expect( posts.rest.shown ).toBe( false );
		expect( posts.hover.shown ).toBe( true );
		expect( posts.focus.shown ).toBe( true );

		expect( table.rest.shown ).toBe( false );
		expect( table.hover.shown ).toBe( true );
		expect( table.focus.shown ).toBe( true );
		expect( table.focus.on ).toBe( 'Edit' );

		expect( table.hover.height ).toBe( table.rest.height );
		expect( table.focus.height ).toBe( table.rest.height );

		expect( table.rest.height ).toBe( posts.rest.height );
		expect( table.hover.height ).toBe( posts.hover.height );
	} );
}
