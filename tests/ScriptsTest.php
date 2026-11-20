<?php
/**
 * Built script tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use PHPUnit\Framework\TestCase;
use SugarCommerce\Plugin\Assets;
use SugarCommerce\Tables\HiddenColumns;

/**
 * The scripts are built from assets/js into assets/build, and the build is
 * committed: Composer and Strauss copy a package as it is, with nothing run
 * at install. These keep the build and the PHP that loads it in step.
 */
final class ScriptsTest extends TestCase {

	/**
	 * Every entry the PHP loads.
	 */
	private const ENTRIES = [ 'reorder', 'inline-edit', 'toggle', 'deferred-columns' ];

	/**
	 * A file of the package, for the asset helper to find it from.
	 */
	private const FILE = __DIR__ . '/../src/Editing/Reorder.php';

	/**
	 * A clean slate.
	 */
	protected function setUp(): void {
		rt_reset_globals();
	}

	/**
	 * Every entry is built, with the manifest @wordpress/scripts writes
	 * beside it, and none of them needs jQuery or its UI any more.
	 */
	public function test_every_built_entry_has_its_manifest(): void {
		foreach ( self::ENTRIES as $entry ) {
			$manifest = Assets::built_manifest( self::FILE, $entry );

			$this->assertNotNull( $manifest, $entry );
			$this->assertFileExists( dirname( __DIR__ ) . '/assets/build/' . $entry . '.js', $entry );
			$this->assertSame( [], preg_grep( '/^jquery/', $manifest['dependencies'] ), $entry );
			$this->assertContains( 'wp-dom-ready', $manifest['dependencies'], $entry );
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{20}$/', (string) $manifest['version'], $entry );
		}

		$this->assertNull( Assets::built_manifest( self::FILE, 'nothing' ) );
	}

	/**
	 * Each entry that speaks to a screen reader imports wp.a11y, so the
	 * build names it; nothing names it by hand.
	 */
	public function test_the_spoken_scripts_bring_wp_a11y(): void {
		foreach ( [ 'reorder', 'inline-edit', 'toggle' ] as $entry ) {
			$this->assertContains( 'wp-a11y', Assets::built_manifest( self::FILE, $entry )['dependencies'], $entry );
		}
	}

	/**
	 * The script that reloads a list for a deferred column is loaded from
	 * the build, under its handle, in the footer.
	 */
	public function test_the_deferred_column_script_is_the_build(): void {
		HiddenColumns::enqueue(
			[
				'id'      => 'demo',
				'columns' => [
					'last_order' => [
						'label'    => 'Last order',
						'deferred' => true,
					],
				],
			]
		);

		[ $src, $deps, $ver, $args ] = $GLOBALS['rt_scripts']['sugarcommerce-tables-deferred-columns'] ?? [ '', [], '', [] ];

		$this->assertStringEndsWith( '/assets/build/deferred-columns.js', (string) $src );
		$this->assertSame( [ 'wp-dom-ready' ], $deps );
		$this->assertSame( Assets::built_manifest( self::FILE, 'deferred-columns' )['version'], $ver );
		$this->assertTrue( $args['in_footer'] ?? $args );
	}

	/**
	 * The sources ship in assets/js beside the build, and nothing loads one
	 * of them directly: only the build is enqueued.
	 */
	public function test_nothing_loads_the_sources(): void {
		foreach ( self::ENTRIES as $entry ) {
			$this->assertFileExists( dirname( __DIR__ ) . '/assets/js/' . $entry . '.js' );
		}

		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( __DIR__ ) . '/src', \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $files as $file ) {
			$this->assertStringNotContainsString( "'js/", (string) file_get_contents( $file->getPathname() ), $file->getFilename() );
		}
	}
}
