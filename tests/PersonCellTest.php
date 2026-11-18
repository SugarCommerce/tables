<?php
/**
 * Person cell tests.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Tests;

use SugarCommerce\Tables\Cells\Person;
use SugarCommerce\Tables\Columns;
use SugarCommerce\Tables\Tables;
use SugarCommerce\Tables\Table;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/flyout-registry.php';

/**
 * A person looks like the same person on every list: picture, the name as
 * the link, the address as a quieter line under it.
 */
final class PersonCellTest extends TestCase {

	/**
	 * A clean slate.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$GLOBALS['rt_flyouts'] = [ 'shop_customer' => true ];
	}

	/**
	 * A picture that says who it was drawn for.
	 *
	 * @return callable
	 */
	private function avatar(): callable {
		return static fn( string $email, string $name, int $size, array $args ): string => sprintf(
			'<svg data-for="%s|%s|%d" style="%s"></svg>',
			$email,
			$name,
			$size,
			$args['style']
		);
	}

	/**
	 * The parts of Uma's cell.
	 *
	 * @param array<string, mixed> $more More parts.
	 *
	 * @return array<string, mixed>
	 */
	private function uma( array $more = [] ): array {
		return $more + [
			'name'   => 'Uma Rossi',
			'email'  => 'uma@example.test',
			'id'     => 12,
			'flyout' => 'shop_customer',
			'avatar' => $this->avatar(),
		];
	}

	/**
	 * The name opens their panel, and the address is text.
	 */
	public function test_the_name_is_the_link_and_the_address_is_not(): void {
		$cell = Person::cell( $this->uma() );

		$this->assertStringContainsString( 'data-flyout="customer" data-id="12">Uma Rossi</a>', $cell );

		// The address: there, whole in its tooltip, and nothing to click.
		$this->assertStringContainsString( '<span class="sugarcommerce-person-email" title="uma@example.test"', $cell );
		$this->assertStringContainsString( '>uma@example.test</span>', $cell );
		$this->assertStringNotContainsString( 'mailto:', $cell );

		// Their picture, once, floated beside the name.
		$this->assertSame( 1, substr_count( $cell, '<svg' ) );
		$this->assertStringContainsString( 'data-for="uma@example.test|Uma Rossi|32" style="float:left;margin:1px 10px 0 0"', $cell );
	}

	/**
	 * With no name the address is shown once, and is the link.
	 */
	public function test_with_no_name_the_address_is_the_link(): void {
		$cell = Person::cell( $this->uma( [ 'name' => '' ] ) );

		$this->assertStringContainsString( 'data-id="12">uma@example.test</a>', $cell );
		$this->assertSame( 1, substr_count( $cell, '>uma@example.test<' ) );
		$this->assertStringNotContainsString( 'sugarcommerce-person-email', $cell );
		$this->assertStringContainsString( ' title="uma@example.test"><span', $cell );
	}

	/**
	 * A list with the address in a column of its own can leave it off, and
	 * nothing else changes.
	 */
	public function test_the_address_can_be_left_off(): void {
		$whole = Person::cell( $this->uma() );
		$cell  = Person::cell( $this->uma( [ 'email_line' => false ] ) );

		$this->assertStringNotContainsString( 'sugarcommerce-person-email', $cell );
		$this->assertSame( $cell, (string) preg_replace( '/<span class="sugarcommerce-person-email".*?<\/span>/', '', $whole ) );
		$this->assertSame( $whole, Person::cell( $this->uma( [ 'email_line' => true ] ) ) );

		// With no name the address is still what is shown, as the link.
		$this->assertStringContainsString( 'data-id="12">uma@example.test</a>', Person::cell( $this->uma( [ 'name' => '', 'email_line' => false ] ) ) );
	}

	/**
	 * Markup handed over ends the name's line, inside it.
	 */
	public function test_after_ends_the_names_line(): void {
		$this->assertStringContainsString( '</span><i>Blocked</i></strong>', Person::cell( $this->uma( [ 'after' => '<i>Blocked</i>' ] ) ) );
	}

	/**
	 * Nothing to open is text; nobody at all is a dash.
	 */
	public function test_with_nothing_to_open_it_is_text(): void {
		$cell = Person::cell( [ 'email' => 'stranger@example.test', 'avatar' => $this->avatar() ] );

		$this->assertStringContainsString( 'stranger@example.test', $cell );
		$this->assertStringNotContainsString( '<a ', $cell );
		$this->assertStringContainsString( '<svg', $cell );

		// A panel that is not there is not a link either.
		$this->assertStringNotContainsString( '<a ', Person::cell( $this->uma( [ 'flyout' => 'shop_gone' ] ) ) );

		$this->assertSame( '<span aria-hidden="true">&mdash;</span>', Person::cell( [] ) );
		$this->assertSame( '<span aria-hidden="true">&mdash;</span>', Person::cell( [ 'email' => '  ', 'name' => ' ' ] ) );
	}

