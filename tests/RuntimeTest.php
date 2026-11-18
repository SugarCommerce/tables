<?php
/**
 * Runtime name derivation tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use SugarCommerce\Tables\Reorder;
use SugarCommerce\Tables\Seen\Bars;
use SugarCommerce\Tables\Seen\Rows;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\ToggleRoute;
use SugarCommerce\Tables\Utils\Runtime;

/**
 * Every handle, route, admin-ajax action and cache group comes from the
 * namespace this copy is compiled under. A plain install is
 * `sugarcommerce-tables`; a Strauss build under `MyShop\` is
 * `myshop-sugarcommerce-tables`, so two plugins never answer each other's
 * requests or load each other's script.
 */
final class RuntimeTest extends TestCase {

	/**
	 * Unprefixed, the library's own id.
	 */
	public function test_unprefixed(): void {
		$this->assertSame( 'sugarcommerce-tables', Runtime::prefix() );
		$this->assertSame( 'sugarcommerce-tables', Runtime::prefix_for( 'SugarCommerce\Tables\Utils' ) );
		$this->assertSame( 'sugarcommerce-tables', Runtime::prefix_for( '' ) );
	}

	/**
	 * Prefixed, the plugin's root namespace first, slugged.
	 */
	public function test_prefixed(): void {
		$this->assertSame( 'myshop-sugarcommerce-tables', Runtime::prefix_for( 'MyShop\SugarCommerce\Tables\Utils' ) );
		$this->assertSame( 'my-shop-sugarcommerce-tables', Runtime::prefix_for( 'My_Shop\SugarCommerce\Tables\Utils' ) );
	}

	/**
	 * What each accessor makes of the prefix, and where they are used.
	 */
	public function test_accessors(): void {
		$this->assertSame( 'sugarcommerce-tables/v1', Runtime::rest_namespace() );
		$this->assertSame( 'sugarcommerce-tables', Runtime::handle() );
		$this->assertSame( 'sugarcommerce-tables-toggle', Runtime::handle( 'toggle' ) );
		$this->assertSame( 'sugarcommerce_tables', Runtime::key() );
		$this->assertSame( 'sugarcommerce_tables_quick_edit', Runtime::key( 'quick_edit' ) );

		$this->assertSame( 'sugarcommerce-tables/v1/shop_orders/toggle', ToggleRoute::path( 'shop_orders' ) );
		$this->assertSame( 'sugarcommerce-tables-reorder-shop_orders', Reorder::action( 'shop_orders' ) );
		$this->assertSame( 'sugarcommerce-tables-highlights', Bars::handle() );
		$this->assertSame( 'sugarcommerce-tables-seen', Rows::cache_group() );
	}

	/**
	 * Quick Edit posts to an action named for this copy, and the script is
	 * told which: one shared action would be answered by whichever bundled
	 * copy hooked it first, against its own tables.
	 */
	public function test_quick_edit_posts_to_this_copys_action(): void {
		rt_reset_globals();

		foreach ( [ 'initialized', 'assets_enqueued' ] as $flag ) {
			( new ReflectionProperty( Tables::class, $flag ) )->setValue( null, false );
		}

		Tables::init();

		$this->assertArrayHasKey( 'wp_ajax_sugarcommerce_tables_quick_edit', $GLOBALS['rt_actions'] ?? [] );

		( new ReflectionMethod( Tables::class, 'do_enqueue_assets' ) )->invoke( null, [ 'quick_edit' => [ 'status' => [ 'label' => 'Status' ] ] ] );

		$this->assertSame( 'sugarcommerce_tables_quick_edit', $GLOBALS['rt_localized']['sugarcommerce-tables-inline-edit'][1]['action'] ?? null );
		$this->assertStringContainsString(
			"'&action=' + encodeURIComponent( settings.action || '' )",
			(string) file_get_contents( dirname( __DIR__ ) . '/assets/js/inline-edit.js' )
		);
	}

	/**
	 * A plugin of the family prefixes with a namespace that also starts
	 * "SugarCommerce", and its build is still its own; a plugin outside the
	 * family keeps the name its first segment always gave it.
	 */
	public function test_a_family_plugin_build_is_not_taken_for_the_library(): void {
		$this->assertSame(
			'sugarcommerce-playground-vendor-sugarcommerce-tables',
			Runtime::prefix_for( 'SugarCommerce\\Playground\\Vendor\\SugarCommerce\\Tables\\Utils' )
		);
		$this->assertNotSame(
			Runtime::prefix_for( 'SugarCommerce\\Playground\\Vendor\\SugarCommerce\\Tables\\Utils' ),
			Runtime::prefix_for( 'SugarCommerce\\Members\\Vendor\\SugarCommerce\\Tables\\Utils' ),
			'Two family plugins share a name.'
		);
		$this->assertSame( 'sugarcart-sugarcommerce-tables', Runtime::prefix_for( 'SugarCart\\Vendor\\SugarCommerce\\Tables\\Utils' ) );
		$this->assertSame( 'sugarcommerce-tables', Runtime::prefix_for( '\\SugarCommerce\\Tables\\Utils\\' ) );
	}
}

