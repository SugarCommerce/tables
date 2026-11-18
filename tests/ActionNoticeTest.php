<?php
/**
 * Action notice tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\ActionNotice;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Outcome;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * What the list says after an action: what happened, to what.
 *
 * What is worth pinning: rows are named by the list's own title, a few by
 * name and many by count, a failure says which and why, the words are the
 * consumer's, and only a token crosses the redirect -- single-use, and
 * readable only by whoever did the action.
 */
final class ActionNoticeTest extends TestCase {

	/**
	 * The people the table holds, by id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const PEOPLE = [
		1 => [ 'id' => 1, 'name' => 'Elena Okafor', 'status' => 'active' ],
		2 => [ 'id' => 2, 'name' => 'Kira Ito', 'status' => 'active' ],
		3 => [ 'id' => 3, 'name' => 'Ben <b>Tanaka</b>', 'status' => 'active' ],
		4 => [ 'id' => 4, 'name' => 'Ana Silva', 'status' => 'active' ],
		5 => [ 'id' => 5, 'name' => 'Raj Patel', 'status' => 'active' ],
	];

	/**
	 * A clean request between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];

		$GLOBALS['rt_gone'] = [];
	}

	/**
	 * A customers table with a Block action, worded by the consumer.
	 *
	 * @param array $config Anything else.
	 *
	 * @return array The registered configuration.
	 */
	private function register( array $config = [] ): array {
		Tables::register(
			'people',
			array_merge(
				[
					'menu_slug'    => 'people',
					'labels'       => [ 'singular' => 'customer', 'plural' => 'customers' ],
					'columns'      => [
						'name' => [
							'label' => 'Name',
							'title' => static fn( $row ): string => (string) $row['name'],
						],
					],
					'callbacks'    => [
						// Gone once deleted, as a real row is: a name read
						// afterwards would be "#2".
						'get_item' => static fn( int $id ) => empty( $GLOBALS['rt_gone'][ $id ] ) ? ( self::PEOPLE[ $id ] ?? null ) : null,
						'delete'   => static function ( int $id ): bool {
							if ( 4 === $id ) {
								return false;
							}

							$GLOBALS['rt_gone'][ $id ] = true;

							return true;
						},
					],
					'bulk_actions' => [
						'block' => [
							'label'    => 'Block',
							'messages' => [
								'one'  => '%s was blocked.',
								'some' => '%s were blocked.',
								'many' => '%s customers blocked.',
							],
						],
					],
				],
				$config
			)
		);

		return (array) Tables::get_table( 'people' );
	}

	/**
	 * Run a bulk action on some ids, and return where it went.
	 *
	 * @param string     $action The action.
	 * @param array<int> $ids    The rows.
	 *
	 * @return string
	 */
	private function bulk( string $action, array $ids ): string {
		$_GET = [
			'page'     => 'people',
			'action'   => $action,
			'people'   => array_map( 'strval', $ids ),
			'_wpnonce' => 'nonce',
		];

		$_REQUEST = $_GET + [ 'customers' => array_map( 'strval', $ids ) ];

		try {
			Tables::process_early_actions();
		} catch ( \RT_Redirected $redirect ) {
			return $redirect->location;
		}

		$this->fail( 'There was no redirect.' );
	}

	/**
	 * The sentence a redirect's token holds, taken as the page would.
	 *
	 * @param string $location Where the action went.
	 *
	 * @return array{message: string, type: string}|null
	 */
	private function said( string $location ): ?array {
		parse_str( (string) parse_url( $location, PHP_URL_QUERY ), $query );

		return ActionNotice::take( (string) ( $query['_notice'] ?? '' ) );
	}

	/**
	 * One row: its name, in the consumer's words.
	 */
	public function test_one_row_is_named(): void {
		$this->register();

		$this->assertSame(
			[ 'message' => 'Elena Okafor was blocked.', 'type' => 'success' ],
			$this->said( $this->bulk( 'block', [ 1 ] ) )
		);
	}

