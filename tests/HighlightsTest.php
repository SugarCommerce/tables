<?php
/**
 * Row highlight tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Seen\Bars;
use SugarCommerce\Tables\Seen\Highlights;
use SugarCommerce\Tables\Table;

/**
 * How a row is lit: the first state it is in, by priority, drawn as a bar
 * and said aloud.
 */
final class HighlightsTest extends SeenTestCase {

	/**
	 * Customers: blocked rows, and an always-allowed list that excuses some.
	 *
	 * @param array<string, mixed> $highlights The table's highlights.
	 *
	 * @return void
	 */
	private function customers( array $highlights = [] ): void {
		Tables::register(
			'shop_customers',
			[
				'menu_slug'  => 'shop-customers',
				'labels'     => [ 'singular' => 'customer', 'plural' => 'customers' ],
				'columns'    => [ 'name' => 'Name' ],
				'row_class'  => static fn( $row ): string => 'vip-' . ( $row->vip ?? 'no' ),
				'seen'       => [
					'label'   => 'New customer',
					'blocked' => [ 'status' => [ 'disabled' ] ],
					'excused' => static fn( object $row ): bool => 'allowed@example.test' === $row->email,
					'count'   => static fn(): int => 0,
					'newest'  => static fn(): int => 0,
				],
				'highlights' => $highlights,
			]
		);
	}

	/**
	 * A customer.
	 *
	 * @param array<string, mixed> $columns Anything about them.
	 *
	 * @return object
	 */
	private function customer( array $columns = [] ): object {
		return (object) array_merge(
			[
				'id'     => 5,
				'name'   => 'Elena',
				'email'  => 'elena@example.test',
				'status' => 'active',
			],
			$columns
		);
	}

	/**
	 * Red before amber before green: the first state the row is in wins.
	 */
	public function test_the_first_state_by_priority_wins(): void {
		$this->customers();

		$this->assertSame( [ 'blocked', 'excused', 'new' ], array_keys( Highlights::states( 'shop_customers' ) ) );

		$new     = $this->customer();
		$blocked = $this->customer( [ 'status' => 'disabled' ] );
		$excused = $this->customer( [ 'status' => 'disabled', 'email' => 'allowed@example.test' ] );
		$seen    = $this->customer( [ 'id' => 0 ] );

		$this->assertSame( 'new', Highlights::highlight( 'shop_customers', $new ) );
		$this->assertSame( 'blocked', Highlights::highlight( 'shop_customers', $blocked ), 'Blocked comes before new.' );
		$this->assertSame( 'excused', Highlights::highlight( 'shop_customers', $excused ) );
		$this->assertSame( '', Highlights::highlight( 'shop_customers', $seen ) );

		// Excused only ever of a row that is blocked.
		$this->assertSame( 'new', Highlights::highlight( 'shop_customers', $this->customer( [ 'email' => 'allowed@example.test' ] ) ) );
	}

