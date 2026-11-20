/**
 * An item's picture is a person's avatar's size, corner and line.
 *
 * A list of subscriptions has a customer and a product side by side in each
 * row. The product's thumbnail was once 24px against the customer's 32px
 * avatar, so the two pictures in one row were different sizes. The cells
 * are drawn by the package itself (pictures.php) and shown under
 * WordPress's own stylesheets and this package's.
 */
import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { core, ours, pkg } from './support.mjs';

const drawn = JSON.parse(
	execFileSync( process.env.PHP_BINARY ?? 'php', [
		join( pkg, 'tests/browser/pictures.php' ),
	] ).toString()
);

const page = `<!doctype html><html><head><meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	${ [ ...core, ours ].map( ( css ) => `<style>${ css }</style>` ).join( '' ) }
	</head><body class="wp-admin wp-core-ui js">
	<div id="wpwrap"><div id="wpcontent"><div id="wpbody"><div id="wpbody-content"><div class="wrap">
	<table class="wp-list-table widefat fixed striped table-view-list">
	<thead><tr><th class="manage-column column-name column-primary">Name</th>
	<th class="manage-column column-customer">Customer</th>
	<th class="manage-column column-product">Product</th>
	<th class="manage-column column-image">Image</th></tr></thead>
	<tbody id="the-list"><tr class="is-expanded">
	<td class="name column-name has-row-actions column-primary" data-colname="Name"><strong>#11</strong></td>
	<td class="customer column-customer" data-colname="Customer">${ drawn.person }</td>
	<td class="product column-product" data-colname="Product">${ drawn.tile }</td>
	<td class="image column-image" data-colname="Image">${ drawn.image }</td>
	</tr></tbody></table>
	</div></div></div></div></div></body></html>`;

/**
 * A picture's size, corner and how far down its cell it starts.
 *
 * @param {import('@playwright/test').Page} browser
 * @param {string}                          selector
 * @return {Promise<Object>} The measurements.
 */
function measure( browser, selector ) {
	return browser.locator( selector ).evaluate( ( el ) => {
		const box = el.getBoundingClientRect();
		const cell = el.closest( 'td' ).getBoundingClientRect();
		const style = window.getComputedStyle( el );

		return {
			width: box.width,
			height: box.height,
			radius:
				parseFloat( style.borderTopLeftRadius ) *
				( style.borderTopLeftRadius.endsWith( '%' )
					? box.width / 100
					: 1 ),
			top: box.top - cell.top,
		};
	} );
}

for ( const width of [ 1440, 390 ] ) {
	test( `an item's picture is a person's avatar's size at ${ width }px`, async ( {
		page: browser,
	} ) => {
		await browser.setViewportSize( { width, height: 900 } );
		await browser.setContent( page );
		await browser.waitForLoadState( 'load' );

		const avatar = await measure( browser, '.column-customer img.avatar' );
		const tile = await measure(
			browser,
			'.column-product .sugarcommerce-item > img'
		);
		const image = await measure(
			browser,
			'.column-image .sugarcommerce-item > img'
		);

		expect( avatar.width ).toBe( 32 );
		expect( avatar.height ).toBe( 32 );

		for ( const picture of [ tile, image ] ) {
			expect( picture.width ).toBe( avatar.width );
			expect( picture.height ).toBe( avatar.height );
			expect( picture.radius ).toBeCloseTo( avatar.radius, 2 );
			expect( picture.top ).toBeCloseTo( avatar.top, 2 );
		}
	} );
}
