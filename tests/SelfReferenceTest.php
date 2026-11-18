<?php
/**
 * Self-reference tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SelfReferences.php';

/**
 * Does every `self::` in this library point at something that exists?
 *
 * PHP will not tell you, `php -l` will not tell you, and a suite that never
 * reaches the line will not either — it is a fatal at the moment of use. The
 * checker is the one in sugarcommerce/fields' suite, copied, since a
 * Composer install leaves that suite behind.
 */
final class SelfReferenceTest extends TestCase {

	/**
	 * Every `self::` and `static::` reference in the library resolves.
	 */
	public function test_every_self_reference_resolves(): void {
		$broken = SelfReferences::broken( dirname( __DIR__ ) . '/src' );

		$this->assertSame( [], $broken, "Undeclared:\n  " . implode( "\n  ", $broken ) );
	}
}
