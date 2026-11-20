<?php
/**
 * Inline edit tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\InlineEdit;
use SugarCommerce\Tables\Editing\InlineEditSave;
use SugarCommerce\Tables\Editing\Routes;
use SugarCommerce\Tables\Tables;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use RuntimeException;

/**
 * Applying one change to a selection.
 *
 * The dangerous shape is a bulk editor that writes its own defaults: open
 * it, press Update, and forty rows become drafts. Core avoids that with a
 * "— No Change —" option carrying the sentinel `-1`, and everything here is
 * about that sentinel holding.
 *
 * The rest is the plumbing that fails silently when it is wrong -- reading
 * the ids under a name nothing posts, or accepting a value the select never
 * offered.
 */
final class InlineEditTest extends TestCase {

	/**
	 * A table that can bulk edit its status.
	 *
	 * @return array<string, mixed>
	 */
	private function config(): array {
		return [
			'labels'    => [ 'plural' => 'products' ],
			'bulk_edit' => [
				'status' => [
					'label'   => 'Status',
					'options' => [ 'active' => 'Active', 'draft' => 'Draft' ],
				],
			],
		];
	}

	/**
	 * A submitted edit, as the form would post it.
	 *
	 * @param array<string, mixed> $extra Anything to add or override.
	 *
	 * @return void
	 */
	private function submit( array $extra = [] ): void {
		$_REQUEST = array_merge(
			[
				'bulk_edit'        => 'Update',
				'table_id'         => 'demo',
				'_bulk_edit_nonce' => 'valid',
				'products'         => [ '4', '5', '6' ],
				'status'           => 'draft',
			],
			$extra
		);
	}

	/**
	 * What the edit fired with, if it fired.
	 *
	 * @return array<int, mixed>|null
	 */
	private function applied(): ?array {
		$fired = null;

		add_action(
			'sugarcommerce_table_bulk_edit_demo',
			static function ( $ids, $values ) use ( &$fired ): void {
				$fired = [ $ids, $values ];
			}
		);

		InlineEditSave::process_bulk_edit( 'demo', $this->config() );

		return $fired;
	}

	protected function setUp(): void {
		rt_reset_globals();

		$_REQUEST = [];
	}

	/**
	 * A chosen value reaches the selection.
	 */
	public function test_a_submitted_edit_applies(): void {
		$this->submit();

		$this->assertSame( [ [ 4, 5, 6 ], [ 'status' => 'draft' ] ], $this->applied() );
	}

	/**
	 * "No change" changes nothing.
	 *
	 * Core's sentinel is -1 rather than an empty string, because an empty
	 * string is a value somebody might genuinely want to set.
	 */
	public function test_no_change_applies_nothing(): void {
		$this->submit( [ 'status' => '-1' ] );

		$this->assertNull( $this->applied() );
	}

	/**
	 * A field the form did not post is left alone.
	 */
	public function test_an_absent_field_applies_nothing(): void {
		$this->submit();

		unset( $_REQUEST['status'] );

		$this->assertNull( $this->applied() );
	}

	/**
	 * A value the select never offered is refused.
	 */
	public function test_an_unoffered_value_is_refused(): void {
		$this->submit( [ 'status' => 'deleted' ] );

		$this->assertNull( $this->applied() );
	}

	/**
	 * The ids are read under the name the checkboxes post them with.
	 *
	 * Which is the plural label, not "ids". Reading the wrong one would have
	 * found nothing, every time, without complaining.
	 */
	public function test_ids_come_from_the_plural_name(): void {
		$this->submit();

		unset( $_REQUEST['products'] );

		$_REQUEST['ids'] = [ '4' ];

		$this->assertNull( $this->applied() );
	}

	/**
	 * Nothing selected does nothing.
	 */
	public function test_an_empty_selection_does_nothing(): void {
		$this->submit( [ 'products' => [] ] );

		$this->assertNull( $this->applied() );
	}

	/**
	 * Another table's submission is not this table's.
	 *
	 * Two list screens can be registered by one plugin, and a stray table_id
	 * would let one apply the other's edit.
	 */
	public function test_another_tables_submission_is_ignored(): void {
		$this->submit( [ 'table_id' => 'somethingelse' ] );

		$this->assertNull( $this->applied() );
	}

