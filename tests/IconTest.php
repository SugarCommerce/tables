<?php
/**
 * Icon tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Fields\Icons\Icons;
use SugarCommerce\Tables\Columns;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Reorder;
use SugarCommerce\Tables\Table;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Every glyph a table draws goes through the field kit's Icons::render().
 *
 * Worth pinning: an `icon` a table is handed takes both forms -- a
 * `set:name` (Heroicons and the rest, drawn as SVG) and a bare Dashicons
 * name, drawn as exactly the span it always was -- and the library's own
 * glyphs (the empty list's, a yes or a no, the reorder handle and its
 * buttons) are Heroicons now, with no Dashicons span left among them.
 */
final class IconTest extends TestCase {

	/**
	 * A clean request between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET     = [];
		$_REQUEST = [];

		Reorder::reset( 'demo' );
	}

	/**
	 * The empty list, as a table with nothing in it draws it.
	 *
	 * @param array<string, mixed> $empty_state The table's `empty_state`.
	 *
	 * @return string
	 */
	private function empty_state( array $empty_state ): string {
		Tables::register(
			'demo',
			[
				'labels'      => [
					'singular' => 'thing',
					'plural'   => 'things',
				],
				'columns'     => [ 'name' => 'Name' ],
				'empty_state' => $empty_state,
				'callbacks'   => [ 'get_items' => static fn(): array => [] ],
			]
		);

		$table = new Table( 'demo', Tables::get_table( 'demo' ) );

		ob_start();
		( new ReflectionMethod( $table, 'render_empty_state' ) )->invoke( $table );

		return (string) ob_get_clean();
	}

	/**
	 * The icon inside the empty list's icon box, or '' for none.
	 *
	 * @param string $html The empty list.
	 *
	 * @return string
	 */
	private function empty_icon( string $html ): string {
		return preg_match( '#<div class="list-table-empty-state__icon">\s*(.*?)\s*</div>#s', $html, $match ) ? $match[1] : '';
	}

	/**
	 * Both forms come through the helper; anything else draws nothing.
	 */
	public function test_the_helper_takes_both_forms(): void {
		$this->assertStringStartsWith( '<svg width="20" height="20" class="sugarcommerce-icon x" aria-hidden="true"', Columns::icon( 'heroicons-outline:inbox', [ 'class' => 'x' ] ) );
		$this->assertSame( '<span class="dashicons dashicons-cart sugarcommerce-icon" aria-hidden="true"></span>', Columns::icon( 'cart' ) );
		$this->assertSame( '<span class="dashicons dashicons-cart sugarcommerce-icon" aria-hidden="true"></span>', Columns::icon( 'dashicons-cart' ) );
		$this->assertSame( '<span class="dashicons dashicons-cart sugarcommerce-icon" aria-hidden="true"></span>', Columns::icon( 'dashicons dashicons-cart' ) );
		$this->assertSame( '', Columns::icon( '' ) );
		$this->assertSame( '', Columns::icon( 'heroicons-outline:no-such-icon' ) );
		$this->assertSame( '', Columns::icon( '<b>x</b>' ) );
	}

	/**
	 * The empty list's default is a Heroicon now.
	 */
	public function test_the_empty_list_has_a_heroicon_by_default(): void {
		$this->assertSame( Icons::render( 'heroicons-outline:inbox' ), $this->empty_icon( $this->empty_state( [] ) ) );
	}

	/**
	 * An empty list's icon takes either form, or none.
	 */
	public function test_the_empty_list_takes_either_form_of_icon(): void {
		$svg = $this->empty_icon( $this->empty_state( [ 'icon' => 'heroicons-outline:shopping-cart' ] ) );

		$this->assertStringStartsWith( '<svg width="20" height="20" class="sugarcommerce-icon" aria-hidden="true"', $svg );
		$this->assertSame( Icons::render( 'heroicons-outline:shopping-cart' ), $svg );

		$this->assertSame(
			'<span class="dashicons dashicons-cart sugarcommerce-icon" aria-hidden="true"></span>',
			$this->empty_icon( $this->empty_state( [ 'icon' => 'dashicons-cart' ] ) )
		);

		$this->assertStringNotContainsString( 'list-table-empty-state__icon', $this->empty_state( [ 'icon' => '' ] ) );
	}

	/**
	 * A yes is a check in a circle and a no a dash, both Heroicons, each
	 * with its word for a screen reader.
	 */
	public function test_a_boolean_is_a_heroicon(): void {
		$yes = Columns::format_boolean( 1, (object) [], 'is_active' );
		$no  = Columns::format_boolean( 0, (object) [], 'is_active' );

		$this->assertSame( Icons::render( 'heroicons-outline:check-circle', [ 'class' => 'column-boolean-yes' ] ) . '<span class="screen-reader-text">Yes</span>', $yes );
		$this->assertSame( Icons::render( 'heroicons-outline:minus', [ 'class' => 'column-boolean-no' ] ) . '<span class="screen-reader-text">No</span>', $no );
		$this->assertStringStartsWith( '<svg width="20" height="20" class="sugarcommerce-icon column-boolean-yes"', $yes );
		$this->assertStringStartsWith( '<svg width="20" height="20" class="sugarcommerce-icon column-boolean-no"', $no );
	}

	/**
	 * The reorder handle and its two buttons are Heroicons: a grip and two
	 * chevrons.
	 */
	public function test_the_reorder_controls_are_heroicons(): void {
		Tables::register(
			'demo',
			[
				'capability' => 'edit_things',
				'orderby'    => 'sort_order',
				'order'      => 'asc',
				'columns'    => [
					'name'       => 'Name',
					'sort_order' => 'Position',
				],
				'reorder'    => [
					'column' => 'sort_order',
					'save'   => static fn(): bool => true,
				],
				'callbacks'  => [ 'get_items' => static fn(): array => [] ],
			]
		);

		$table = new Table( 'demo', Tables::get_table( 'demo' ) );
		$cell  = $table->column_default( (object) [
			'id' => 11,
			'name' => 'Row',
			'sort_order' => 0,
		], 'sort_order' );

		$this->assertStringContainsString( 'arrow keys.">' . Icons::render( 'heroicons-outline:bars-3' ) . '</button>', $cell );
		$this->assertStringContainsString( 'up">' . Icons::render( 'heroicons-outline:chevron-up' ) . '</button>', $cell );
		$this->assertStringContainsString( 'down">' . Icons::render( 'heroicons-outline:chevron-down' ) . '</button>', $cell );
		$this->assertStringNotContainsString( 'dashicons', $cell );
	}

	/**
	 * The stylesheets size the glyphs by the class both forms carry, and
	 * no longer by `.dashicons`, which an SVG does not have.
	 */
	public function test_the_stylesheets_size_both_forms(): void {
		$css = '';

		foreach ( [ 'admin-tables', 'toggle', 'reorder' ] as $sheet ) {
			$css .= (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/' . $sheet . '.css' );
		}

		$this->assertStringNotContainsString( '.dashicons', $css );
		$this->assertStringContainsString( '.list-table-empty-state__icon .sugarcommerce-icon {', $css );
		$this->assertStringContainsString( '.sugarcommerce-toggle-cell--line .sugarcommerce-icon {', $css );
		$this->assertStringContainsString( '.sugarcommerce-reorder__step .sugarcommerce-icon {', $css );
	}
}
