<?php
/**
 * Toggle cell tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Fields\Icons\Icons;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Table;
use SugarCommerce\Tables\Toggle;
use SugarCommerce\Tables\ToggleRoute;
use PHPUnit\Framework\TestCase;

/**
 * A toggle cell is a button that says whether it is on, and saves.
 *
 * Worth pinning: what it is called (a column of identically named buttons
 * cannot be told apart by ear), who gets a button at all, what the tooltip
 * warns, where it saves -- and that the library's own route checks the
 * capability before it calls anything.
 */
final class ToggleTest extends TestCase {

	/**
	 * What the save callable was handed.
	 *
	 * @var array<int, array{0: int, 1: bool}>
	 */
	private array $saved = [];

	/**
	 * A clean request between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET        = [];
		$_REQUEST    = [];
		$this->saved = [];
	}

	/**
	 * A products table with a star, and a customers table with a line.
	 *
	 * @param array<string, mixed> $star Extra star settings.
	 * @param mixed                $save What the line's save answers.
	 *
	 * @return void
	 */
	private function register( array $star = [], mixed $save = true ): void {
		Tables::register(
			'demo',
			[
				'capability' => 'edit_things',
				'columns'    => [
					'name'     => 'Name',
					'featured' => [
						'label'  => Toggle::heading( 'Featured' ),
						'toggle' => array_merge(
							[
								'style'        => 'star',
								'on'           => 'Featured',
								'off'          => 'Not featured',
								'turn_on'      => 'Mark as Featured',
								'turn_off'     => 'Remove from Featured',
								'turn_on_row'  => 'Mark “%s” as Featured',
								'turn_off_row' => 'Remove “%s” from Featured',
								'rest'         => [
									'path'   => '/shop/v1/products/{id}',
									'method' => 'patch',
									'key'    => 'is_featured',
								],
							],
							$star
						),
					],
					'consent'  => [
						'label'  => 'Email',
						'toggle' => [
							'field'       => 'marketing_consent',
							'on'          => 'Subscribed',
							'off'         => 'Not subscribed',
							'name'        => 'Subscribed to Marketing',
							'turn_on'     => 'Mark as Subscribed',
							'turn_off'    => 'Mark as Not Subscribed',
							'confirm_on'  => 'Only do this if they agreed to hear from you.',
							'press_again' => 'Press again to mark them as subscribed.',
							'since'       => static fn( $row ): string => 'Since 3 Mar 2026',
							'capability'  => 'edit_people',
							'save'        => function ( int $id, bool $on ) use ( $save ) {
								$this->saved[] = [ $id, $on ];

								return $save;
							},
						],
					],
				],
				'callbacks'  => [
					'get_items' => static fn() => [],
					'get_item'  => static fn( $id ) => (object) [ 'id' => $id ],
				],
			]
		);
	}

	/**
	 * A cell, drawn through the table as a page would.
	 *
	 * @param string $column The column.
	 * @param object $row    The row.
	 *
	 * @return string
	 */
	private function cell( string $column, object $row ): string {
		return ( new Table( 'demo', Tables::get_table( 'demo' ) ) )->column_default( $row, $column );
	}

	/**
	 * Each star is a button showing its state in its glyph, and named for
	 * what pressing it does to this row.
	 */
	public function test_the_star_is_a_button_in_both_states(): void {
		$this->register();

		$on  = $this->cell( 'featured', (object) [
			'id' => 4,
			'title' => 'Analog Drums',
			'featured' => 1,
		] );
		$off = $this->cell( 'featured', (object) [
			'id' => 5,
			'title' => 'Tape Loops',
			'featured' => 0,
		] );

		$this->assertStringContainsString( '<button type="button" class="sugarcommerce-toggle button-link" data-toggle="demo:featured" data-id="4"', $on );
		$this->assertStringContainsString( 'aria-pressed="true"', $on );
		$this->assertStringContainsString( 'aria-label="Remove “Analog Drums” from Featured"', $on );
		$this->assertStringContainsString( 'title="Remove from Featured"', $on );
		$this->assertStringContainsString( Icons::render( 'heroicons-solid:star' ), $on );
		$this->assertStringContainsString( 'is-on', $on );

		$this->assertStringContainsString( 'aria-pressed="false"', $off );
		$this->assertStringContainsString( 'aria-label="Mark “Tape Loops” as Featured"', $off );
		$this->assertStringContainsString( Icons::render( 'heroicons-outline:star' ), $off );
		$this->assertStringNotContainsString( 'dashicons', $on . $off );
		$this->assertStringNotContainsString( 'is-on', $off );

		// A star's word is in its name; nothing visible but the glyph.
		$this->assertStringNotContainsString( 'sugarcommerce-toggle__word', $on );
	}

