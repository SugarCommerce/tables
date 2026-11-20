/**
 * Two plugins' copies of the library on one page: each script reads its own
 * build's settings, listens inside its own build's screen, and keeps the
 * flag that it has begun in its own entry of the registry.
 *
 * The built scripts themselves are run, as WordPress prints them for two
 * builds -- each <script> element's id is what tells it which copy it is.
 */

import { readFileSync } from 'node:fs';
import { resolve as path } from 'node:path';

import { JSDOM } from 'jsdom';
import { afterEach, describe, expect, test } from 'vitest';

import { claim, resolve, rootOf } from '../../assets/js/lib/config';
import { bindToggles } from '../../assets/js/toggle/press';

const ENTRIES = [ 'toggle', 'inline-edit', 'deferred-columns', 'reorder' ];

const BUILT = Object.fromEntries(
	ENTRIES.map( ( entry ) => [
		entry,
		readFileSync(
			path( __dirname, `../../assets/build/${ entry }.js` ),
			'utf8'
		),
	] )
);

/**
 * What one build's PHP hands each of its scripts.
 *
 * @param {string} build The build.
 * @return {Object} Settings by entry.
 */
const settings = ( build ) => ( {
	toggle: {
		columns: {
			'demo:featured': {
				on: 'Featured',
				off: 'Not featured',
				icons: { on: '', off: '' },
				request: { url: `/${ build }/{id}`, key: 'featured' },
			},
		},
	},
	'inline-edit': { path: `${ build }/v1/demo/quick-edit` },
	'deferred-columns': { path: `${ build }/v1/demo/hidden-columns` },
	reorder: { path: `${ build }/v1/demo/reorder`, i18n: {} },
} );

/**
 * One build's screen: a toggle, and a column -- deferred or drawn.
 *
 * @param {string}  build    The build.
 * @param {boolean} deferred Whether its `total` column is only marks.
 * @return {string} Markup.
 */
const screen = ( build, deferred ) =>
	`<div class="wrap" data-sugarcommerce-tables="${ build }"><h1>${ build }</h1>
		<table class="wp-list-table widefat"><thead><tr><th id="total" class="manage-column column-total hidden">Total</th></tr></thead>
		<tbody><tr><td class="column-total">${
			deferred
				? '<span class="sugarcommerce-column-deferred">…</span>'
				: '£5'
		}</td>
		<td><button type="button" class="sugarcommerce-toggle" data-toggle="demo:featured" data-id="7" aria-pressed="false">off</button><span class="sugarcommerce-toggle__message" hidden></span></td></tr></tbody></table>
	</div>`;

/**
 * Every script of one build, printed as WordPress prints it: the settings
 * before, filed under the handle, then the script with id "{handle}-js".
 *
 * @param {string} build The build.
 * @return {string} Markup.
 */
const printed = ( build ) =>
	ENTRIES.map( ( entry ) => {
		const handle = `${ build }-${ entry }`;
		const config = { build, ...settings( build )[ entry ] };

		return (
			`<script>window.SugarCommerceTables=window.SugarCommerceTables||{};window.SugarCommerceTables[${ JSON.stringify(
				handle
			) }]=${ JSON.stringify( config ) };</script>` +
			`<script id="${ handle }-js">${ BUILT[ entry ].replace(
				/<\/script/gi,
				'<\\/script'
			) }</script>`
		);
	} ).join( '' );

let dom;

/**
 * A page holding the screens given, with every script of both builds.
 *
 * @param {string} screens Markup.
 * @return {{window: Window, sent: string[]}} The page, and every request.
 */
function load( screens ) {
	const sent = [];

	dom = new JSDOM(
		`<!doctype html><html><head><script>window.wp={apiFetch:function(o){window.sent.push(o.path||o.url);return new Promise(function(){});},domReady:function(f){f();},a11y:{speak:function(){}}};</script></head>` +
			`<body><div id="adv-settings"><input type="checkbox" class="hide-column-tog" value="total"></div>${ screens }` +
			printed( 'one-sugarcommerce-tables' ) +
			printed( 'two-sugarcommerce-tables' ) +
			'</body></html>',
		{
			runScripts: 'dangerously',
			beforeParse( window ) {
				window.sent = sent;
			},
		}
	);

	return { window: dom.window, sent };
}

afterEach( () => dom && dom.window.close() );