	/**
	 * A URL, or the consumer's own link.
	 */
	public function test_a_url_or_a_link_of_its_own(): void {
		$this->assertStringContainsString( '<a href="https://example.test/u/12">Uma Rossi</a>', Person::cell( $this->uma( [ 'flyout' => '', 'url' => 'https://example.test/u/12' ] ) ) );
		$this->assertStringContainsString( '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><b>Uma</b></span>', Person::cell( $this->uma( [ 'link' => '<b>Uma</b>' ] ) ) );
	}

	/**
	 * Core's picture when none is given; none when told.
	 */
	public function test_the_picture_defaults_to_cores_and_can_be_left_off(): void {
		$this->assertStringContainsString( '<img class="avatar"', Person::cell( $this->uma( [ 'avatar' => null ] ) ) );
		$this->assertStringNotContainsString( '<img', Person::cell( $this->uma( [ 'avatar' => false ] ) ) );
		$this->assertStringStartsWith( '<i>pic</i><strong', Person::cell( $this->uma( [ 'avatar' => '<i>pic</i>' ] ) ) );
	}

	/**
	 * A name and an address are text, whatever is in them.
	 */
	public function test_the_cell_is_escaped(): void {
		$cell = Person::cell(
			[
				'name'   => 'Uma <script>alert(1)</script> "Rossi"',
				'email'  => '"><img src=x onerror=alert(1)>@example.test',
				'avatar' => false,
			]
		);

		$this->assertStringNotContainsString( '<script', $cell );
		$this->assertStringNotContainsString( '<img', $cell );
		$this->assertStringNotContainsString( 'onerror=alert(1)>', $cell );
	}

	/**
	 * As a column: the row's person, the table's panel, the table's filter.
	 */
	public function test_a_person_column(): void {
		Tables::register(
			'shop_orders',
			[
				'flyouts' => [ 'view' => 'shop_customer' ],
				'columns' => [
					'customer' => [
						'label'      => 'Customer',
						'type'       => 'person',
						'person'     => static fn( object $row ): ?array => $row->customer_id
							? [ 'name' => $row->name, 'email' => $row->email, 'id' => $row->customer_id ]
							: [ 'email' => $row->email ],
						'link'       => 'view_flyout',
						'avatar'     => $this->avatar(),
						'email_line' => false,
					],
				],
			]
		);

		$table = new Table( 'shop_orders', Tables::get_table( 'shop_orders' ) );
		$row   = (object) [ 'id' => 3, 'customer_id' => 12, 'name' => 'Uma Rossi', 'email' => 'uma@example.test' ];

		$cell = $table->column_default( $row, 'customer' );

		$this->assertStringContainsString( 'data-flyout="customer" data-id="12">Uma Rossi</a>', $cell );
		$this->assertStringNotContainsString( 'sugarcommerce-person-email', $cell );

		// An order with an address and nobody behind it.
		$this->assertStringNotContainsString( '<a ', $table->column_default( (object) [ 'id' => 4, 'customer_id' => 0, 'email' => 'loose@example.test' ], 'customer' ) );

		add_filter( 'sugarcommerce_table_person_cell_shop_orders', static fn( string $html, array $parts ): string => $parts['email'] . '!', 10, 2 );

		$this->assertSame( 'uma@example.test!', $table->column_default( $row, 'customer' ) );
	}

	/**
	 * A column's link can be a panel by id, a URL, or a closure; and with
	 * no `person`, the row is the person.
	 */
	public function test_a_person_columns_link(): void {
		$cell = function ( $link ): string {
			Tables::register(
				'shop_people',
				[
					'columns' => [
						'name' => [
							'label'  => 'Name',
							'type'   => 'person',
							'link'   => $link,
							'avatar' => false,
						],
					],
				]
			);

			return ( new Table( 'shop_people', Tables::get_table( 'shop_people' ) ) )
				->column_default( (object) [ 'id' => 12, 'name' => 'Uma', 'email' => 'uma@example.test' ], 'name' );
		};

		$this->assertStringContainsString( 'data-flyout="customer" data-id="12">Uma</a>', $cell( 'shop_customer' ) );
		$this->assertStringContainsString( '<a href="https://example.test/people">Uma</a>', $cell( 'https://example.test/people' ) );
		$this->assertStringContainsString( '<a href="https://example.test/u/12">Uma</a>', $cell( static fn( object $row ): string => 'https://example.test/u/' . $row->id ) );

		// A string is never called, even when a function has its name.
		$this->assertStringNotContainsString( '<a ', $cell( 'strtoupper' ) );
	}

	/**
	 * The old `customer` type is drawn as the same cell.
	 */
	public function test_the_customer_type_is_the_same_cell(): void {
		$person = new class() {
			/**
			 * Name.
			 *
			 * @return string
			 */
			public function get_name(): string {
				return 'Uma Rossi';
			}

			/**
			 * Email.
			 *
			 * @return string
			 */
			public function get_email(): string {
				return 'uma@example.test';
			}
		};

		$html = Columns::auto_format( 'customer', $person, (object) [], [ '_filter_url' => 'https://example.test/orders?c=12' ] );

		$this->assertStringContainsString( '<a href="https://example.test/orders?c=12">Uma Rossi</a>', $html );
		$this->assertStringContainsString( 'sugarcommerce-person-email', $html );
		$this->assertStringContainsString( '<img class="avatar"', $html );
	}
}