	/**
	 * A name is escaped wherever the button carries it.
	 */
	public function test_a_name_is_escaped(): void {
		$this->register();

		$cell = $this->cell( 'featured', (object) [
			'id' => 4,
			'title' => 'Kick "&" Snare',
			'featured' => 0,
		] );

		$this->assertStringContainsString( 'data-name="Kick &quot;&amp;&quot; Snare"', $cell );
		$this->assertStringContainsString( 'aria-label="Mark “Kick &quot;&amp;&quot; Snare” as Featured"', $cell );
	}

	/**
	 * Somebody who may see the list but not change the record sees the
	 * state, and is offered no button the server would refuse.
	 */
	public function test_a_viewer_gets_no_button(): void {
		$this->register();

		$GLOBALS['rt_caps'] = [ 'read' ];

		$cell = $this->cell( 'featured', (object) [
			'id' => 4,
			'title' => 'Analog Drums',
			'featured' => 1,
		] );

		$this->assertStringNotContainsString( '<button', $cell );
		$this->assertStringContainsString( Icons::render( 'heroicons-solid:star' ), $cell );
		$this->assertStringContainsString( '<span class="screen-reader-text">Featured</span>', $cell );
	}

	/**
	 * The line: a fixed name, the state in words, a check for "on" only,
	 * since-when and the warning in the tooltip.
	 */
	public function test_the_line_in_both_states(): void {
		$this->register();

		$on  = $this->cell( 'consent', (object) [
			'id' => 8,
			'name' => 'Kenji Sato',
			'marketing_consent' => 1,
		] );
		$off = $this->cell( 'consent', (object) [
			'id' => 9,
			'name' => 'Ada',
			'marketing_consent' => 0,
		] );

		$this->assertSame(
			'<span class="sugarcommerce-toggle-cell sugarcommerce-toggle-cell--line is-on"><button type="button" class="sugarcommerce-toggle button-link" data-toggle="demo:consent" data-id="8" data-name="Kenji Sato" aria-pressed="true" aria-label="Subscribed to Marketing" title="Since 3 Mar 2026 · Mark as Not Subscribed">' . Icons::render( 'heroicons-outline:check' ) . '<span class="sugarcommerce-toggle__word">Subscribed</span></button><span class="sugarcommerce-toggle__message" hidden></span></span>',
			$on
		);

		$this->assertStringContainsString( 'title="Mark as Subscribed Only do this if they agreed to hear from you."', $off );
		$this->assertStringContainsString( '<span class="sugarcommerce-toggle__word">Not subscribed</span>', $off );
		$this->assertStringNotContainsString( 'sugarcommerce-icon', $off, 'No check beside "Not subscribed".' );
	}

	/**
	 * The toggle's own capability decides, not the table's.
	 */
	public function test_its_own_capability_decides(): void {
		$this->register();

		$GLOBALS['rt_caps'] = [ 'edit_things' ];

		$cell = $this->cell( 'consent', (object) [
			'id' => 8,
			'name' => 'Kenji',
			'marketing_consent' => 1,
		] );

		$this->assertStringNotContainsString( '<button', $cell );
		$this->assertStringContainsString( 'title="Since 3 Mar 2026"', $cell );

		// And with no capability of its own, the table's edit capability.
		$this->assertSame( 'edit_things', Toggle::capability( Tables::get_table( 'demo' ), (array) Toggle::config( Tables::get_table( 'demo' ), 'featured' ) ) );
	}

	/**
	 * The script is told where each toggle saves: the consumer's own route
	 * with the id left to fill, or the library's when there is a `save`.
	 */
	public function test_the_script_is_told_where_each_saves(): void {
		$this->register();

		$data = Toggle::script_data( 'demo', Tables::get_table( 'demo' ) );

		$this->assertSame( 'https://example.test/wp-json/shop/v1/products/{id}', $data['demo:featured']['request']['url'] );
		$this->assertSame( 'PATCH', $data['demo:featured']['request']['method'] );
		$this->assertSame( 'is_featured', $data['demo:featured']['request']['key'] );
		$this->assertSame( 'is_featured', $data['demo:featured']['request']['state'] );

		$this->assertSame( 'https://example.test/wp-json/sugarcommerce-tables/v1/demo/toggle/{id}', $data['demo:consent']['request']['url'] );
		$this->assertSame( 'POST', $data['demo:consent']['request']['method'] );
		$this->assertEquals( (object) [ 'column' => 'consent' ], $data['demo:consent']['request']['data'] );
		$this->assertSame( 'Only do this if they agreed to hear from you.', $data['demo:consent']['confirmOn'] );
		$this->assertArrayNotHasKey( 'demo:name', $data );
	}

