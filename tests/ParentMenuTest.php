<?php
/**
 * Parent menu tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\ParentMenu;
use PHPUnit\Framework\TestCase;

/**
 * The entry core adds for the parent itself goes, and the first table is the
 * landing page.
 */
final class ParentMenuTest extends TestCase {

	/**
	 * The parent's own entry is taken out and the rest close up.
	 */
	public function test_the_parents_own_entry_goes(): void {
		ParentMenu::register( 'shop', 'Shop' );

		$GLOBALS['submenu'] = [
			'shop'  => [
				[ 'Shop', 'manage_options', 'shop' ],
				[ 'Orders', 'manage_options', 'shop-orders' ],
				[ 'Products', 'manage_options', 'shop-products' ],
			],
			'other' => [
				[ 'Other', 'manage_options', 'other' ],
			],
		];

		ParentMenu::fix_menus();

		$this->assertSame( [ 'shop-orders', 'shop-products' ], array_column( $GLOBALS['submenu']['shop'], 2 ) );
		$this->assertSame( [ 0, 1 ], array_keys( $GLOBALS['submenu']['shop'] ) );
		$this->assertCount( 1, $GLOBALS['submenu']['other'], 'Another menu was touched.' );

		unset( $GLOBALS['submenu'] );
	}
}
