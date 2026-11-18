<?php
/**
 * Inline checklist tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\InlineChecklist;
use SugarCommerce\Tables\InlineEdit;
use SugarCommerce\Tables\InlineEditSave;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Table;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A checklist in the inline editors, as core's categories are.
 *
 * What fails quietly here is the bulk arithmetic: a half-ticked box that
 * took every row out of something only some were in, or an untouched editor
 * that changed rows by being opened and saved. The rest is the plumbing --
 * the sentinel that makes "nothing ticked" a submission, and the hidden data
 * the script fills the boxes from.
 */
final class InlineChecklistTest extends TestCase {

	/**
	 * The collections a product can be in.
	 *
	 * @return array<string, mixed>
	 */
	private function field(): array {
		return [
			'type'    => 'checklist',
			'label'   => 'Collections',
			'options' => [
				3 => 'Drums',
				5 => 'Loops',
				8 => 'Vocals',
			],
		];
	}

	/**
	 * A table with a status select and a collections checklist.
	 *
	 * @return array<string, mixed>
	 */
	private function config(): array {
		return [
			'labels'     => [ 'plural' => 'products' ],
			'capability' => 'manage_options',
			'columns'    => [ 'title' => 'Title' ],
			'callbacks'  => [ 'get_items' => static fn() => [] ],
			'quick_edit' => [
				'status'      => [
					'label'   => 'Status',
					'options' => [ 'active' => 'Active', 'draft' => 'Draft' ],
				],
				'collections' => $this->field(),
			],
		];
	}

	/**
	 * Both editors, drawn.
	 *
	 * @param array<string, mixed> $config Table configuration.
	 *
	 * @return array{0: string, 1: string} The quick row and the bulk row.
	 */
	private function rows( array $config ): array {
		ob_start();

		try {
			InlineEdit::render_inline_rows( 'demo', $config, 5 );
		} finally {
			$html = (string) ob_get_clean();
		}

		preg_match( '#<tr id="inline-edit".*?</tr>#s', $html, $quick );
		preg_match( '#<tr id="bulk-edit".*?</tr>#s', $html, $bulk );

		return [ $quick[0] ?? '', $bulk[0] ?? '' ];
	}

	/**
	 * What a bulk edit handed over, if it fired.
	 *
	 * @param array<string, mixed> $request What the form posted.
	 *
	 * @return array<string, mixed>|null
	 */
	private function bulk( array $request ): ?array {
		$_REQUEST = array_merge(
			[
				'bulk_edit'        => 'Update',
				'table_id'         => 'demo',
				'_bulk_edit_nonce' => 'valid',
				'products'         => [ '4', '5' ],
				'status'           => '-1',
			],
			$request
		);

		$fired = null;

		add_action(
			'sugarcommerce_table_bulk_edit_demo',
			static function ( $ids, $values ) use ( &$fired ): void {
				$fired = $values;
			}
		);

		InlineEditSave::process_bulk_edit( 'demo', $this->config() );

		return $fired;
	}

	/**
	 * What a quick edit handed over.
	 *
	 * @param array<string, mixed> $post What the row posted.
	 *
	 * @return array<string, mixed>|null
	 */
	private function quick( array $post ): ?array {
		Tables::register( 'demo', $this->config() );

		$_POST    = array_merge(
			[
				'table_id'     => 'demo',
				'item_id'      => '9',
				'_inline_edit' => 'valid',
			],
			$post
		);
		$_REQUEST = $_POST;

		$seen = null;

		add_action(
			'sugarcommerce_table_quick_edit_demo',
			static function ( $id, $values ) use ( &$seen ): void {
				$seen = $values;
			}
		);

		try {
			InlineEditSave::handle_quick_edit();
		} catch ( RuntimeException $e ) {
			// wp_die(), which ends every quick edit.
			unset( $e );
		}

		return $seen;
	}

	protected function setUp(): void {
		rt_reset_globals();

		$_POST    = [];
		$_REQUEST = [];
	}

