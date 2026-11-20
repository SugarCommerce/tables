/**
 * What the browser tests' pages are built from: WordPress's own admin
 * stylesheets -- from the WordPress this package sits in, or `WP_CORE_DIR`
 * -- and this package's.
 */
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const pkg = resolve(
	dirname( fileURLToPath( import.meta.url ) ),
	'../..'
);

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

export const core = [
	'wp-includes/css/dashicons.css',
	'wp-includes/css/buttons.css',
	'wp-admin/css/common.css',
	'wp-admin/css/forms.css',
	'wp-admin/css/list-tables.css',
	'wp-admin/css/edit.css',
].map( ( file ) => readFileSync( join( wordpress(), file ), 'utf8' ) );

export const ours = readFileSync(
	join( pkg, 'assets/css/admin-tables.css' ),
	'utf8'
);