	/**
	 * A few rows: each named, as a list. The name is plain text -- the
	 * title's markup is not carried into the sentence.
	 */
	public function test_a_few_rows_are_listed(): void {
		$this->register();

		$this->assertSame(
			'Elena Okafor, Kira Ito and Ben Tanaka were blocked.',
			$this->said( $this->bulk( 'block', [ 1, 2, 3 ] ) )['message'] ?? null
		);
	}

	/**
	 * Many rows: a count.
	 */
	public function test_many_rows_are_counted(): void {
		$this->register();

		$this->assertSame(
			'5 customers blocked.',
			$this->said( $this->bulk( 'block', [ 1, 2, 3, 4, 5 ] ) )['message'] ?? null
		);
	}

	/**
	 * Some failed: the rest are said, then which failed and why, and the
	 * notice is a warning rather than a success.
	 */
	public function test_a_partial_failure_says_which_and_why(): void {
		$this->register();

		add_action(
			'sugarcommerce_table_bulk_action_people_block',
			static function (): void {
				Outcome::fail( 3, 'Ben Tanaka is already blocked' );
			}
		);

		$this->assertSame(
			[
				'message' => 'Elena Okafor and Kira Ito were blocked. 1 could not be changed: Ben Tanaka is already blocked.',
				'type'    => 'warning',
			],
			$this->said( $this->bulk( 'block', [ 1, 2, 3 ] ) )
		);
	}

	/**
	 * Everything failed: an error, naming the row when no reason was given.
	 */
	public function test_a_whole_failure_is_an_error(): void {
		$this->register(
			[
				'bulk_actions' => [
					'block' => [
						'label'    => 'Block',
						'callback' => static fn(): bool => false,
					],
				],
			]
		);

		$this->assertSame(
			[
				'message' => '1 could not be changed: Kira Ito.',
				'type'    => 'error',
			],
			$this->said( $this->bulk( 'block', [ 2 ] ) )
		);
	}

	/**
	 * Without wording of its own an action is said in the library's, still
	 * naming the row; a deleted one is named from before it went.
	 */
	public function test_the_default_wording_names_the_row(): void {
		$this->register( [ 'bulk_actions' => [ 'delete' => 'Delete', 'tag' => 'Tag' ] ] );

		$this->assertSame( 'Elena Okafor deleted.', $this->said( $this->bulk( 'delete', [ 1 ] ) )['message'] ?? null );
		$this->assertSame( '5 customers updated.', $this->said( $this->bulk( 'tag', [ 1, 2, 3, 4, 5 ] ) )['message'] ?? null );
	}

	/**
	 * A callable is given the whole outcome and answers with the sentence:
	 * here, a status in the record's own words rather than its key.
	 */
	public function test_a_callable_words_it_from_the_outcome(): void {
		$labels = [ 'disabled' => 'Blocked' ];

		$this->register(
			[
				'messages'     => [
					'disable' => static function ( array $outcome ) use ( $labels ): string {
						return sprintf( '%s is now %s.', implode( ', ', $outcome['names'] ), strtolower( $labels['disabled'] ) );
					},
				],
				'bulk_actions' => [ 'disable' => 'Disable' ],
			]
		);

		$this->assertSame( 'Kira Ito is now blocked.', $this->said( $this->bulk( 'disable', [ 2 ] ) )['message'] ?? null );
	}

	/**
	 * An `item_name` callback names a row when the title is not what a
	 * sentence wants.
	 */
	public function test_an_item_name_callback_wins(): void {
		$config = $this->register( [ 'item_name' => static fn( $row ): string => 'Customer #' . $row['id'] ] );

		$this->assertSame( 'Customer #2', ActionNotice::name( $config, self::PEOPLE[2] ) );
	}

