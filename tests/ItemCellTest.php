<?php
/**
 * Item cell tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Cells\Item;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Table;
use PHPUnit\Framework\TestCase;

/**
 * One thing a row names: its picture, its name, how many more, and quieter
 * lines beneath -- built from parts.
 */
final class ItemCellTest extends TestCase {

	/**
	 * A clean slate.
	 */
	protected function setUp(): void {
		rt_reset_globals();
	}

	/**
	 * The cell is built from parts, every one of them escaped or said to be
	 * markup already.
	 */
	public function test_the_cell_is_built_from_parts(): void {
		$this->assertSame(
			'<span class="sugarcommerce-item"><span class="sugarcommerce-item-text"><span class="sugarcommerce-item-line"><span class="sugarcommerce-item-title" title="Fish &amp; Chips">Fish &amp; Chips</span></span></span></span>',
			Item::cell( [ 'title' => 'Fish & Chips' ] )
		);

		$this->assertSame(
			'<span class="sugarcommerce-item"><img src="kit.jpg" alt="" /><span class="sugarcommerce-item-text">'
			. '<span class="sugarcommerce-item-line"><span class="sugarcommerce-item-title" title="Trap Kit, Loops, &quot;Chords&quot;"><a href="#kit">Trap Kit</a></span>'
			. ' <span class="sugarcommerce-item-more">+ 2 &lt;others&gt;</span></span>'
			. '<span class="sugarcommerce-item-meta"><em>2 of 3 downloaded</em></span>'
			. '<span class="sugarcommerce-item-meta">Expires soon</span>'
			. '</span></span>',
			Item::cell(
				[
					'title'   => 'Trap Kit',
					'link'    => '<a href="#kit">Trap Kit</a>',
					'picture' => '<img src="kit.jpg" alt="" />',
					'more'    => '+ 2 <others>',
					'tooltip' => 'Trap Kit, Loops, "Chords"',
					'lines'   => [
						'downloads' => '<em>2 of 3 downloaded</em>',
						'empty'     => '  ',
						'second'    => 'Expires soon',
					],
				]
			)
		);
	}

	/**
	 * A count is worded; none is nothing.
	 */
	public function test_more_is_worded(): void {
		$this->assertSame( '+ 1 other', Item::more( 1 ) );
		$this->assertSame( '+ 2 others', Item::more( 2 ) );
		$this->assertSame( '', Item::more( 0 ) );
		$this->assertStringContainsString( '<span class="sugarcommerce-item-more">+ 2 others</span>', Item::cell( [ 'title' => 'Kit', 'more' => 2 ] ) );
		$this->assertStringNotContainsString( 'sugarcommerce-item-more', Item::cell( [ 'title' => 'Kit', 'more' => 0 ] ) );
	}

	/**
	 * An image when there is one: small and silent beside a name, its own
	 * size and words beside the title of the thing itself.
	 */
	public function test_a_picture_when_there_is_one(): void {
		$GLOBALS['rt_attachments'] = [ 5 => 'kit.jpg' ];

		$this->assertSame( '<img src="kit.jpg" class="sugarcommerce-thumb sugarcommerce-thumb-small" alt="" width="24" height="24" />', Item::picture( 5, 'box', 24 ) );
		$this->assertSame( '<img src="kit.jpg" class="sugarcommerce-thumb" alt="picture" />', Item::picture( 5 ) );
		$this->assertStringContainsString( 'class="mine"', Item::picture( 5, 'box', 40, [ 'class' => 'mine' ] ) );
	}

	/**
	 * No image, or one that has gone, is the drawn tile: a box for a thing,
	 * a stack for a group of things, or a glyph of the caller's own.
	 */
	public function test_no_picture_is_the_drawn_tile(): void {
		$box   = Item::picture( 0 );
		$gone  = Item::picture( 999, 'box', 24 );
		$stack = Item::picture( 0, 'stack' );

		$this->assertStringStartsWith( '<img src="data:image/svg+xml;base64,', $box );
		$this->assertStringContainsString( 'alt="" width="40" height="40" class="sugarcommerce-thumb"', $box );
		$this->assertStringContainsString( 'width="24" height="24" class="sugarcommerce-thumb sugarcommerce-thumb-small"', $gone );

		$this->assertStringContainsString( 'scale(1.25)', $this->svg( $box ) );
		$this->assertStringContainsString( 'M13.5 13.25h13M16 10h8', $this->svg( $stack ) );
		$this->assertSame( $this->svg( $box ), $this->svg( Item::picture( 0, 'nonsense' ) ) );
		$this->assertStringContainsString( '<circle r="4"/>', $this->svg( Item::picture( 0, 'mine', 40, [ 'placeholder' => '<circle r="4"/>' ] ) ) );

		// Never smaller than sixteen.
		$this->assertStringContainsString( 'width="16" height="16"', Item::picture( 0, 'box', 4 ) );
	}

	/**
	 * As a column: the row's parts, the table's filter.
	 */
	public function test_an_item_column(): void {
		Tables::register(
			'shop_orders',
			[
				'columns' => [
					'items' => [
						'label' => 'Items',
						'type'  => 'item',
						'item'  => static fn( object $row ): array => [
							'title' => $row->first,
							'more'  => $row->count - 1,
						],
					],
				],
			]
		);

		$table = new Table( 'shop_orders', Tables::get_table( 'shop_orders' ) );
		$row   = (object) [ 'id' => 1, 'first' => 'Trap Kit', 'count' => 3 ];

		$this->assertStringContainsString( 'title="Trap Kit">Trap Kit</span> <span class="sugarcommerce-item-more">+ 2 others</span>', $table->column_default( $row, 'items' ) );

		add_filter( 'sugarcommerce_table_item_cell_shop_orders', static fn( string $html, array $parts ): string => $parts['title'] . '!', 10, 2 );

		$this->assertSame( 'Trap Kit!', $table->column_default( $row, 'items' ) );
	}

	/**
	 * The drawing inside a tile.
	 *
	 * @param string $img The tile.
	 *
	 * @return string
	 */
	private function svg( string $img ): string {
		preg_match( '/base64,([^"]+)"/', $img, $match );

		return (string) base64_decode( $match[1] ?? '' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- reading back a drawn tile.
	}
}