	/* =========================================================================
	 * Markup
	 * ========================================================================= */

	/**
	 * Core's categories markup, in its own fieldset between the other two.
	 */
	public function test_the_checklist_is_cores_markup(): void {
		[ $quick ] = $this->rows( $this->config() );

		$this->assertMatchesRegularExpression(
			'#</fieldset>\s*<fieldset class="inline-edit-col-center inline-edit-categories">.*?</fieldset>\s*<fieldset class="inline-edit-col-right">#s',
			$quick
		);
		$this->assertStringContainsString( '<span class="title inline-edit-categories-label">Collections</span>', $quick );
		$this->assertStringContainsString( '<ul class="cat-checklist collections-checklist" data-checklist="collections">', $quick );
		$this->assertStringContainsString( '<label class="selectit"><input value="5" type="checkbox" name="collections[]" id="in-quick-collections-5" /> Loops</label>', $quick );
	}

	/**
	 * Nothing ticked is still a submission, so a row can be taken out of
	 * everything.
	 */
	public function test_the_checklist_posts_a_sentinel(): void {
		[ $quick, $bulk ] = $this->rows( $this->config() );

		foreach ( [ $quick, $bulk ] as $row ) {
			$this->assertMatchesRegularExpression(
				'#<input type="hidden" name="collections\[\]" value="" />\s*<ul class="cat-checklist#',
				$row
			);
		}
	}

	/**
	 * A row with a checklist takes core's "post" shape, which sizes three
	 * columns; one without keeps the "page" shape and its two halves.
	 */
	public function test_a_checklist_row_is_shaped_as_cores_post_row(): void {
		[ $quick, $bulk ] = $this->rows( $this->config() );

		$this->assertStringContainsString( 'quick-edit-row-post', $quick );
		$this->assertStringContainsString( 'bulk-edit-row-post', $bulk );

		$config = $this->config();
		unset( $config['quick_edit']['collections'] );

		[ $quick ] = $this->rows( $config );

		$this->assertStringContainsString( 'quick-edit-row-page', $quick );
		$this->assertStringNotContainsString( 'inline-edit-col-center', $quick );
	}

	/**
	 * The checklist is not one of the fields the two halves are balanced
	 * by: with a status beside it, the status goes right and the left holds
	 * only the row's name.
	 */
	public function test_a_checklist_is_not_counted_in_the_halves(): void {
		[ $quick ] = $this->rows( $this->config() );

		preg_match( '#col-left.*?</fieldset>#s', $quick, $left );
		preg_match( '#col-right.*?</fieldset>#s', $quick, $right );

		$this->assertStringNotContainsString( 'name="status"', $left[0] ?? '' );
		$this->assertStringContainsString( 'name="status"', $right[0] ?? '' );
		$this->assertStringNotContainsString( 'collections', ( $left[0] ?? '' ) . ( $right[0] ?? '' ) );
	}

	/**
	 * A checklist with nothing to tick draws nothing.
	 */
	public function test_an_empty_checklist_draws_nothing(): void {
		$config = $this->config();

		$config['quick_edit']['collections']['options'] = [];

		[ $quick ] = $this->rows( $config );

		$this->assertStringNotContainsString( 'cat-checklist', $quick );
	}

	/* =========================================================================
	 * The row's data
	 * ========================================================================= */

	/**
	 * What a row is in, from the field's callback, as core's comma list.
	 */
	public function test_a_row_value_comes_from_the_callback(): void {
		$field          = $this->field();
		$field['value'] = static fn( $item ): array => [ 3, (int) $item->id ];

		$this->assertSame( '3,8', InlineChecklist::row_value( $field, (object) [ 'id' => 8 ], 'collections' ) );
	}

