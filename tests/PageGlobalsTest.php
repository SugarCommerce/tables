<?php
/**
 * The page globals the scripts set.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SugarCommerce\Tables\Support\ScriptConfig;

/**
 * One page global, the registry keyed by handle, and nothing two copies
 * would both set.
 *
 * Two plugins each bundling a copy load each script twice, under two
 * handles. A fixed name -- `window.sugarCommerceInlineEditor`, or the
 * `sugarCommerceReorder`, `sugarCommerceInlineEdit`, `sugarCommerceToggles`
 * and `sugarCommerceDeferredColumns` objects `wp_localize_script()` printed
 * -- is set by both, and the last to print answers for both plugins.
 */
final class PageGlobalsTest extends TestCase {

	/**
	 * Every script the PHP loads.
	 */
	private const ENTRIES = [ 'reorder', 'inline-edit', 'toggle', 'deferred-columns' ];

	/**
	 * Every script, as written and as built.
	 *
	 * @return array<string, string> Path relative to assets/ => source.
	 */
	private static function scripts(): array {
		$root    = dirname( __DIR__ ) . '/assets';
		$scripts = [];

		foreach ( [ 'js', 'build' ] as $dir ) {
			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );

			foreach ( $files as $file ) {
				if ( 'js' === $file->getExtension() ) {
					$scripts[ substr( $file->getPathname(), strlen( $root ) + 1 ) ] = (string) file_get_contents( $file->getPathname() );
				}
			}
		}

		ksort( $scripts );

		return $scripts;
	}

	/**
	 * Nothing but the registry is set on window, and nothing reads one of
	 * the old fixed names.
	 *
	 * @return void
	 */
	public function test_the_scripts_set_no_global_but_the_registry(): void {
		$wrong = [];

		foreach ( self::scripts() as $path => $source ) {
			preg_match_all( '/window\.([A-Za-z_$][\w$]*)\s*=(?!=)/', $source, $matches );

			foreach ( array_unique( $matches[1] ) as $name ) {
				if ( ScriptConfig::REGISTRY !== $name ) {
					$wrong[] = $path . ' sets window.' . $name;
				}
			}

			if ( 1 === preg_match( '/\bsugarCommerce[A-Z]\w*/', $source, $match ) ) {
				$wrong[] = $path . ' names ' . $match[0];
			}
		}

		$this->assertNotEmpty( self::scripts() );
		$this->assertSame( [], $wrong );
	}

	/**
	 * Each built script finds its own entry from its element, and its own
	 * build's screen from the attribute the `.wrap` carries.
	 *
	 * @return void
	 */
	public function test_each_built_script_is_its_own_builds(): void {
		$scripts = self::scripts();

		foreach ( self::ENTRIES as $entry ) {
			$built = $scripts[ 'build/' . $entry . '.js' ] ?? '';

			$this->assertStringContainsString( 'document.currentScript', $built, $entry );
			$this->assertStringContainsString( 'window.' . ScriptConfig::REGISTRY, $built, $entry );
			$this->assertStringContainsString( ScriptConfig::ATTRIBUTE, $built, $entry );
		}
	}

	/**
	 * No `wp_localize_script()`: its object name is a page global every copy
	 * would set.
	 *
	 * @return void
	 */
	public function test_nothing_is_localized_under_a_fixed_name(): void {
		$src   = dirname( __DIR__ ) . '/src';
		$found = [];
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );

		foreach ( $files as $file ) {
			if ( 'php' === $file->getExtension() && str_contains( (string) file_get_contents( $file->getPathname() ), 'wp_localize_script(' ) ) {
				$found[] = substr( $file->getPathname(), strlen( $src ) + 1 );
			}
		}

		$this->assertSame( [], $found );
	}

	/**
	 * The settings are filed under the script's handle, with the build
	 * they belong to; the screen names the same build.
	 *
	 * @return void
	 */
	public function test_the_settings_and_the_screen_name_the_build(): void {
		$this->assertSame(
			'window.SugarCommerceTables=window.SugarCommerceTables||{};window.SugarCommerceTables["sugarcommerce-tables-toggle"]={"build":"sugarcommerce-tables","columns":[]};',
			ScriptConfig::inline( 'sugarcommerce-tables-toggle', [ 'columns' => [] ] )
		);

		$this->assertSame( 'data-sugarcommerce-tables="sugarcommerce-tables"', ScriptConfig::marker() );

		$page = (string) file_get_contents( dirname( __DIR__ ) . '/src/Traits/Tables/PageRenderer.php' );

		$this->assertStringContainsString( '<div class="wrap" <?php echo ScriptConfig::marker();', $page );
	}
}