	/**
	 * Nothing but a token goes in the address: no count, no action, no name.
	 */
	public function test_only_a_token_crosses_the_redirect(): void {
		$this->register();

		$location = $this->bulk( 'block', [ 1 ] );

		parse_str( (string) parse_url( $location, PHP_URL_QUERY ), $query );

		$this->assertSame( [ 'page', '_notice' ], array_keys( $query ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{16}$/', $query['_notice'] );
		$this->assertStringNotContainsString( 'Elena', $location );
	}

	/**
	 * A token is read once, and only by whoever did the action.
	 */
	public function test_a_token_is_single_use_and_per_user(): void {
		$GLOBALS['rt_user_id'] = 7;

		$token = ActionNotice::store( [ 'message' => 'Kira Ito was blocked.', 'type' => 'success' ] );

		$GLOBALS['rt_user_id'] = 8;
		$this->assertNull( ActionNotice::take( $token ), 'Another user read the notice.' );

		$GLOBALS['rt_user_id'] = 7;
		$this->assertSame( 'Kira Ito was blocked.', ActionNotice::take( $token )['message'] ?? null );
		$this->assertNull( ActionNotice::take( $token ), 'The notice was shown twice.' );
	}

	/**
	 * A token that is not one of ours is not looked up.
	 */
	public function test_a_malformed_token_finds_nothing(): void {
		$this->assertNull( ActionNotice::take( '../../options' ) );
	}

	/**
	 * Drawn, the sentence is escaped: a name is somebody's to choose.
	 */
	public function test_the_notice_is_escaped_and_dismissible(): void {
		$html = ActionNotice::render( [ 'message' => '<script>x</script> was blocked.', 'type' => 'warning' ] );

		$this->assertSame(
			'<div class="notice notice-warning is-dismissible"><p>&lt;script&gt;x&lt;/script&gt; was blocked.</p></div>',
			$html
		);
	}

	/**
	 * The page shows the kept sentence where core shows its notices.
	 */
	public function test_the_page_shows_the_kept_sentence_once(): void {
		$config = $this->register();

		$_GET['_notice'] = ActionNotice::store( [ 'message' => 'Elena Okafor was blocked.', 'type' => 'success' ] );

		$method = new ReflectionMethod( Tables::class, 'render_admin_notices' );

		ob_start();
		$method->invoke( null, 'people', $config );
		$first = (string) ob_get_clean();

		ob_start();
		$method->invoke( null, 'people', $config );
		$second = (string) ob_get_clean();

		$this->assertStringContainsString( 'Elena Okafor was blocked.', $first );
		$this->assertStringNotContainsString( 'Elena Okafor', $second );
	}

	/**
	 * A row action names its row, and the built-in Delete names what it
	 * deleted -- or says it could not.
	 */
	public function test_a_row_delete_names_the_row(): void {
		$this->register();

		$location = $this->row( 'delete', 2 );
		$this->assertSame( [ 'message' => 'Kira Ito deleted.', 'type' => 'success' ], $this->said( $location ) );

		$location = $this->row( 'delete', 4 );
		$this->assertSame( [ 'message' => '1 could not be changed: Ana Silva.', 'type' => 'error' ], $this->said( $location ) );
	}

	/**
	 * An action worded the old way keeps its count in the address.
	 */
	public function test_an_old_notice_keeps_its_address(): void {
		$this->register(
			[
				'bulk_actions' => [
					'merge' => [
						'label'  => 'Merge',
						'notice' => [ 'success' => '%d merged.' ],
					],
				],
			]
		);

		$location = $this->bulk( 'merge', [ 1, 2 ] );

		$this->assertStringContainsString( 'updated=2', $location );
		$this->assertStringContainsString( '_bulk_action=merge', $location );
	}

	/**
	 * Run a row action's link.
	 *
	 * @param string $action The action.
	 * @param int    $id     The row.
	 *
	 * @return string Where it went.
	 */
	private function row( string $action, int $id ): string {
		$_GET = [
			'page'     => 'people',
			'action'   => $action,
			'item'     => (string) $id,
			'_wpnonce' => 'nonce',
		];

		$_REQUEST = $_GET;

		try {
			Tables::process_early_actions();
		} catch ( \RT_Redirected $redirect ) {
			return $redirect->location;
		}

		$this->fail( 'There was no redirect.' );
	}
}