	/**
	 * A bad nonce is refused.
	 */
	public function test_a_bad_nonce_is_refused(): void {
		$this->submit();

		// The stub verifies against this rather than the value, so the value
		// stays realistic and the refusal is the thing being asserted.
		$GLOBALS['rt_nonce_ok'] = false;

		$this->assertNull( $this->applied() );

		unset( $GLOBALS['rt_nonce_ok'] );
	}

	/**
	 * The bulk editor asks for the edit capability, not the view one.
	 *
	 * A table can be readable by anyone who can see the menu and editable
	 * only by a manager -- that is what `capabilities['edit']` declares --
	 * and the editor used to check the view capability, so declaring the
	 * edit one changed nothing.
	 */
	public function test_a_bulk_edit_needs_the_edit_capability(): void {
		$config = $this->config() + [
			'capability'   => 'read',
			'capabilities' => [ 'edit' => 'edit_products' ],
		];

		$fired = null;

		add_action(
			'sugarcommerce_table_bulk_edit_demo',
			static function ( $ids, $values ) use ( &$fired ): void {
				$fired = [ $ids, $values ];
			}
		);

		$this->submit();

		// The view capability alone, which is enough to reach the page.
		$GLOBALS['rt_caps'] = [ 'read' ];

		InlineEditSave::process_bulk_edit( 'demo', $config );

		$this->assertNull( $fired );

		// With the edit one, the change goes through.
		$GLOBALS['rt_caps'] = [ 'read', 'edit_products' ];

		InlineEditSave::process_bulk_edit( 'demo', $config );

		$this->assertSame( [ [ 4, 5, 6 ], [ 'status' => 'draft' ] ], $fired );
	}

