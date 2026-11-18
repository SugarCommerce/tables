<?php
/**
 * List date tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\ListDate;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Table;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * One preference for each person, honoured by every date on the lists that
 * share it: "2 weeks ago" or "13 Oct 2026, 3:20 pm", with the other in the
 * tooltip.
 */
final class ListDateTest extends TestCase {

	/**
	 * A clean slate, with the library's screen option filter back on.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_POST = [];

		( new ReflectionMethod( Tables::class, 'handle_screen_options' ) )->invoke( null );
	}

	/**
	 * Leave no request behind.
	 */
	protected function tearDown(): void {
		$_POST = [];
	}

	/**
	 * A stored datetime some time from now.
	 *
	 * @param int $seconds How far; negative for the past.
	 *
	 * @return string
	 */
	private function at( int $seconds ): string {
		return gmdate( 'Y-m-d H:i:s', time() + $seconds );
	}

	/**
	 * Relative unless somebody has said otherwise, with the date one hover away.
	 */
	public function test_a_date_is_relative_by_default(): void {
		$when = $this->at( -2 * 604800 );

		$this->assertSame( 'relative', ListDate::mode( 'shop_list_dates' ) );
		$this->assertSame(
			sprintf(
				'<time class="shop-date" datetime="%1$s" title="%2$s">2 weeks ago</time>',
				gmdate( 'c', strtotime( $when . ' UTC' ) ),
				gmdate( 'j M Y, g:i a', strtotime( $when . ' UTC' ) )
			),
			ListDate::cell( $when, ListDate::mode( 'shop_list_dates' ), [ 'class' => 'shop-date' ] )
		);
	}

	/**
	 * Exact is the short date and the time, with how long ago in the tooltip.
	 */
	public function test_exact_shows_the_date_and_keeps_the_other_reading(): void {
		ListDate::set_mode( 'shop_list_dates', 'exact' );

		$this->assertSame( 'exact', ListDate::mode( 'shop_list_dates' ) );
		$this->assertSame( '13 Oct 2026, 2:20 pm', ListDate::exact( '2026-10-13 14:20:00' ) );

		$when = $this->at( -2 * 604800 );
		$cell = ListDate::cell( $when, 'exact' );

		$this->assertStringContainsString( '>' . ListDate::exact( $when ) . '</time>', $cell );
		$this->assertStringContainsString( 'title="2 weeks ago"', $cell );
		$this->assertStringNotContainsString( 'class=', $cell );
	}

	/**
	 * A date still to come says how far off it is, and none turns into a
	 * date for being old.
	 */
	public function test_future_and_old_dates_keep_their_words(): void {
		$this->assertStringContainsString( '>in 3 weeks</time>', ListDate::cell( $this->at( 3 * 604800 + 3600 ) ) );
		$this->assertStringContainsString( '>2 years ago</time>', ListDate::cell( $this->at( -2 * 31536000 - 86400 ) ) );
		$this->assertStringContainsString( 'title="in 3 weeks"', ListDate::cell( $this->at( 3 * 604800 + 3600 ), 'exact' ) );
	}

	/**
	 * No date is a dash, or the word given for it, in either mode -- and
	 * BerlinDB's zero date is no date.
	 */
	public function test_no_date_is_a_dash_or_the_word_given(): void {
		foreach ( [ 'relative', 'exact' ] as $mode ) {
			foreach ( [ null, '', '0000-00-00 00:00:00' ] as $nothing ) {
				$this->assertSame( '<span aria-hidden="true">&mdash;</span>', ListDate::cell( $nothing, $mode ) );
				$this->assertSame( 'Never &amp; ever', ListDate::cell( $nothing, $mode, [ 'none' => 'Never & ever' ] ) );
			}
		}

		$this->assertFalse( ListDate::has_date( '0000-00-00 00:00:00' ) );
		$this->assertFalse( ListDate::has_date( null ) );
		$this->assertTrue( ListDate::has_date( '2026-10-13 14:20:00' ) );
	}

	/**
	 * A DateTime or a timestamp is the moment it is.
	 */
	public function test_a_datetime_or_a_timestamp_is_read(): void {
		$cell = ListDate::cell( 1791901200, 'exact' );

		$this->assertStringContainsString( 'datetime="2026-10-13T14:20:00+00:00"', $cell );
		$this->assertStringContainsString( 'datetime="2026-10-13T14:20:00+00:00"', ListDate::cell( new \DateTimeImmutable( '2026-10-13 15:20:00', new \DateTimeZone( '+01:00' ) ) ) );
	}

