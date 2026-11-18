<?php
/**
 * Status transition tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\ActionNotice;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Outcome;
use SugarCommerce\Tables\Sources\BerlinDB;
use SugarCommerce\Tables\Table;
use SugarCommerce\Tables\Transitions;
use SugarCommerce\Tables\Tests\Fixtures\FakeQuery;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/berlindb-query.php';

/**
 * A row offers its next move, and the list says what moved, by name.
 *
 * What is worth pinning: a row never offers the state it is in, a move
 * writes through the one setter, a row already in the state is said to be
 * so rather than counted as changed, and the sentence uses the record's
 * own word for the status.
 */
final class TransitionsTest extends TestCase {

	/**
	 * A clean request, and three customers.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET     = [];
		$_REQUEST = [];

		FakeQuery::seed(
			[
				[ 'id' => 1, 'name' => 'Elena Okafor', 'status' => 'active' ],
				[ 'id' => 2, 'name' => 'Kira Ito', 'status' => 'disabled' ],
				[ 'id' => 3, 'name' => 'Ben Tanaka', 'status' => 'active' ],
			]
		);
	}

	/**
	 * A customers table that blocks and unblocks.
	 *
	 * @param array<string, mixed> $config Anything else.
	 *
	 * @return array<string, mixed> The registered configuration.
	 */
	private function register( array $config = [] ): array {
		Tables::register(
			'people',
			array_merge(
				[
					'menu_slug'    => 'people',
					'labels'       => [ 'singular' => 'customer', 'plural' => 'customers' ],
					'columns'      => [ 'name' => 'Name' ],
					'views'        => [ 'active' => 'Active', 'disabled' => 'Blocked' ],
					'callbacks'    => BerlinDB::callbacks( [ 'query' => FakeQuery::class ] ),
					'row_actions'  => [ 'edit' => [ 'label' => 'Edit', 'flyout' => true ] ],
					'transitions'  => [
						'active'   => [ 'disabled' => 'Block' ],
						'disabled' => [ 'active' => 'Unblock' ],
					],
					'bulk_actions' => [
						'block'   => [ 'label' => 'Block', 'status' => 'disabled' ],
						'unblock' => [ 'label' => 'Unblock', 'status' => 'active' ],
					],
				],
				$config
			)
		);

		return (array) Tables::get_table( 'people' );
	}

	/**
	 * The labels a row in a status is offered.
	 *
	 * @param string $status The row's status.
	 *
	 * @return array<int, string>
	 */
	private function offered( string $status ): array {
		$table  = new Table( 'people', (array) Tables::get_table( 'people' ) );
		$method = new \ReflectionMethod( $table, 'handle_row_actions' );
		$html   = (string) $method->invoke( $table, (object) [ 'id' => 9, 'name' => 'Row', 'status' => $status ], 'name', 'name' );

		preg_match_all( '/<a [^>]*>([^<]+)<\/a>/', $html, $labels );

		// Delete is the library's own, from the delete callback.
		return array_values( array_diff( $labels[1], [ 'Delete' ] ) );
	}

	/**
	 * Run an action and return what the list said.
	 *
	 * @param array<string, mixed> $request The request.
	 *
	 * @return array{message: string, type: string}|null
	 */
	private function act( array $request ): ?array {
		$_GET     = $request + [
			'page'     => 'people',
			'_wpnonce' => 'nonce',
		];
		$_REQUEST = $_GET;

		try {
			Tables::process_early_actions();
		} catch ( \RT_Redirected $redirect ) {
			parse_str( (string) parse_url( $redirect->location, PHP_URL_QUERY ), $query );

			return ActionNotice::take( (string) ( $query['_notice'] ?? '' ) );
		}

		$this->fail( 'There was no redirect.' );
	}

	/**
	 * A row offers its next move and never the state it is already in.
	 */
	public function test_a_row_offers_its_next_move(): void {
		$this->register();

		$this->assertSame( [ 'Block' ], $this->offered( 'active' ) );
		$this->assertSame( [ 'Unblock' ], $this->offered( 'disabled' ) );
		$this->assertSame( [], $this->offered( 'archived' ) );
	}

	/**
	 * The move is a row action of the table's own, after what it declared.
	 */
	public function test_the_moves_follow_the_declared_actions(): void {
		$config = $this->register();

		$this->assertSame( [ 'edit', 'status_active_disabled', 'status_disabled_active' ], array_keys( $config['row_actions'] ) );
		$this->assertSame( 'disabled', $config['row_actions']['status_active_disabled']['to'] );
	}

	/**
	 * A row's move writes through the update callback, and says so by name
	 * in the view's word for the status.
	 */
	public function test_a_row_move_sets_the_status_and_says_so(): void {
		$this->register();

		$said = $this->act( [ 'action' => 'status_active_disabled', 'item' => '1' ] );

		$this->assertSame( 'disabled', FakeQuery::$rows[1]['status'] );
		$this->assertSame( [ 'message' => 'Elena Okafor is now blocked.', 'type' => 'success' ], $said );
	}

