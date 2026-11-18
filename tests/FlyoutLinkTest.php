<?php
/**
 * Flyout link tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\FlyoutLink;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Table;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/flyout-registry.php';

/**
 * A row's Edit link and its linked title are made by the flyout library's
 * class, never its global function.
 *
 * Under Strauss the function is not prefixed and is guarded, so it belongs to
 * whichever plugin loaded first -- or does not exist at all, which is how a
 * plugin came to draw every Edit link itself. The class is prefixed with the
 * rest of the bundle, so it is always the right copy.
 */
final class FlyoutLinkTest extends TestCase {

	/**
	 * A clean request between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET     = [];
		$_REQUEST = [];
	}

	/**
	 * A table with an edit panel and an Edit row action.
	 *
	 * @param array<string, mixed> $action The Edit action.
	 *
	 * @return Table
	 */
	private function table( array $action = [ 'label' => 'Edit', 'flyout' => true ] ): Table {
		Tables::register(
			'demo',
			[
				'labels'      => [ 'singular' => 'item', 'plural' => 'items' ],
				'flyouts'     => [ 'edit' => 'shop_edit_item' ],
				'columns'     => [
					'name' => [
						'label' => 'Name',
						'title' => static fn( object $row ): string => $row->name,
						'link'  => 'edit_flyout',
					],
				],
				'row_actions' => [ 'edit' => $action ],
				'callbacks'   => [ 'get_items' => static fn() => [] ],
			]
		);

		return new Table( 'demo', Tables::get_table( 'demo' ) );
	}

	/**
	 * The row actions under the name.
	 *
	 * @param Table $table The table.
	 *
	 * @return string
	 */
	private function actions( Table $table ): string {
		$method = new \ReflectionMethod( $table, 'handle_row_actions' );

		return (string) $method->invoke( $table, (object) [ 'id' => 7, 'name' => 'Kit' ], 'name', 'name' );
	}

	/**
	 * The Edit action opens the table's edit panel on the row.
	 */
	public function test_the_edit_action_opens_the_panel(): void {
		$GLOBALS['rt_flyouts'] = [ 'shop_edit_item' => true ];

		$html = $this->actions( $this->table() );

		$this->assertStringContainsString( 'data-flyout-manager="shop" data-flyout="edit_item" data-id="7">Edit</a>', $html );
	}

	/**
	 * A panel can be named outright instead of `true`.
	 */
	public function test_an_action_can_name_its_panel(): void {
		$GLOBALS['rt_flyouts'] = [ 'shop_view_item' => true ];

		$html = $this->actions( $this->table( [ 'label' => 'View', 'flyout' => 'shop_view_item' ] ) );

		$this->assertStringContainsString( 'data-flyout="view_item" data-id="7">View</a>', $html );
	}

	/**
	 * No panel, no link: a link to `#` looked like an action and did nothing.
	 */
	public function test_no_panel_draws_no_link(): void {
		$html = $this->actions( $this->table() );

		$this->assertStringNotContainsString( 'href="#"', $html );
		$this->assertStringNotContainsString( 'Edit', $html );
	}

	/**
	 * The title links to the panel, carrying core's `row-title`, inside the
	 * <strong>.
	 */
	public function test_the_title_links_to_the_panel(): void {
		$GLOBALS['rt_flyouts'] = [ 'shop_edit_item' => true ];

		$html = $this->table()->column_default( (object) [ 'id' => 7, 'name' => 'Kit' ], 'name' );

		$this->assertSame(
			'<strong><a href="#" class="wp-flyout-trigger row-title" data-flyout-manager="shop" data-flyout="edit_item" data-id="7">Kit</a></strong>',
			$html
		);
	}

	/**
	 * Without the panel the title is still there, unlinked.
	 */
	public function test_the_title_without_a_panel_is_plain(): void {
		$this->assertSame( '<strong>Kit</strong>', $this->table()->column_default( (object) [ 'id' => 7, 'name' => 'Kit' ], 'name' ) );
	}

	/**
	 * The Add button is the flyout library's button, through its class.
	 */
	public function test_the_add_button_is_made_by_the_class(): void {
		$GLOBALS['rt_flyouts'] = [ 'shop_add_item' => true ];

		$this->assertSame(
			'<button class="page-title-action" data-flyout="add_item">Add Item</button>',
			FlyoutLink::button( 'shop_add_item', [ 'text' => 'Add Item', 'class' => 'page-title-action', 'data' => 'dropped' ] )
		);
		$this->assertSame( '', FlyoutLink::button( 'shop_missing', [ 'text' => 'Add' ] ) );
		$this->assertSame( '', FlyoutLink::button( 'shop_add_item', [ 'text' => '' ] ) );
	}

	/**
	 * A sync button needs wp-inline-sync, which these tests do not load.
	 */
	public function test_no_sync_library_draws_no_sync_button(): void {
		$this->assertSame( '', FlyoutLink::sync_button( 'orders' ) );
	}
}