	/**
	 * A table with nothing to bulk edit renders nothing and does nothing.
	 */
	public function test_a_table_without_bulk_edit_is_inert(): void {
		$this->submit();

		$this->assertFalse( InlineEdit::has_bulk_edit( [ 'labels' => [ 'plural' => 'products' ] ] ) );

		ob_start();
		InlineEdit::render_inline_rows( 'demo', [ 'labels' => [] ], 5 );

		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * The row carries core's classes, because core styles them.
	 *
	 * Matching core is what makes this need no stylesheet: list-tables.css is
	 * already loaded on every list screen.
	 */
	public function test_the_row_uses_core_markup(): void {
		ob_start();
		InlineEdit::render_inline_rows( 'demo', $this->config(), 5 );
		$html = (string) ob_get_clean();

		foreach (
			[
				'id="bulk-edit"',
				'inline-edit-row',
				'bulk-edit-row',
				'inline-edit-wrapper',
				'id="bulk-titles"',
				'colspanchange',
				'colspan="5"',
				'name="bulk_edit"',
				'<div class="notice notice-error notice-alt inline hidden"><p class="error"></p></div>',
			] as $marker
		) {
			$this->assertStringContainsString( $marker, $html, $marker );
		}

		// And the sentinel is first, so opening the row and pressing Update
		// changes nothing.
		$this->assertMatchesRegularExpression( '/<select name="status">\s*<option value="-1"/', $html );
	}

	/**
	 * Each row carries the title the editor lists it under.
	 */
	public function test_a_row_carries_its_title(): void {
		$html = InlineEdit::inline_data( 12, 'Deep House Kit' );

		$this->assertStringContainsString( 'id="inline_12"', $html );
		$this->assertStringContainsString( 'row_title', $html );
		$this->assertStringContainsString( 'Deep House Kit', $html );
	}

	/**
	 * A title with markup in it is escaped.
	 */
	public function test_a_title_is_escaped(): void {
		$html = InlineEdit::inline_data( 1, '<script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script>', $html );
	}

	/* =========================================================================
	 * Quick Edit
	 * ========================================================================= */

	/**
	 * A table that can quick edit one row.
	 *
	 * @return array<string, mixed>
	 */
	private function quick_config(): array {
		return [
			'labels'     => [ 'plural' => 'products' ],
			'capability' => 'manage_options',
			'quick_edit' => [
				'status' => [
					'label'   => 'Status',
					'options' => [ 'active' => 'Active', 'draft' => 'Draft' ],
				],
				'sku'    => [ 'label' => 'SKU' ],
			],
		];
	}

	/**
	 * Register a table and send a quick edit to its route.
	 *
	 * @param array<string, mixed> $post   What the row submits.
	 * @param array<string, mixed> $config Table configuration.
	 *
	 * @return void
	 */
	private function quick_submit( array $post, array $config = [] ): void {
		Tables::register( 'demo', $config ?: $this->quick_config() );

		// Through the REST route as a request reaches it: its permission
		// check first, then the save.
		if ( ! Routes::may_quick_edit( 'demo' ) ) {
			return;
		}

		$request = new WP_REST_Request( 'POST', '/' );
		$request->set_param( 'id', 9 );
		$request->set_body_params( $post );

		Routes::quick_edit( 'demo', $request );
	}

	/**
	 * A quick edit reaches the handler with the row and its values.
	 */
	public function test_a_quick_edit_applies(): void {
		$seen = [];

		add_action(
			'sugarcommerce_table_quick_edit_demo',
			static function ( $id, $values ) use ( &$seen ): void {
				$seen = [ $id, $values ];
			}
		);

		$this->quick_submit( [ 'status' => 'draft', 'sku' => 'DHK-01' ] );

		$this->assertSame( 9, $seen[0] ?? null );
		$this->assertSame( [ 'status' => 'draft', 'sku' => 'DHK-01' ], $seen[1] ?? null );
	}

	/**
	 * A value the select never offered is refused.
	 *
	 * The markup constrains a browser and nothing constrains a POST, so this
	 * is the only thing standing between a hand-rolled request and any status
	 * the sender fancies.
	 */
	public function test_a_quick_edit_refuses_an_unoffered_value(): void {
		$seen = null;

		add_action(
			'sugarcommerce_table_quick_edit_demo',
			static function ( $id, $values ) use ( &$seen ): void {
				$seen = $values;
			}
		);

		$this->quick_submit( [ 'status' => 'deleted', 'sku' => 'DHK-01' ] );

		// The SKU is a text field and survives; the status does not.
		$this->assertSame( [ 'sku' => 'DHK-01' ], $seen );
	}

	/**
	 * Quick Edit is a REST route of the table's own: no admin-ajax action
	 * is left to answer it, and the route asks the edit capability.
	 */
	public function test_a_quick_edit_is_a_rest_route(): void {
		Tables::register( 'demo', $this->quick_config() );
		Routes::register_routes();

		$route = $GLOBALS['rt_routes']['sugarcommerce-tables/v1/demo/quick-edit/(?P<id>\d+)'] ?? null;

		$this->assertNotNull( $route );
		$this->assertSame( 'POST', $route['methods'] );
		$this->assertSame( 'integer', $route['args']['id']['type'] );
		$this->assertSame( 1, $route['args']['id']['minimum'] );
		$this->assertArrayNotHasKey( 'wp_ajax_sugarcommerce_tables_quick_edit', $GLOBALS['rt_actions'] ?? [] );

		$GLOBALS['rt_caps'] = [ 'read' ];
		$this->assertFalse( ( $route['permission_callback'] )() );
	}

	/**
	 * A row that is not one is refused, and nothing fires.
	 */
	public function test_a_quick_edit_of_no_row_is_refused(): void {
		Tables::register( 'demo', $this->quick_config() );

		$request = new WP_REST_Request( 'POST', '/' );
		$request->set_param( 'id', 0 );
		$request->set_body_params( [ 'status' => 'draft' ] );

		$answer = Routes::quick_edit( 'demo', $request );

		$this->assertInstanceOf( \WP_Error::class, $answer );
		$this->assertSame( 400, $answer->get_error_data()['status'] );
		$this->assertNotContains( 'sugarcommerce_table_quick_edit_demo', (array) ( $GLOBALS['rt_fired'] ?? [] ) );
	}

	/**
	 * Without the capability, nothing happens.
	 */
	public function test_a_quick_edit_needs_the_capability(): void {
		$GLOBALS['rt_caps'] = [ 'read' ];

		$this->quick_submit( [ 'status' => 'draft' ] );

		$this->assertNotContains( 'sugarcommerce_table_quick_edit_demo', (array) ( $GLOBALS['rt_fired'] ?? [] ) );
	}

	/**
	 * Quick edit asks for the edit capability too.
	 */
	public function test_a_quick_edit_needs_the_edit_capability(): void {
		$config = array_merge(
			$this->quick_config(),
			[
				'capability'   => 'read',
				'capabilities' => [ 'edit' => 'edit_products' ],
			]
		);

		$GLOBALS['rt_caps'] = [ 'read' ];

		$this->quick_submit( [ 'status' => 'draft' ], $config );

		$this->assertNotContains( 'sugarcommerce_table_quick_edit_demo', (array) ( $GLOBALS['rt_fired'] ?? [] ) );

		$GLOBALS['rt_caps'] = [ 'read', 'edit_products' ];

		$this->quick_submit( [ 'status' => 'draft' ], $config );

		$this->assertContains( 'sugarcommerce_table_quick_edit_demo', (array) ( $GLOBALS['rt_fired'] ?? [] ) );
	}

	/**
	 * A table that never declared quick edit cannot be quick edited.
	 *
	 * The endpoint is one URL for every table on the site, so the only thing
	 * scoping it is this check reading the table's own configuration.
	 */
	public function test_a_table_without_quick_edit_refuses(): void {
		$this->quick_submit(
			[ 'status' => 'draft' ],
			[ 'labels' => [ 'plural' => 'products' ] ]
		);

		$this->assertNotContains( 'sugarcommerce_table_quick_edit_demo', (array) ( $GLOBALS['rt_fired'] ?? [] ) );
	}

	/**
	 * A field added by the filter saves as well as renders.
	 *
	 * Reading the raw config to save would give an add-on a control that
	 * appears in the row, accepts a value, and quietly drops it.
	 */
	public function test_a_filtered_field_saves(): void {
		$seen = null;

		add_filter(
			'sugarcommerce_table_quick_edit_fields_demo',
			static function ( $fields ) {
				$fields['vendor'] = [ 'label' => 'Vendor' ];

				return $fields;
			}
		);

		add_action(
			'sugarcommerce_table_quick_edit_demo',
			static function ( $id, $values ) use ( &$seen ): void {
				$seen = $values;
			}
		);

		$this->quick_submit( [ 'vendor' => 'Acme' ] );

		$this->assertSame( [ 'vendor' => 'Acme' ], $seen );
	}

	/**
	 * The same filter reaches the bulk row too.
	 */
	public function test_a_filtered_field_saves_in_bulk(): void {
		$seen = null;

		add_filter(
			'sugarcommerce_table_bulk_edit_fields_demo',
			static function ( $fields ) {
				$fields['vendor'] = [ 'label' => 'Vendor' ];

				return $fields;
			}
		);

		add_action(
			'sugarcommerce_table_bulk_edit_demo',
			static function ( $ids, $values ) use ( &$seen ): void {
				$seen = $values;
			}
		);

		$this->submit( [ 'vendor' => 'Acme' ] );

		InlineEditSave::process_bulk_edit( 'demo', $this->config() );

		$this->assertSame( 'Acme', $seen['vendor'] ?? null );
	}

	/**
	 * A table declaring only one editor gets the other's fields.
	 */
	public function test_one_declaration_serves_both_editors(): void {
		$config = $this->quick_config();

		$this->assertSame(
			array_keys( $config['quick_edit'] ),
			array_keys( InlineEdit::fields( 'demo', $config, 'bulk_edit' ) )
		);
	}

	/**
	 * The quick row carries its table and row; the REST nonce replaces its own.
	 *
	 * Without the hidden table_id the endpoint cannot tell which table is
	 * saving, and refuses every request -- from a row that looks fine.
	 */
	public function test_the_quick_row_carries_its_table(): void {
		ob_start();

		try {
			InlineEdit::render_inline_rows( 'demo', $this->quick_config(), 5 );
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'id="inline-edit"', $html );
		$this->assertStringContainsString( 'name="table_id" value="demo"', $html );
		$this->assertStringContainsString( 'name="item_id"', $html );
		$this->assertStringNotContainsString( 'name="_inline_edit"', $html );
		$this->assertStringContainsString( 'class="button button-primary save"', $html );

		// Both rows render, from a config that declared only quick_edit.
		// Asking for the literal bulk_edit key here once left "Bulk edit" in
		// the dropdown opening nothing, with every test still green.
		$this->assertStringContainsString( 'id="bulk-edit"', $html );
		$this->assertStringContainsString( 'name="_bulk_edit_nonce"', $html );

		preg_match( '#<tr id="inline-edit".*?</tr>#s', $html, $quick );
		preg_match( '#<tr id="bulk-edit".*?</tr>#s', $html, $bulk );

		// No "No Change" sentinel on the quick row: it edits one row, and
		// every field shows what that row currently is. The bulk row is the
		// opposite -- without the sentinel, opening it and pressing Update
		// would set every field on every selected row.
		$this->assertStringNotContainsString( '<option value="-1"', $quick[0] ?? '' );
		$this->assertStringContainsString( '<option value="-1"', $bulk[0] ?? '' );
	}

	/**
	 * A row's hidden block names each div for the input it fills.
	 */
	public function test_the_data_block_names_its_fields(): void {
		$html = InlineEdit::inline_data( 9, 'Deep House Kit', [ 'status' => 'draft' ] );

		$this->assertStringContainsString( '<div class="row_title">Deep House Kit</div>', $html );
		$this->assertStringContainsString( '<div class="status">draft</div>', $html );
	}

	/* =========================================================================
	 * Layout
	 * ========================================================================= */

	/**
	 * A field label is never floated.
	 *
	 * `alignleft` is float: left. Core puts it only inside an
	 * .inline-edit-group, where two controls are meant to share a line;
	 * on a plain label it drops every field onto one row, which is what
	 * this row looked like before.
	 */
	public function test_fields_are_not_floated(): void {
		ob_start();

		try {
			InlineEdit::render_inline_rows( 'demo', $this->quick_config(), 5 );
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringNotContainsString( 'alignleft', $html );

		// Core's structure, which is what the stylesheet selects on. Without
		// span.title the label has no 6em column and the control sits hard
		// against the text.
		$this->assertMatchesRegularExpression(
			'/<label class="inline-edit-status">\s*<span class="title">/',
			$html
		);
	}

	/**
	 * Quick edit fills both columns; bulk edit fills only the right.
	 *
	 * The left column of a bulk row is the list of what was selected, so
	 * fields there would sit under it. Quick edit has one text input on the
	 * left, and putting every field on the right leaves a column of one
	 * beside a column of three -- which is not what posts looks like.
	 */
	public function test_the_columns_are_balanced(): void {
		$config = $this->quick_config();

		// Three fields: one joins the title on the left, two on the right.
		$config['quick_edit']['vendor'] = [ 'label' => 'Vendor' ];

		ob_start();

		try {
			InlineEdit::render_inline_rows( 'demo', $config, 5 );
		} finally {
			$html = (string) ob_get_clean();
		}

		preg_match( '#<tr id="inline-edit".*?</tr>#s', $html, $quick );
		preg_match( '#<tr id="bulk-edit".*?</tr>#s', $html, $bulk );

		$left = static function ( string $row ): string {
			preg_match( '#col-left.*?</fieldset>#s', $row, $m );

			return $m[0] ?? '';
		};

		$this->assertStringContainsString( 'name="status"', $left( $quick[0] ) );
		$this->assertStringNotContainsString( 'name="vendor"', $left( $quick[0] ) );

		// The bulk row's left column holds the selection, nothing else.
		$this->assertStringContainsString( 'id="bulk-titles"', $left( $bulk[0] ) );
		$this->assertStringNotContainsString( 'name="status"', $left( $bulk[0] ) );
	}

	/**
	 * The row's name is introduced by the column it comes from.
	 */
	public function test_the_row_title_uses_the_primary_column_label(): void {
		$config = $this->quick_config() + [
			'primary_column' => 'title',
			'columns'        => [ 'title' => [ 'label' => 'Product' ] ],
		];

		ob_start();

		try {
			InlineEdit::render_inline_rows( 'demo', $config, 5 );
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertMatchesRegularExpression(
			'/<span class="title">Product<\/span>\s*<strong class="inline-edit-row-title-text" data-row-title>/',
			$html
		);
	}

	/**
	 * The quick editor names its row and offers nothing to type there.
	 *
	 * The box that used to be here was never saved, so whatever was typed
	 * into it was discarded.
	 */
	public function test_the_quick_editor_has_no_title_box(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/InlineEdit.php' );

		$this->assertStringNotContainsString( 'name="row_title"', $source );
		$this->assertStringContainsString( 'data-row-title', $source );
	}

	/**
	 * A table that lists its first column in `quick_edit` gets a real field
	 * for it, and no line repeating the name above.
	 */
	public function test_an_editable_first_column_is_a_field(): void {
		$config = $this->quick_config() + [
			'primary_column' => 'title',
			'columns'        => [ 'title' => [ 'label' => 'Product' ] ],
		];

		$config['quick_edit'] = [ 'title' => [ 'label' => 'Title' ] ] + (array) $config['quick_edit'];

		ob_start();

		try {
			InlineEdit::render_inline_rows( 'demo', $config, 5 );
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( '<input type="text" name="title"', $html );
		$this->assertStringNotContainsString( 'data-row-title', $html );
	}
}
