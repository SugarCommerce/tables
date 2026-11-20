<?php
/**
 * Where an action sends the browser back to.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Support\ViewState;
use PHPUnit\Framework\TestCase;

/**
 * Back to the view the action was taken from.
 *
 * A bulk action taken from Pending came back to `status=-1` -- All -- because
 * the bulk editor, hidden in the same GET form, has a `status` select whose
 * "No Change" is -1 and comes after the hidden input carrying the view. The
 * page and the sort were never carried at all.
 */
final class RedirectViewTest extends TestCase {

	/**
	 * A clean request between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];
	}

	/**
	 * A table with a status editor, a country filter and a whitelisted arg.
	 *
	 * @return void
	 */
	private function register(): void {
		Tables::register(
			'demo',
			[
				'menu_slug'    => 'demo',
				'labels'       => [ 'singular' => 'order', 'plural' => 'orders' ],
				'columns'      => [ 'name' => 'Name' ],
				'filters'      => [
					'country' => [ 'label' => 'All', 'options' => [ 'GB' => 'UK', 'FR' => 'France' ] ],
				],
				'query_args'   => [ 'customer_id' => 'absint' ],
				'bulk_edit'    => [
					'status' => [ 'label' => 'Status', 'options' => [ 'complete' => 'Complete' ] ],
				],
				'bulk_actions' => [
					'complete' => [ 'label' => 'Mark complete' ],
				],
			]
		);
	}

	/**
	 * Run what admin_init runs, catching the redirect.
	 *
	 * @return string Where it went.
	 */
	private function dispatch(): string {
		try {
			Tables::process_early_actions();
		} catch ( \RT_Redirected $redirect ) {
			return $redirect->location;
		}

		$this->fail( 'There was no redirect.' );
	}

	/**
	 * The query arguments of a URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return array<string, string>
	 */
	private function query( string $url ): array {
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );

		return $query;
	}

	/**
	 * A bulk action from the form, with the bulk editor's -1 posted over
	 * the view's status, as the browser posted it.
	 *
	 * @return void
	 */
	private function bulk_from_pending(): void {
		$_GET = [
			'page'     => 'demo',
			'status'   => '-1',
			'country'  => 'GB',
			'action'   => 'complete',
			'orders'   => [ '5' ],
			'_wpnonce' => 'nonce',
		];

		$_REQUEST = $_GET;
	}

	/**
	 * The status view, the filters, the search, the sort and the page all
	 * survive a bulk action, read from the page it was taken on.
	 */
	public function test_a_bulk_action_comes_back_to_its_view(): void {
		$this->register();
		$this->bulk_from_pending();

		$GLOBALS['rt_referer'] = 'https://example.test/wp-admin/admin.php?page=demo&status=pending&country=GB&s=kira&customer_id=12&orderby=total&order=asc&paged=3&_notice=0123456789abcdef';

		$query = $this->query( $this->dispatch() );

		$this->assertSame( 'pending', $query['status'] );
		$this->assertSame( 'GB', $query['country'] );
		$this->assertSame( 'kira', $query['s'] );
		$this->assertSame( '12', $query['customer_id'] );
		$this->assertSame( 'total', $query['orderby'] );
		$this->assertSame( 'asc', $query['order'] );
		$this->assertSame( '3', $query['paged'] );

		// A token from an earlier notice is not part of the view.
		$this->assertNotSame( '0123456789abcdef', $query['_notice'] ?? '' );
	}

	/**
	 * With no referer the request is read -- and its -1 is not a view.
	 */
	public function test_without_a_referer_the_editors_minus_one_is_dropped(): void {
		$this->register();
		$this->bulk_from_pending();

		$query = $this->query( $this->dispatch() );

		$this->assertArrayNotHasKey( 'status', $query );
		$this->assertSame( 'GB', $query['country'] );
	}

	/**
	 * A referer from another screen says nothing about this list.
	 */
	public function test_a_referer_from_another_page_is_ignored(): void {
		$this->register();
		$this->bulk_from_pending();

		$GLOBALS['rt_referer'] = 'https://example.test/wp-admin/admin.php?page=elsewhere&status=refunded';

		$query = $this->query( $this->dispatch() );

		$this->assertArrayNotHasKey( 'status', $query );
		$this->assertSame( 'demo', $query['page'] );
	}

	/**
	 * Whatever a referer carries, only a view's keys come back, sanitised.
	 */
	public function test_only_the_views_keys_are_read(): void {
		$this->register();

		$view = ViewState::from(
			Tables::get_table( 'demo' ),
			[
				'status'  => 'Pending<script>',
				'number'  => '9999',
				'paged'   => '1',
				'country' => '<b>GB</b>',
			]
		);

		$this->assertSame(
			[
				'status'  => 'pendingscript',
				'country' => 'GB',
			],
			$view
		);
	}

	/**
	 * The Filter button keeps the view and drops the page: new filters
	 * are new rows, and page three of the old ones may not exist.
	 */
	public function test_the_filter_redirect_keeps_the_view_but_not_the_page(): void {
		$this->register();

		$_GET = [
			'page'          => 'demo',
			'status'        => 'pending',
			'country'       => 'FR',
			'customer_id'   => '12',
			'paged'         => '4',
			'filter_action' => 'Filter',
		];

		$query = $this->query( $this->dispatch() );

		$this->assertSame( 'pending', $query['status'] );
		$this->assertSame( 'FR', $query['country'] );
		$this->assertSame( '12', $query['customer_id'] );
		$this->assertArrayNotHasKey( 'paged', $query );
	}

	/**
	 * The editors are drawn after the list's form and not inside it, so
	 * their fields do not ride along with the Filter button, the search or
	 * a bulk action.
	 */
	public function test_the_editors_are_outside_the_form(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Traits/Tables/PageRenderer.php' );

		$form_ends = strpos( $source, '</form>' );
		$editors   = strpos( $source, 'InlineEdit::render_inline_rows(' );

		$this->assertNotFalse( $form_ends );
		$this->assertNotFalse( $editors );
		$this->assertGreaterThan( $form_ends, $editors );
	}
}