	/**
	 * The library's route saves through the callable and says what it is now.
	 */
	public function test_the_route_saves(): void {
		$this->register();

		$answer = ToggleRoute::save( 'demo', 'consent', 8, true );

		$this->assertSame( 200, $answer['status'] );
		$this->assertSame( [ [ 8, true ] ], $this->saved );
		$this->assertSame( [
			'on' => true,
			'since_text' => 'Since 3 Mar 2026',
		], $answer['body'] );
		$this->assertContains( 'sugarcommerce_table_toggled_demo', $GLOBALS['rt_fired'] ?? [] );
	}

	/**
	 * Without the capability the callable is never called.
	 *
	 * The cell hides the button, but a REST route is anybody's to post to.
	 */
	public function test_the_route_checks_the_capability_first(): void {
		$this->register();

		$GLOBALS['rt_caps'] = [ 'edit_things' ];

		$answer = ToggleRoute::save( 'demo', 'consent', 8, true );

		$this->assertSame( 403, $answer['status'] );
		$this->assertSame( [], $this->saved );
	}

	/**
	 * A column with no `save` has no route to save through.
	 */
	public function test_a_column_without_a_save_is_not_saved_here(): void {
		$this->register();

		$this->assertSame( 404, ToggleRoute::save( 'demo', 'featured', 4, true )['status'] );
		$this->assertSame( 404, ToggleRoute::save( 'demo', 'name', 4, true )['status'] );
		$this->assertSame( 404, ToggleRoute::save( 'nope', 'consent', 4, true )['status'] );
	}

	/**
	 * A save that did not work says so, in the toggle's words.
	 */
	public function test_a_failed_save_says_so(): void {
		$this->register( [], false );

		$answer = ToggleRoute::save( 'demo', 'consent', 8, false );

		$this->assertSame( 500, $answer['status'] );
		$this->assertSame( 'Not saved. Try again.', $answer['body']['message'] );
	}

	/**
	 * A save may say the record ended up otherwise than asked.
	 */
	public function test_a_save_can_answer_what_it_now_is(): void {
		$this->register( [], [ 'on' => false ] );

		$answer = ToggleRoute::save( 'demo', 'consent', 8, true );

		$this->assertFalse( $answer['body']['on'] );
		$this->assertSame( '', $answer['body']['since_text'] );
	}

	/**
	 * The route is registered per table, only where something saves through it.
	 */
	public function test_a_route_is_registered_per_table(): void {
		$this->register();

		ToggleRoute::register_routes();

		$this->assertArrayHasKey( 'sugarcommerce-tables/v1/demo/toggle/(?P<id>\d+)', $GLOBALS['rt_routes'] ?? [] );
		$this->assertSame( 'POST', $GLOBALS['rt_routes']['sugarcommerce-tables/v1/demo/toggle/(?P<id>\d+)']['methods'] );
	}

	/**
	 * A toggle can live inside another cell: declared under `toggles`,
	 * drawn by the cell's own callback, saved through the same route.
	 */
	public function test_a_toggle_inside_another_cell(): void {
		Tables::register(
			'people',
			[
				'columns'   => [
					'email' => [
						'label'    => 'Email',
						'callback' => static fn( object $row ): string => esc_html( $row->email ) . Toggle::render( 'people', 'consent', $row ),
					],
				],
				'toggles'   => [
					'consent' => [
						'field' => 'marketing_consent',
						'on'    => 'Subscribed',
						'save'  => static fn( int $id, bool $on ): bool => true,
					],
				],
				'callbacks' => [ 'get_items' => static fn() => [] ],
			]
		);

		$cell = ( new Table( 'people', Tables::get_table( 'people' ) ) )->column_default( (object) [
			'id' => 3,
			'email' => 'a@b.c',
			'marketing_consent' => 1,
		], 'email' );

		$this->assertStringStartsWith( 'a@b.c<span class="sugarcommerce-toggle-cell sugarcommerce-toggle-cell--line is-on">', $cell );
		$this->assertStringContainsString( 'data-toggle="people:consent"', $cell );
		$this->assertArrayHasKey( 'people:consent', Toggle::script_data( 'people', Tables::get_table( 'people' ) ) );
		$this->assertSame( 200, ToggleRoute::save( 'people', 'consent', 3, false )['status'] );
	}