	/**
	 * Without a callback, off the row: an array or a comma list.
	 */
	public function test_a_row_value_falls_back_to_the_row(): void {
		$this->assertSame( '3,5', InlineChecklist::row_value( $this->field(), (object) [ 'collections' => [ 3, 5 ] ], 'collections' ) );
		$this->assertSame( '8', InlineChecklist::row_value( $this->field(), [ 'collections' => '8' ], 'collections' ) );
	}

	/**
	 * The checkbox column carries it, for both editors -- the bulk one counts
	 * the ticked rows' lists.
	 */
	public function test_the_data_block_carries_the_list(): void {
		$config                                       = $this->config();
		$config['bulk_actions']                       = [ 'edit' => 'Bulk edit' ];
		$config['quick_edit']['collections']['value'] = static fn(): array => [ 5, 8 ];

		Tables::register( 'demo', $config );

		$html = ( new Table( 'demo', Tables::get_table( 'demo' ) ) )->column_cb(
			(object) [
				'id'     => 9,
				'title'  => 'Deep House Kit',
				'status' => 'draft',
			]
		);

		$this->assertStringContainsString( '<div class="collections">5,8</div>', $html );
		$this->assertStringContainsString( '<div class="status">draft</div>', $html );
	}

	/* =========================================================================
	 * Quick Edit
	 * ========================================================================= */

	/**
	 * The ticked ids reach the handler, and only the ones on offer.
	 */
	public function test_a_quick_edit_hands_over_what_is_ticked(): void {
		$values = $this->quick( [ 'collections' => [ '', '8', '3', '99' ] ] );

		$this->assertSame( [ '3', '8' ], $values['collections'] ?? null );
	}

	/**
	 * Only the sentinel is "in nothing", not "leave it".
	 */
	public function test_nothing_ticked_clears_the_list(): void {
		$this->assertSame( [], $this->quick( [ 'collections' => [ '' ] ] )['collections'] ?? null );
	}

	/**
	 * A row that did not post the field leaves it alone.
	 */
	public function test_an_absent_checklist_is_left_alone(): void {
		$this->assertArrayNotHasKey( 'collections', (array) $this->quick( [ 'status' => 'draft' ] ) );
	}

	/* =========================================================================
	 * Bulk Edit
	 * ========================================================================= */

	/**
	 * Ticked is added; unticked is taken away; half-ticked is left to each
	 * row -- core's (current ∩ indeterminate) ∪ (checked - indeterminate).
	 */
	public function test_a_bulk_edit_adds_removes_and_keeps(): void {
		$values = $this->bulk(
			[
				'collections'               => [ '', '3' ],
				'indeterminate_collections' => [ '5' ],
			]
		);

		$this->assertSame(
			[
				'add'    => [ '3' ],
				'remove' => [ '8' ],
			],
			$values['collections'] ?? null
		);

		// Applied to a row in Loops and Vocals: Loops kept as it was,
		// Vocals taken away, Drums added.
		$row = array_values( array_unique( array_merge( array_diff( [ '5', '8' ], $values['collections']['remove'] ), $values['collections']['add'] ) ) );

		sort( $row );

		$this->assertSame( [ '3', '5' ], $row );
	}

	/**
	 * A box half-ticked and then ticked has lost its flag in the script; one
	 * still flagged is never added, whatever it posts as.
	 */
	public function test_a_half_ticked_box_is_never_added(): void {
		$values = $this->bulk(
			[
				'collections'               => [ '', '5' ],
				'indeterminate_collections' => [ '5' ],
			]
		);

		$this->assertNotContains( '5', $values['collections']['add'] ?? [ '5' ] );
		$this->assertNotContains( '5', $values['collections']['remove'] ?? [ '5' ] );
	}

	/**
	 * A bulk edit that did not post the checklist does not touch it, and
	 * with nothing else chosen does not fire.
	 */
	public function test_an_absent_checklist_does_not_fire(): void {
		$this->assertNull( $this->bulk( [] ) );
		$this->assertSame( [ 'status' => 'draft' ], $this->bulk( [ 'status' => 'draft' ] ) );
	}
}