	/**
	 * It is each person's own, and anything but `exact` is `relative`.
	 */
	public function test_the_preference_is_per_person(): void {
		$GLOBALS['rt_user_id'] = 7;

		ListDate::set_mode( 'shop_list_dates', 'exact' );

		$this->assertSame( 'exact', $GLOBALS['rt_meta_by_user'][7]['shop_list_dates'] );
		$this->assertSame( 'exact', ListDate::mode( 'shop_list_dates' ) );
		$this->assertSame( 'relative', ListDate::mode( 'shop_list_dates', 8 ) );

		// Nonsense is the default, stored and read.
		ListDate::set_mode( 'shop_list_dates', 'sideways' );
		$this->assertSame( 'relative', $GLOBALS['rt_meta_by_user'][7]['shop_list_dates'] );

		$GLOBALS['rt_meta_by_user'][7]['shop_list_dates'] = 'sideways';
		$this->assertSame( 'relative', ListDate::mode( 'shop_list_dates' ) );

		// Nobody signed in has nothing kept for them, and no key keeps nothing.
		$GLOBALS['rt_user_id'] = 0;
		$this->assertFalse( ListDate::set_mode( 'shop_list_dates', 'exact' ) );
		$this->assertSame( 'relative', ListDate::mode( 'shop_list_dates' ) );

		$GLOBALS['rt_user_id'] = 7;
		$this->assertFalse( ListDate::set_mode( '', 'exact' ) );
	}

	/**
	 * A site can have one way for everybody, through the key's own filter.
	 */
	public function test_a_filter_can_force_a_mode(): void {
		add_filter( 'sugarcommerce_table_date_mode_shop_list_dates', static fn(): string => 'exact' );

		$this->assertSame( 'exact', ListDate::mode( 'shop_list_dates' ) );
		$this->assertSame( 'relative', ListDate::mode( 'other_list_dates' ) );

		add_filter( 'sugarcommerce_table_date_mode_shop_list_dates', static fn(): string => 'nonsense', 20 );

		$this->assertSame( 'relative', ListDate::mode( 'shop_list_dates' ) );
	}

	/**
	 * Off unless asked; `true` is a key of the table's own; a string is a
	 * key to share.
	 */
	public function test_the_key_comes_from_the_table(): void {
		$this->assertSame( '', ListDate::meta_key( 'shop_orders', [] ) );
		$this->assertSame( '', ListDate::meta_key( 'shop_orders', [ 'date_preference' => false ] ) );
		$this->assertSame( 'shop_orders_dates', ListDate::meta_key( 'shop_orders', [ 'date_preference' => true ] ) );
		$this->assertSame( 'shop_list_dates', ListDate::meta_key( 'shop_orders', [ 'date_preference' => 'shop_list_dates' ] ) );
	}

	/**
	 * The control: both choices, each with an example, the chosen one ticked.
	 */
	public function test_the_control_offers_both_and_ticks_the_choice(): void {
		$html = ListDate::control( 'shop_list_dates' );

		$this->assertStringContainsString( '<legend>Dates</legend>', $html );
		$this->assertMatchesRegularExpression( '/name="shop_list_dates" value="relative" checked=\'checked\' \/> Relative <span class="description">\(2 weeks ago\)/', $html );
		$this->assertMatchesRegularExpression( '/name="shop_list_dates" value="exact" \/> Exact/', $html );

		ListDate::set_mode( 'shop_list_dates', 'exact' );

		$html = ListDate::control( 'shop_list_dates' );

		$this->assertMatchesRegularExpression( '/value="exact" checked=\'checked\'/', $html );
		$this->assertDoesNotMatchRegularExpression( '/value="relative" checked/', $html );

		$this->assertSame( '', ListDate::control( '' ) );
	}

