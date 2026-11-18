<?php
/**
 * Inline edit script tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use PHPUnit\Framework\TestCase;

/**
 * What the quick editor's script does to the rows around it.
 *
 * Nothing here runs the script -- that takes a browser. These hold the two
 * places it went wrong without an error anywhere, by reading what it does to
 * a data row and to a select.
 */
final class InlineEditScriptTest extends TestCase {

	/**
	 * The script.
	 *
	 * @return string
	 */
	private function script(): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/inline-edit.js' );
	}

	/**
	 * A data row is never given the editor's class.
	 *
	 * Core has `tr.inline-edit-row td { padding: 0 }`, meant for the editor.
	 * The script put that class on the row it was hiding and Cancel showed
	 * the row again still wearing it, so the row came back with every cell
	 * hard against the next. The class belongs to the markup InlineEdit
	 * prints and to nothing the script touches.
	 */
	public function test_a_data_row_is_not_given_the_editors_class(): void {
		$this->assertDoesNotMatchRegularExpression(
			'/addClass\(\s*[\'"][^\'"]*\binline-edit-row\b/',
			$this->script()
		);
	}

	/**
	 * A row whose value the select does not offer still opens showing it.
	 *
	 * The select used to be left on its first option, so a Refunded order
	 * edited through Pending / Complete / Failed / Cancelled opened reading
	 * "Pending", and Update -- with nothing changed -- made it pending. The
	 * row's own value is added to the open editor instead; the server still
	 * refuses it, which InlineEditTest holds, so submitting it changes
	 * nothing.
	 */
	public function test_an_unoffered_value_is_shown_rather_than_replaced(): void {
		$this->assertMatchesRegularExpression(
			'/!\s*self\.offers\(\s*this,\s*value\s*\)\s*\)\s*\{\s*\$\(\s*this\s*\)\.prepend\(\s*\$\(\s*\'<option>\'\s*\)\.val\(\s*value\s*\)/',
			$this->script()
		);
	}

	/**
	 * A row's title is core's shape: the link inside the <strong>.
	 *
	 * The other way round puts a block inside the link, and its focus ring
	 * falls apart.
	 */
	public function test_a_row_title_link_sits_inside_its_strong(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Traits/ColumnRenderer.php' );

		$this->assertStringNotContainsString( '"><strong>%s</strong></a>', $source );
		$this->assertStringNotContainsString( '><strong>$1</strong></a>', $source );
		$this->assertStringContainsString( '<strong><a class="row-title" href="%s">%s</a></strong>', $source );
	}

	/**
	 * A checklist's boxes are not filled by the loop that fills the rest.
	 *
	 * That loop reads one value per name and ticks a checkbox when it reads
	 * "1" -- and `collections[]` is not even a class it can look for, so a
	 * checklist would have thrown before the editor opened. Names ending in
	 * `[]` are skipped there and ticked by fillChecklists(), core's way:
	 * the row's comma list handed to the boxes as an array.
	 */
	public function test_a_checklist_is_filled_from_its_list(): void {
		$script = $this->script();

		$this->assertMatchesRegularExpression( '/if \( \/\\\\\[\\\\\]\$\/\.test\( name \) \) \{\s*return;/', $script );
		$this->assertMatchesRegularExpression( '/\$\(\s*\':checkbox\',\s*this\s*\)\.val\(\s*ids\s*\?\s*ids\.split\(\s*\',\'\s*\)/', $script );
	}

	/**
	 * In bulk, a box only some ticked rows have opens half-ticked and says
	 * so to the server, and the next selection starts clean.
	 */
	public function test_a_bulk_checklist_is_tri_state(): void {
		$script = $this->script();

		$this->assertStringContainsString( "prop( 'indeterminate', true )", $script );
		$this->assertStringContainsString( "'indeterminate_' + name + '[]'", $script );
		$this->assertStringContainsString( 'settings.someHaveThis', $script );
		$this->assertMatchesRegularExpression( '/this\.resetBulkChecklists\(\);\s*this\.setBulkChecklists\(/', $script );
	}
}