	/**
	 * A setter of the plugin's own wins over the update, and a false from it
	 * is a failure.
	 */
	public function test_a_setter_of_its_own_is_used(): void {
		$set = [];

		$this->register(
			[
				'callbacks'    => BerlinDB::callbacks( [ 'query' => FakeQuery::class ] ) + [
					'set_status' => static function ( int $id, string $status ) use ( &$set ): bool {
						$set[] = [ $id, $status ];

						return 3 !== $id;
					},
				],
				'status_label' => static fn( string $status ): string => 'disabled' === $status ? 'Shut Out' : 'Let In',
			]
		);

		$said = $this->act( [ 'action' => 'status_active_disabled', 'item' => '1' ] );

		$this->assertSame( [ [ 1, 'disabled' ] ], $set );
		$this->assertSame( 'active', FakeQuery::$rows[1]['status'], 'The update was used as well as the setter.' );
		$this->assertSame( 'Elena Okafor is now shut out.', $said['message'] );

		$said = $this->act( [ 'action' => 'status_active_disabled', 'item' => '3' ] );

		$this->assertSame( 'error', $said['type'] );
	}

	/**
	 * A bulk move sets every row, and a row already there is said to be.
	 */
	public function test_a_bulk_move_names_a_row_already_there(): void {
		$this->register();

		$said = $this->act(
			[
				'action'    => 'block',
				'customers' => [ '1', '2' ],
			]
		);

		$this->assertSame( 'disabled', FakeQuery::$rows[1]['status'] );
		$this->assertSame(
			[
				'message' => 'Elena Okafor is now blocked. 1 could not be changed: Kira Ito is already blocked.',
				'type'    => 'warning',
			],
			$said
		);

		// Only the row that moved was written.
		$this->assertSame( [ [ 'update', 1, [ 'status' => 'disabled' ] ] ], FakeQuery::$writes );
	}

	/**
	 * Many rows are counted, in the plural label and the status's word.
	 */
	public function test_many_rows_are_counted(): void {
		$config = $this->register();

		$many = Transitions::messages( $config, 'disabled' )['many'];

		$this->assertSame( '4 customers are now blocked.', $many( 4 ) );
		$this->assertSame( '1 customers is now blocked.', $many( 1 ) );
	}

	/**
	 * A table's own wording and its own callback are left alone.
	 */
	public function test_a_declared_bulk_action_is_left_alone(): void {
		$config = $this->register(
			[
				'bulk_actions' => [
					'block' => [
						'label'    => 'Block',
						'status'   => 'disabled',
						'messages' => [ 'one' => '%s is out.' ],
						'callback' => static fn(): bool => true,
					],
				],
			]
		);

		$this->assertSame( [ 'one' => '%s is out.' ], $config['bulk_actions']['block']['messages'] );
		$this->assertTrue( ( $config['bulk_actions']['block']['callback'] )( [ 1 ] ) );
	}

	/**
	 * With no way to write a status, nothing is offered.
	 */
	public function test_no_setter_offers_nothing(): void {
		$config = $this->register( [ 'callbacks' => [ 'get_items' => static fn(): array => [] ] ] );

		$this->assertSame( [ 'edit' ], array_keys( $config['row_actions'] ) );
		$this->assertArrayNotHasKey( 'callback', $config['bulk_actions']['block'] );
	}

	/**
	 * An editor that set a status says it in the same words; one that set
	 * none is said in the library's.
	 */
	public function test_an_edit_that_set_a_status_says_it(): void {
		$this->register();

		$words = Transitions::edited_messages( 'people' );

		$said = $words(
			[
				'kind'   => 'quick_edit',
				'done'   => [ 1 ],
				'failed' => [],
				'names'  => [ 1 => 'Elena Okafor' ],
				'values' => [ 'status' => 'disabled' ],
			]
		);

		$this->assertSame( 'Elena Okafor is now blocked.', $said['message'] );

		$said = $words(
			[
				'kind'   => 'quick_edit',
				'done'   => [ 1 ],
				'failed' => [],
				'names'  => [ 1 => 'Elena Okafor' ],
				'values' => [ 'name' => 'E' ],
			]
		);

		$this->assertSame( 'Elena Okafor updated.', $said['message'] );
	}

	/**
	 * A handler of the plugin's own can say "already" the same way.
	 */
	public function test_already_is_said_by_name(): void {
		$this->register();

		Outcome::reset();
		Transitions::already( 'people', (object) [ 'id' => 2, 'name' => 'Kira Ito' ], 'disabled' );

		$this->assertSame( [ 2 => 'Kira Ito is already blocked' ], Outcome::failed() );
	}
}