	/**
	 * The control is in the table's own Screen Options, and nowhere else.
	 */
	public function test_the_control_is_on_the_tables_screen_only(): void {
		Tables::register( 'shop_orders', [ 'columns' => [ 'name' => 'Name' ], 'date_preference' => 'shop_list_dates' ] );
		Tables::register( 'shop_plain', [ 'columns' => [ 'name' => 'Name' ] ] );

		$GLOBALS['rt_screen'] = new FakeScreen( 'shop_page_shop-orders' );
		( new ReflectionMethod( Tables::class, 'setup_screen' ) )->invoke( null, 'shop_orders', Tables::get_table( 'shop_orders' ) );

		$html = (string) apply_filters( 'screen_settings', '<p>Theirs</p>', $GLOBALS['rt_screen'] );

		$this->assertStringStartsWith( '<p>Theirs</p>', $html );
		$this->assertStringContainsString( 'name="shop_list_dates"', $html );
		$this->assertSame( '', apply_filters( 'screen_settings', '', new FakeScreen( 'edit-post' ) ) );

		// A table with no preference draws none.
		$GLOBALS['rt_filters'] = [];
		$GLOBALS['rt_screen']  = new FakeScreen( 'shop_page_shop-plain' );
		( new ReflectionMethod( Tables::class, 'setup_screen' ) )->invoke( null, 'shop_plain', Tables::get_table( 'shop_plain' ) );

		$this->assertSame( '', apply_filters( 'screen_settings', '', $GLOBALS['rt_screen'] ) );
	}

	/**
	 * Saved by core's Apply on one of the library's tables, and no other form.
	 */
	public function test_it_is_saved_with_the_tables_screen_options_only(): void {
		Tables::register( 'shop_orders', [ 'columns' => [ 'name' => 'Name' ], 'date_preference' => 'shop_list_dates' ] );
		Tables::register( 'shop_licences', [ 'columns' => [ 'name' => 'Name' ], 'date_preference' => 'shop_list_dates' ] );

		$_POST['shop_list_dates'] = 'exact';

		// Another screen's form: nothing kept, and its value passed on.
		$this->assertFalse( apply_filters( 'set-screen-option', false, 'edit_post_per_page', '25' ) );
		$this->assertSame( 'relative', ListDate::mode( 'shop_list_dates' ) );

		// Ours: kept, and the rows per page still answered.
		$this->assertSame( 25, apply_filters( 'set-screen-option', false, 'shop_licences_per_page', '25' ) );
		$this->assertSame( 'exact', ListDate::mode( 'shop_list_dates' ) );

		// Back again from another list sharing it.
		$_POST['shop_list_dates'] = 'relative';
		apply_filters( 'set-screen-option', false, 'shop_orders_per_page', '20' );
		$this->assertSame( 'relative', ListDate::mode( 'shop_list_dates' ) );

		// Not a string: ignored.
		$_POST['shop_list_dates'] = [ 'exact' ];
		apply_filters( 'set-screen-option', false, 'shop_orders_per_page', '20' );
		$this->assertSame( 'relative', ListDate::mode( 'shop_list_dates' ) );
	}

	/**
	 * A date column of a table follows the table's preference.
	 */
	public function test_a_tables_date_columns_follow_its_preference(): void {
		Tables::register(
			'shop_orders',
			[
				'columns'         => [
					'date_created' => 'Date',
					'date_expires' => [
						'label' => 'Expires',
						'none'  => 'Never',
					],
				],
				'date_preference' => 'shop_list_dates',
			]
		);

		$when = $this->at( -2 * 604800 );
		$row  = (object) [
			'id'           => 1,
			'date_created' => $when,
			'date_expires' => '0000-00-00 00:00:00',
		];

		$table = new Table( 'shop_orders', Tables::get_table( 'shop_orders' ) );

		$this->assertStringContainsString( '>2 weeks ago</time>', $table->column_default( $row, 'date_created' ) );
		$this->assertSame( 'Never', $table->column_default( $row, 'date_expires' ) );

		ListDate::set_mode( 'shop_list_dates', 'exact' );

		$table = new Table( 'shop_orders', Tables::get_table( 'shop_orders' ) );

		$this->assertStringContainsString( '>' . ListDate::exact( $when ) . '</time>', $table->column_default( $row, 'date_created' ) );
		$this->assertStringContainsString( 'title="2 weeks ago"', $table->column_default( $row, 'date_created' ) );
	}
}

/**
 * Just enough of WP_Screen for setup_screen().
 */
final class FakeScreen {

	/**
	 * Constructor.
	 *
	 * @param string $id The screen's id.
	 */
	public function __construct( public string $id ) {}

	/**
	 * Unused.
	 *
	 * @param mixed ...$args Ignored.
	 *
	 * @return void
	 */
	public function add_option( ...$args ): void {}

	/**
	 * Unused.
	 *
	 * @param mixed ...$args Ignored.
	 *
	 * @return void
	 */
	public function set_screen_reader_content( ...$args ): void {}
}