	/**
	 * A row's classes: the table's own, then the state's and the bar's.
	 */
	public function test_the_row_carries_its_state(): void {
		$this->customers( [ 'class' => 'shop-row' ] );

		$table = new Table( 'shop_customers', Tables::get_table( 'shop_customers' ) );

		ob_start();
		$table->single_row( $this->customer( [ 'status' => 'disabled' ] ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'class="vip-no shop-row-blocked sugarcommerce-row-lit status-disabled"', $html );

		ob_start();
		$table->single_row( $this->customer( [ 'id' => 0 ] ) );
		$this->assertStringContainsString( 'class="vip-no status-active"', (string) ob_get_clean() );
	}

	/**
	 * What a lit row is, said in its first cell for whoever cannot see it.
	 */
	public function test_the_row_says_what_it_is(): void {
		$this->customers( [ 'states' => [ 'blocked' => [ 'label' => 'Blocked customer' ] ] ] );

		$this->assertSame( ' <span class="screen-reader-text">Blocked customer</span>', Highlights::label( 'shop_customers', $this->customer( [ 'status' => 'disabled' ] ) ) );
		$this->assertSame( ' <span class="screen-reader-text">New customer</span>', Highlights::label( 'shop_customers', $this->customer() ) );
		$this->assertSame( '', Highlights::label( 'shop_customers', $this->customer( [ 'id' => 0 ] ) ) );

		// Drawn by the table, before the row actions.
		$table   = new Table( 'shop_customers', Tables::get_table( 'shop_customers' ) );
		$actions = new \ReflectionMethod( $table, 'handle_row_actions' );

		$this->assertStringStartsWith( ' <span class="screen-reader-text">New customer</span>', (string) $actions->invoke( $table, $this->customer(), 'name', 'name' ) );
		$this->assertSame( '', (string) $actions->invoke( $table, $this->customer(), 'email', 'name' ) );

		// A filter can say the row already says it.
		add_filter( 'sugarcommerce_table_highlight_label_shop_customers', static fn( string $label, string $state ): string => 'new' === $state ? '' : $label );
		$this->assertSame( '', Highlights::label( 'shop_customers', $this->customer() ) );
	}

	/**
	 * A table can draw the words itself, where it wants them.
	 */
	public function test_announce_off_leaves_the_words_to_the_table(): void {
		$this->customers( [ 'announce' => false ] );

		$this->assertSame( '', Highlights::announce( 'shop_customers', $this->customer() ) );
		$this->assertNotSame( '', Highlights::label( 'shop_customers', $this->customer() ) );
	}

	/**
	 * A state with no words of its own still says that its row is new.
	 */
	public function test_a_state_without_words_says_new(): void {
		$this->orders(
			[],
			[
				'highlights' => [
					'states' => [
						'pending' => [
							'color'    => '#dba617',
							'priority' => 40,
							'forced'   => 'double',
							'when'     => static fn( object $row, string $table ): bool => 'pending' === $row->status && \SugarCommerce\Tables\Seen\Seen::is_new( $table, $row ),
						],
					],
				],
			]
		);

		$row = $this->order( [ 'status' => 'pending' ] );

		$this->assertSame( 'pending', Highlights::highlight( 'shop_orders', $row ) );
		$this->assertSame( ' <span class="screen-reader-text">New order</span>', Highlights::label( 'shop_orders', $row ) );
	}

	/**
	 * A state of its own: a priority, a colour, a tint, and the bar is drawn for it.
	 */
	public function test_a_table_adds_a_state(): void {
		$this->orders(
			[],
			[
				'highlights' => [
					'states' => [
						'attention' => [
							'label'    => '',
							'color'    => '#dba617',
							'tint'     => true,
							'priority' => 30,
							'forced'   => 'double',
							'when'     => static fn( object $row ): bool => 'disputed' === $row->status,
						],
						'broken'    => [ 'color' => 'not a colour', 'forced' => 'wavy' ],
					],
				],
			]
		);

		$this->assertSame( [ 'attention', 'new', 'broken' ], array_keys( Highlights::states( 'shop_orders' ) ) );

		$disputed = $this->order( [ 'status' => 'disputed' ] );

		$this->assertSame( 'attention', Highlights::highlight( 'shop_orders', $disputed ), 'Attention comes before new.' );
		$this->assertSame( [ 'sugarcommerce-row-attention', 'sugarcommerce-row-lit', 'sugarcommerce-row-tinted' ], Highlights::row_classes( 'shop_orders', $disputed ) );

		$css = Bars::css( 'shop_orders' );

		$this->assertStringContainsString(
			'.wp-list-table tbody tr.sugarcommerce-row-attention{--sugarcommerce-row-bar:#dba617;--sugarcommerce-row-tint:#fbf6e8;--sugarcommerce-row-forced-style:double;--sugarcommerce-row-forced-color:CanvasText}',
			$css
		);
		$this->assertStringContainsString(
			'.wp-list-table tbody tr.sugarcommerce-row-new{--sugarcommerce-row-bar:#00a32a;--sugarcommerce-row-forced-style:solid;--sugarcommerce-row-forced-color:Highlight}',
			$css
		);

		// No colour, no rule; an unknown line style is a solid one.
		$this->assertStringNotContainsString( 'sugarcommerce-row-broken', $css );
		$this->assertSame( 'solid', Highlights::states( 'shop_orders' )['broken']['forced'] );
	}

	/**
	 * A filter has the last word on a row, and only the table's states pass.
	 */
	public function test_the_highlight_is_filtered(): void {
		$this->orders();

		$row = $this->order( [], 30 );

		$this->assertSame( '', Highlights::highlight( 'shop_orders', $row ) );

		add_filter( 'sugarcommerce_table_row_highlight_shop_orders', static fn(): string => 'new' );
		$this->assertSame( 'new', Highlights::highlight( 'shop_orders', $row ) );

		$GLOBALS['rt_filters'] = [];
		add_filter( 'sugarcommerce_table_row_highlight_shop_orders', static fn(): string => 'red' );
		$this->assertSame( '', Highlights::highlight( 'shop_orders', $row ) );
		$this->assertSame( [], Highlights::row_classes( 'shop_orders', $row ) );

		// A table with nothing registered lights nothing.
		$this->assertSame( '', Highlights::highlight( 'shop_other', $row ) );
		$this->assertSame( [], Highlights::states( 'shop_other' ) );
	}

	/**
	 * The states are filtered once, by table.
	 */
	public function test_the_states_are_filtered(): void {
		add_filter(
			'sugarcommerce_table_highlights_shop_orders',
			static fn( array $states ): array => $states + [ 'vip' => [ 'color' => '#2271b1', 'priority' => 5, 'when' => static fn(): bool => true ] ]
		);

		$this->orders();

		$this->assertSame( 'vip', Highlights::highlight( 'shop_orders', $this->order() ) );
	}

	/**
	 * One shared registry, picked into each list's states.
	 */
	public function test_a_shared_registry_is_picked_by_list(): void {
		$registry = [
			'blocked'   => [ 'label' => 'Blocked', 'labels' => [ 'customers' => 'Blocked customer' ], 'color' => '#d63638' ],
			'attention' => [ 'label' => '', 'color' => '#dba617', 'lists' => [ 'orders' ] ],
		];

		$this->assertSame(
			[ 'blocked' => [ 'label' => 'Blocked customer', 'color' => '#d63638' ] ],
			Highlights::for_list( $registry, 'customers' )
		);
		$this->assertSame( [ 'blocked', 'attention' ], array_keys( Highlights::for_list( $registry, 'orders' ) ) );
		$this->assertSame( 'Blocked', Highlights::for_list( $registry, 'orders' )['blocked']['label'] );
	}

	/**
	 * Highlights without a marker: states of the table's own, and no "new".
	 */
	public function test_highlights_without_a_marker(): void {
		Tables::register(
			'shop_payouts',
			[
				'labels'     => [ 'plural' => 'payouts' ],
				'columns'    => [ 'name' => 'Name' ],
				'highlights' => [ 'states' => [ 'late' => [ 'color' => '#d63638', 'when' => static fn( object $row ): bool => $row->late ] ] ],
			]
		);

		$this->assertSame( [ 'late' ], array_keys( Highlights::states( 'shop_payouts' ) ) );
		$this->assertSame( 'late', Highlights::highlight( 'shop_payouts', (object) [ 'id' => 1, 'late' => true ] ) );
		$this->assertSame( '', Highlights::highlight( 'shop_payouts', (object) [ 'id' => 1, 'late' => false ] ) );
	}

	/**
	 * The bars are on the screens of tables that light rows, and nowhere else.
	 */
	public function test_the_bars_are_loaded_on_their_list_only(): void {
		$this->orders();

		$_GET['page'] = 'shop-products';
		Bars::enqueue();
		$this->assertArrayNotHasKey( Bars::handle(), $GLOBALS['rt_inline_styles'] ?? [] );
		$this->assertSame( 'wp-admin', Bars::body_class( 'wp-admin' ) );

		$_GET['page'] = 'shop-orders';
		Bars::enqueue();
		$this->assertStringContainsString( 'tr.sugarcommerce-row-new{', $GLOBALS['rt_inline_styles'][ Bars::handle() ] ?? '' );
		$this->assertSame( 'wp-admin sugarcommerce-table-highlights', Bars::body_class( 'wp-admin' ) );
	}

	/**
	 * The stylesheet: core's bar on the first cell, logical properties, a
	 * high-contrast mode, and the folded row.
	 */
	public function test_the_stylesheet(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/highlights.css' );

		$this->assertStringContainsString( "tr.sugarcommerce-row-lit > :first-child {\n\tborder-inline-start: 4px solid var(--sugarcommerce-row-bar, currentColor);", $css );
		$this->assertStringContainsString( "tr.sugarcommerce-row-lit > .check-column input {\n\tmargin-inline-start: 4px;", $css );
		$this->assertStringContainsString( '@media (forced-colors: active)', $css );
		$this->assertStringContainsString( '@media screen and (max-width: 782px)', $css );
		$this->assertStringContainsString( 'tr.status-pending:not(.sugarcommerce-row-tinted)', $css );

		// Logical properties only, so a right-to-left admin needs nothing more.
		$this->assertStringNotContainsString( 'border-left', $css );
		$this->assertStringNotContainsString( 'margin-left', $css );

		// Only a tinted row has a background of its own.
		$this->assertSame( 1, substr_count( $css, 'var(--sugarcommerce-row-tint)' ) );
	}
}
