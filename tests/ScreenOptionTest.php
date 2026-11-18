<?php
/**
 * Screen option tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Tables;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Saving the "items per page" preference, and nobody else's.
 *
 * Core hands every screen option on the site through one filter and expects
 * each plugin to claim its own. Claiming everything ending in `_per_page`
 * saved other plugins' options as integers whether or not that was what
 * they held -- and their own filter, running after, never saw them.
 */
final class ScreenOptionTest extends TestCase {

	/**
	 * A clean slate, with this library's filter back on it.
	 *
	 * The filter is added once, when the first table registers, and the
	 * reset between tests forgets it -- so it is put back by hand.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		Tables::register( 'demo', [ 'columns' => [ 'name' => 'Name' ] ] );

		( new ReflectionMethod( Tables::class, 'handle_screen_options' ) )->invoke( null );
	}

	/**
	 * The table's own option is saved, as a number.
	 */
	public function test_the_tables_option_is_saved(): void {
		$this->assertSame( 25, apply_filters( 'set-screen-option', false, 'demo_per_page', '25' ) );
	}

	/**
	 * A column hidden by default is unticked in Screen Options.
	 *
	 * The boxes there are core's, and core asks `default_hidden_columns`
	 * which columns start hidden. Unsaid, a column the table was hiding had
	 * a ticked box.
	 */
	public function test_a_hidden_column_is_hidden_to_core_as_well(): void {
		$config = [
			'columns'        => [
				'name'  => 'Name',
				'email' => 'Email',
				'notes' => [
					'label'  => 'Notes',
					'hidden' => true,
				],
			],
			'hidden_columns' => [ 'email' ],
		];

		Tables::register( 'people', $config );

		$screen = (object) [ 'id' => 'toplevel_page_people' ];

		( new ReflectionMethod( Tables::class, 'setup_column_options' ) )->invoke( null, $screen, Tables::get_table( 'people' ), 'people' );

		$this->assertSame( [ 'email', 'notes' ], apply_filters( 'default_hidden_columns', [], $screen ) );

		// And on no other screen.
		$this->assertSame( [], apply_filters( 'default_hidden_columns', [], (object) [ 'id' => 'edit-post' ] ) );
		$this->assertSame( [ 'theirs' ], apply_filters( 'default_hidden_columns', [ 'theirs' ], (object) [ 'id' => 'edit-post' ] ) );
	}

	/**
	 * Another plugin's is left alone.
	 */
	public function test_another_plugins_option_is_left_alone(): void {
		$this->assertFalse( apply_filters( 'set-screen-option', false, 'edit_post_per_page', '25' ) );
	}
}