	/**
	 * A heading glyph keeps its word for a screen reader.
	 */
	public function test_a_heading_glyph_keeps_its_word(): void {
		$this->assertSame(
			'<span class="sugarcommerce-toggle-heading" aria-hidden="true" title="Featured">' . Icons::render( 'heroicons-solid:star' ) . '</span><span class="screen-reader-text">Featured</span>',
			Toggle::heading( 'Featured' )
		);
	}

	/**
	 * A heading takes either form of icon: a set's, drawn as SVG, or a bare
	 * Dashicons name, drawn as the font's span it always was.
	 */
	public function test_a_heading_takes_either_form_of_icon(): void {
		$svg  = Toggle::heading( 'Loved', 'heroicons-outline:heart' );
		$font = Toggle::heading( 'Featured', 'star-filled' );

		$this->assertStringContainsString( 'title="Loved"><svg width="20" height="20" class="sugarcommerce-icon" aria-hidden="true"', $svg );
		$this->assertStringContainsString( Icons::render( 'heroicons-outline:heart' ), $svg );
		$this->assertStringContainsString( 'title="Featured"><span class="dashicons dashicons-star-filled sugarcommerce-icon" aria-hidden="true"></span></span>', $font );
	}

	/**
	 * A toggle's own icons, in either form, are drawn in both states; and
	 * a state with no icon draws none.
	 */
	public function test_a_toggle_takes_its_own_icons_in_either_form(): void {
		$this->register(
			[
				'icons' => [
					'on'  => 'heroicons-solid:heart',
					'off' => 'heroicons-outline:heart',
				],
			]
		);

		$on  = $this->cell( 'featured', (object) [
			'id' => 4,
			'title' => 'A',
			'featured' => 1,
		] );
		$off = $this->cell( 'featured', (object) [
			'id' => 5,
			'title' => 'B',
			'featured' => 0,
		] );

		$this->assertStringContainsString( 'title="Remove from Featured"><svg width="20" height="20" class="sugarcommerce-icon"', $on );
		$this->assertStringContainsString( Icons::render( 'heroicons-solid:heart' ), $on );
		$this->assertStringContainsString( Icons::render( 'heroicons-outline:heart' ), $off );

		rt_reset_globals();
		$this->register(
			[
				'icons' => [
					'on'  => 'star-filled',
					'off' => 'dashicons-star-empty',
				],
			]
		);

		$on  = $this->cell( 'featured', (object) [
			'id' => 4,
			'title' => 'A',
			'featured' => 1,
		] );
		$off = $this->cell( 'featured', (object) [
			'id' => 5,
			'title' => 'B',
			'featured' => 0,
		] );

		$this->assertStringContainsString( '<span class="dashicons dashicons-star-filled sugarcommerce-icon" aria-hidden="true"></span></button>', $on );
		$this->assertStringContainsString( '<span class="dashicons dashicons-star-empty sugarcommerce-icon" aria-hidden="true"></span></button>', $off );
	}

	/**
	 * The script is handed each state's icon already drawn, so it swaps
	 * markup and never builds a glyph of its own.
	 */
	public function test_the_script_is_handed_the_icons_drawn(): void {
		$this->register();

		$data = Toggle::script_data( 'demo', Tables::get_table( 'demo' ) );

		$this->assertSame(
			[
				'on'  => Icons::render( 'heroicons-solid:star' ),
				'off' => Icons::render( 'heroicons-outline:star' ),
			],
			$data['demo:featured']['icons']
		);
		$this->assertSame(
			[
				'on'  => Icons::render( 'heroicons-outline:check' ),
				'off' => '',
			],
			$data['demo:consent']['icons']
		);
	}

	/**
	 * A screen with toggles loads the built script, with nothing of jQuery,
	 * and hands it the REST nonce and each toggle under the name it has
	 * always read. What the script does with them is tests/js/toggle.test.js.
	 */
	public function test_the_screen_loads_the_built_script(): void {
		$this->register();
		$_GET['page'] = 'demo';

		ToggleRoute::enqueue();

		[ $src, $deps ] = $GLOBALS['rt_scripts']['sugarcommerce-tables-toggle'] ?? [ '', [] ];

		$this->assertStringEndsWith( '/assets/build/toggle.js', (string) $src );
		$this->assertContains( 'wp-a11y', $deps );
		$this->assertSame( [], preg_grep( '/^jquery/', $deps ) );
		$this->assertContains( 'sugarcommerce-tables-toggle', $GLOBALS['rt_enqueued_scripts'] ?? [] );

		[ $name, $data ] = $GLOBALS['rt_localized']['sugarcommerce-tables-toggle'];

		$this->assertSame( 'sugarCommerceToggles', $name );
		$this->assertArrayHasKey( 'nonce', $data );
		$this->assertArrayHasKey( 'demo:consent', $data['columns'] );
	}
}
