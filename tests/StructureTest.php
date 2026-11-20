<?php
/**
 * How big a source file may grow, and where it lives.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * No class or trait in src/ past 1,000 lines; every file where PSR-4 looks.
 *
 * A class that does several jobs becomes a slim public class and a trait
 * per job under src/Traits/<Class>/ well before that. Files past 500 lines
 * are listed on STDERR as the ones to look at next; they do not fail.
 */
final class StructureTest extends TestCase {

	/**
	 * Never longer than this.
	 *
	 * @var int
	 */
	private const int LIMIT = 1000;

	/**
	 * Longer than this is worth a look.
	 *
	 * @var int
	 */
	private const int WARN = 500;

	/**
	 * The namespace src/ maps to.
	 *
	 * @var string
	 */
	private const string ROOT = 'SugarCommerce\\Tables';

	/**
	 * Every PHP file under src/, with its line count.
	 *
	 * @return array<string, int> Path relative to src/ => lines.
	 */
	private static function sizes(): array {
		$src   = dirname( __DIR__ ) . '/src';
		$sizes = [];
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );

		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$path           = substr( $file->getPathname(), strlen( $src ) + 1 );
			$sizes[ $path ] = count( (array) file( $file->getPathname() ) );
		}

		ksort( $sizes );

		return $sizes;
	}

	/**
	 * Nothing past the limit; the long ones named.
	 *
	 * @return void
	 */
	public function test_no_class_or_trait_is_over_a_thousand_lines(): void {
		$sizes = self::sizes();
		$over  = array_filter( $sizes, static fn( int $lines ): bool => $lines > self::LIMIT );
		$long  = array_filter( $sizes, static fn( int $lines ): bool => $lines > self::WARN && $lines <= self::LIMIT );

		foreach ( $long as $path => $lines ) {
			fwrite( STDERR, sprintf( "\nStructure: src/%s is %d lines (over %d): split it by job before it grows.", $path, $lines, self::WARN ) );
		}

		$this->assertNotEmpty( $sizes, 'No source files were found.' );
		$this->assertSame( [], $over, 'Over ' . self::LIMIT . ' lines: split into a slim class and a trait per job.' );
	}

	/**
	 * Each file declares strict types and the namespace its folder names.
	 *
	 * @return void
	 */
	public function test_every_file_is_strict_and_where_its_namespace_says(): void {
		$wrong = [];

		foreach ( array_keys( self::sizes() ) as $path ) {
			$code   = (string) file_get_contents( dirname( __DIR__ ) . '/src/' . $path );
			$folder = dirname( $path );
			$want   = self::ROOT . ( '.' === $folder ? '' : '\\' . str_replace( '/', '\\', $folder ) );

			if ( 1 !== preg_match( '/^declare\(\s*strict_types\s*=\s*1\s*\);/m', $code ) ) {
				$wrong[] = $path . ': no strict_types';
			}

			if ( ! str_contains( $code, "\nnamespace " . $want . ";\n" ) ) {
				$wrong[] = $path . ': not in namespace ' . $want;
			}
		}

		$this->assertSame( [], $wrong );
	}

	/**
	 * src/ is PHP and nothing else: scripts and styles live under assets/.
	 *
	 * @return void
	 */
	public function test_src_holds_php_only(): void {
		$src   = dirname( __DIR__ ) . '/src';
		$other = [];
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );

		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				$other[] = substr( $file->getPathname(), strlen( $src ) + 1 );
			}
		}

		sort( $other );

		$this->assertSame( [], $other, 'Not PHP: scripts go in assets/js, styles in assets/css.' );
	}
}
