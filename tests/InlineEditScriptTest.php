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
 * What the quick editor's markup and script agree on.
 *
 * The script's own fixes -- a data row never given the editor's class, a
 * value the select does not offer shown rather than replaced, a checklist
 * filled from its comma list, the bulk checklists tri-state -- are held by
 * tests/js/inline-edit.test.js, which opens the editors rather than reading
 * the source. This holds the row markup they are opened over.
 */
final class InlineEditScriptTest extends TestCase {

	/**
	 * A row's title is core's shape: the link inside the <strong>.
	 *
	 * The other way round puts a block inside the link, and its focus ring
	 * falls apart.
	 */
	public function test_a_row_title_link_sits_inside_its_strong(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Traits/Table/ColumnRenderer.php' );

		$this->assertStringNotContainsString( '"><strong>%s</strong></a>', $source );
		$this->assertStringNotContainsString( '><strong>$1</strong></a>', $source );
		$this->assertStringContainsString( '<strong><a class="row-title" href="%s">%s</a></strong>', $source );
	}
}
