<?php
/**
 * Menu count tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Seen\MenuCount;

/**
 * A count beside the list in the menu: core's bubble, said aloud, and gone
 * on the list itself.
 */
final class MenuCountTest extends SeenTestCase {

	/**
	 * Put the menu back between tests.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['menu'], $GLOBALS['submenu'] );
	}

	/**
	 * A fresh menu: the parent, Comments, and two lists under the parent.
	 *
	 * @return void
	 */
	private function menu(): void {
		$GLOBALS['menu']    = [
			[ 'Comments', 'edit_posts', 'edit-comments.php' ],
			[ 'Shop', 'manage_options', 'shop' ],
		];
		$GLOBALS['submenu'] = [
			'shop' => [
				[ 'Products', 'manage_options', 'shop-products' ],
				[ 'Orders', 'manage_options', 'shop-orders' ],
			],
		];
	}

	/**
	 * The words read aloud, in the table's own.
	 *
	 * @return array<string, mixed>
	 */
	private function counted(): array {
		return [
			'menu_count' => [
				'text' => static fn( int $count, string $number ): string => sprintf( 1 === $count ? '%s new order' : '%s new orders', $number ),
			],
		];
	}

	/**
	 * The bubble is core's, says the number aloud, and stops at 99+.
	 */
	public function test_the_bubble(): void {
		$this->orders( $this->counted() );

		$this->assertSame( '', MenuCount::bubble( 'shop_orders', 0 ) );
		$this->assertSame( '', MenuCount::bubble( 'shop_orders', -3 ) );

		$this->assertSame(
			'<span class="awaiting-mod count-1"><span class="pending-count" aria-hidden="true">1</span><span class="screen-reader-text">1 new order</span></span>',
			MenuCount::bubble( 'shop_orders', 1 )
		);

		$this->assertStringContainsString( 'aria-hidden="true">99</span>', MenuCount::bubble( 'shop_orders', 99 ) );

		$many = MenuCount::bubble( 'shop_orders', 1234 );

		$this->assertStringContainsString( 'aria-hidden="true">99+</span>', $many );
		$this->assertStringContainsString( '<span class="screen-reader-text">1,234 new orders</span>', $many );

		// Without words of the table's own.
		$this->orders( [ 'menu_count' => true ] );
		$this->assertStringContainsString( '2 new items', MenuCount::bubble( 'shop_orders', 2 ) );
	}

	/**
	 * Beside the parent and beside the list, and gone on the list itself.
	 */
	public function test_the_menu_carries_the_count(): void {
		$this->orders( $this->counted() );

		// Nothing new: nothing drawn.
		$this->menu();
		MenuCount::badge();

		$this->assertSame( 'Shop', $GLOBALS['menu'][1][0] );
		$this->assertSame( 'Orders', $GLOBALS['submenu']['shop'][1][0] );

		$this->order();
		$this->order();

		$this->menu();
		MenuCount::badge();

		$this->assertSame( 'Shop ' . MenuCount::bubble( 'shop_orders', 2 ), $GLOBALS['menu'][1][0] );
		$this->assertSame( 'Orders ' . MenuCount::bubble( 'shop_orders', 2 ), $GLOBALS['submenu']['shop'][1][0] );
		$this->assertSame( 'Comments', $GLOBALS['menu'][0][0] );
		$this->assertSame( 'Products', $GLOBALS['submenu']['shop'][0][0] );

		// On the list the rows say it, and the bubble does not.
		$_GET['page'] = 'shop-orders';

		$this->menu();
		MenuCount::badge();

		$this->assertSame( 'Shop', $GLOBALS['menu'][1][0] );
		$this->assertSame( 'Orders', $GLOBALS['submenu']['shop'][1][0] );

		// Any other screen still has it.
		$_GET['page'] = 'shop-products';

		$this->menu();
		MenuCount::badge();

		$this->assertStringContainsString( 'awaiting-mod count-2', $GLOBALS['menu'][1][0] );

		// Somebody who may not see the list has none.
		$GLOBALS['rt_user_caps'] = [ 1 => [ 'read' ] ];

		$this->menu();
		MenuCount::badge();

		$this->assertSame( 'Shop', $GLOBALS['menu'][1][0] );
	}

	/**
	 * Only beside the list, when the parent is not to have it.
	 */
	public function test_the_parent_can_go_without(): void {
		$this->orders( [ 'menu_count' => [ 'parent' => false ] ] );
		$this->order();

		$this->menu();
		MenuCount::badge();

		$this->assertSame( 'Shop', $GLOBALS['menu'][1][0] );
		$this->assertStringContainsString( 'awaiting-mod count-1', $GLOBALS['submenu']['shop'][1][0] );
	}

	/**
	 * A list at the top level carries it on its own item.
	 */
	public function test_a_top_level_list(): void {
		$this->orders( $this->counted(), [ 'parent_slug' => '' ] );
		$this->order();

		$GLOBALS['menu']    = [ [ 'Orders', 'manage_options', 'shop-orders' ] ];
		$GLOBALS['submenu'] = [];

		MenuCount::badge();

		$this->assertStringContainsString( 'Orders <span class="awaiting-mod count-1">', $GLOBALS['menu'][0][0] );
	}

	/**
	 * No menu count, no bubble and no query.
	 */
	public function test_no_count_unless_asked(): void {
		$this->orders();
		$this->order();

		$this->menu();
		MenuCount::badge();

		$this->assertSame( 'Shop', $GLOBALS['menu'][1][0] );
		$this->assertSame( 0, $this->asked );
		$this->assertArrayNotHasKey( 'admin_menu', $GLOBALS['rt_actions'] ?? [] );
	}
}