describe( 'lib/config.js', () => {
	test( "a script's entry is the one its element's id names", () => {
		const element = document.createElement( 'script' );
		const registry = { 'one-sugarcommerce-tables-toggle': { build: 'x' } };

		element.id = 'one-sugarcommerce-tables-toggle-js';
		expect( resolve( element, registry ) ).toBe(
			registry[ 'one-sugarcommerce-tables-toggle' ]
		);
		expect( resolve( null, registry ) ).toBeUndefined();
		expect( resolve( element, undefined ) ).toBeUndefined();
	} );

	test( "a build's screen is the .wrap naming it, and it begins once", () => {
		document.body.innerHTML =
			'<div class="wrap" data-sugarcommerce-tables="one"></div><div class="wrap" data-sugarcommerce-tables="two"></div>';

		const entry = { build: 'two' };

		expect( rootOf( document, 'two' ) ).toBe( document.body.lastChild );
		expect( rootOf( document, 'three' ) ).toBeNull();
		expect( rootOf( document, '' ) ).toBeNull();

		expect( claim( document, entry ).root ).toBe( document.body.lastChild );
		expect( entry.bound ).toBe( true );
		expect( claim( document, entry ) ).toBeNull();

		// Not without its own screen, nor without settings.
		const elsewhere = { build: 'three' };

		expect( claim( document, elsewhere ) ).toBeNull();
		expect( elsewhere.bound ).toBeUndefined();
		expect( claim( document, undefined ) ).toBeNull();
	} );
} );

test( "each build's scripts begin on its own screen only, its editor in its own entry", () => {
	const { window } = load( screen( 'one-sugarcommerce-tables', false ) );
	const registry = window.SugarCommerceTables;

	for ( const entry of ENTRIES ) {
		expect( registry[ `one-sugarcommerce-tables-${ entry }` ].bound ).toBe(
			true
		);
		expect(
			registry[ `two-sugarcommerce-tables-${ entry }` ].bound
		).toBeUndefined();
	}

	const editor = registry[ 'one-sugarcommerce-tables-inline-edit' ].editor;

	for ( const method of [ 'edit', 'save', 'revert', 'setBulk' ] ) {
		expect( typeof editor[ method ] ).toBe( 'function' );
	}

	expect(
		registry[ 'two-sugarcommerce-tables-inline-edit' ].editor
	).not.toBe( editor );
	expect( window.sugarCommerceInlineEditor ).toBeUndefined();
	expect(
		Object.keys( window ).filter( ( name ) =>
			/^sugarCommerce/.test( name )
		)
	).toEqual( [] );
} );

test( "a toggle is pressed by its own build's script, through its own build's route", () => {
	const { window, sent } = load(
		screen( 'one-sugarcommerce-tables', false ) +
			screen( 'two-sugarcommerce-tables', false )
	);
	const [ one, two ] = window.document.querySelectorAll(
		'button.sugarcommerce-toggle'
	);

	one.click();
	expect( sent ).toEqual( [ '/one-sugarcommerce-tables/7' ] );

	two.click();
	expect( sent ).toEqual( [
		'/one-sugarcommerce-tables/7',
		'/two-sugarcommerce-tables/7',
	] );
} );

test( "a deferred column reloads its own build's list, through its own route", () => {
	const { window, sent } = load(
		screen( 'one-sugarcommerce-tables', true ) +
			screen( 'two-sugarcommerce-tables', false )
	);
	const box = window.document.querySelector( '.hide-column-tog' );

	// Ticked by the click, as somebody ticks it.
	box.click();

	// Only build one's column was marks; build two's was drawn.
	expect( sent ).toEqual( [
		'one-sugarcommerce-tables/v1/demo/hidden-columns',
	] );
} );

test( "what somebody was told once is remembered under the toggle's build", () => {
	const kept = new Map();
	const root = document.createElement( 'div' );

	root.innerHTML =
		'<button type="button" class="sugarcommerce-toggle" data-toggle="demo:consent" data-id="7" aria-pressed="false">off</button><span class="sugarcommerce-toggle__message" hidden></span>';
	document.body.replaceChildren( root );

	bindToggles( root, {
		settings: {
			build: 'one-sugarcommerce-tables',
			columns: {
				'demo:consent': {
					confirmOn: 'Only if they agreed.',
					icons: { on: '', off: '' },
					request: { url: '/one/{id}', key: 'on' },
				},
			},
		},
		storage: () => ( {
			getItem: ( key ) => kept.get( key ) || null,
			setItem: ( key, value ) => kept.set( key, value ),
		} ),
		apiFetch: () => new Promise( () => {} ),
		speak: () => {},
	} );

	const button = root.querySelector( 'button' );

	button.click();
	button.click();

	expect( [ ...kept.keys() ] ).toEqual( [
		'sugarcommerce-toggle-noted:one-sugarcommerce-tables:demo:consent:on',
	] );
} );
