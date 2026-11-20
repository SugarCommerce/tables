<?php
/**
 * Inline Edit Save
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Editing;

use SugarCommerce\Tables\ActionNotice;
use SugarCommerce\Tables\InlineEdit;
use SugarCommerce\Tables\Outcome;
use SugarCommerce\Tables\Table;
use SugarCommerce\Tables\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Applying what an inline editor submitted.
 *
 * Separate from the rendering because the two run in different requests and
 * fail in different ways. InlineEdit draws markup during a page load; this
 * reads a POST, and everything in it is a check on something an attacker
 * controls -- the nonce, the capability, the table, and every value against
 * the options the field actually offered.
 *
 * That last one matters more than it looks. The markup constrains a browser
 * and nothing constrains a POST, so a select offering three statuses will
 * happily receive a fourth. Both save paths run every submitted value past
 * the field that claims to own it, and drop anything the field would not
 * have offered.
 *
 * Neither path writes a row. They validate, then fire an action -- the
 * caller decides what saving means, because only the caller knows whether a
 * status change also has to reach Stripe.
 *
 * @since 1.0.0
 */
final class InlineEditSave {

	/**
	 * What the last bulk edit was given. See last_bulk_edit().
	 *
	 * @var array{ids: array<int>, values: array<string, mixed>, names: array<int, string>}
	 */
	private static array $last_bulk_edit = [
		'ids'    => [],
		'values' => [],
		'names'  => [],
	];

	/**
	 * Apply a submitted bulk edit.
	 *
	 * Runs before the table is built, so the rows redraw with the change
	 * already in them -- the same as core, where the page reloads.
	 *
	 * @param string $table_id The table id.
	 * @param array  $config   Table configuration.
	 *
	 * @return int How many rows were edited, 0 if nothing was.
	 */
	public static function process_bulk_edit( string $table_id, array $config ): int {
		if ( ! InlineEdit::has_bulk_edit( $config ) || ! isset( $_REQUEST['bulk_edit'] ) ) {
			return 0;
		}

		$submitted_table = isset( $_REQUEST['table_id'] )
			? sanitize_key( wp_unslash( $_REQUEST['table_id'] ) )
			: '';

		if ( $submitted_table !== $table_id ) {
			return 0;
		}

		$nonce = isset( $_REQUEST['_bulk_edit_nonce'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['_bulk_edit_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'bulk-edit-' . $table_id ) ) {
			return 0;
		}

		if ( ! current_user_can( self::edit_capability( $config ) ) ) {
			return 0;
		}

		// The checkboxes post under the plural label, which is also what the
		// bulk-action nonce is scoped to. Reading 'ids' would have found
		// nothing, every time, silently.
		$plural = Tables::bulk_key( $config );

		$ids = array_values(
			array_filter( array_map( 'absint', (array) ( $_REQUEST[ $plural ] ?? [] ) ) )
		);

		if ( [] === $ids ) {
			return 0;
		}

		$values = [];

		// Read through fields() rather than off the config, so a field added by
		// the filter saves as well as renders. Reading the raw config here
		// would have given an add-on a control that looks like it works.
		foreach ( InlineEdit::fields( $table_id, $config, 'bulk_edit' ) as $name => $field ) {
			// A checklist posts a list, and what it means for each row
			// depends on what that row is in already -- so it is handed over
			// as what to add and what to take away. See InlineChecklist.
			if ( InlineChecklist::is( (array) $field ) ) {
				$change = InlineChecklist::bulk_submitted( (array) $field, (string) $name, $_REQUEST );

				if ( null !== $change ) {
					$values[ $name ] = $change;
				}

				continue;
			}

			$submitted = isset( $_REQUEST[ $name ] )
				? sanitize_text_field( wp_unslash( $_REQUEST[ $name ] ) )
				: '-1';

			// Core's sentinel. Not an empty string, because an empty string is
			// a value somebody might genuinely want to set.
			if ( '-1' === $submitted ) {
				continue;
			}

			if ( ! self::accepts( $field, $submitted ) ) {
				continue;
			}

			$values[ $name ] = $submitted;
		}

		if ( [] === $values ) {
			return 0;
		}

		// Who they are while they can still be named, a clean slate for the
		// handlers to say a row could not be changed, and what was set --
		// for the sentence the list shows afterwards. See ActionNotice.
		self::$last_bulk_edit = [
			'ids'    => $ids,
			'values' => $values,
			'names'  => ActionNotice::names_before( $config, $ids ),
		];

		Outcome::reset();

		/**
		 * Fires when a bulk edit is applied.
		 *
		 * @param array<int, int>      $ids    The rows being edited.
		 * @param array<string, mixed> $values What to set on them.
		 *
		 * @since 1.0.0
		 */
		do_action( "sugarcommerce_table_bulk_edit_{$table_id}", $ids, $values );

		return count( $ids );
	}

	/**
	 * Save one row, and hand back the row as it now reads.
	 *
	 * Core's flow: the script posts the fields, the server saves and renders
	 * the single row, and the script swaps the markup in. Returning the row
	 * rather than a success flag is what keeps the table honest -- a status
	 * column, a formatted amount and a row action set all change with the
	 * save, and a client rebuilding them from the submitted values would be
	 * a second renderer drifting from the first.
	 *
	 * Asked through the REST route (Routes), which has already checked the
	 * edit capability; the REST nonce is the forgery check.
	 *
	 * @param string               $table_id The table.
	 * @param int                  $item_id  The row.
	 * @param array<string, mixed> $input    What the quick editor posted, unslashed.
	 *
	 * @return array{status: int, row: string, notice: array|null}
	 * @since 1.0.0
	 */
	public static function quick_edit( string $table_id, int $item_id, array $input ): array {
		$config = Tables::get_table( $table_id );

		if ( ! $config || ! InlineEdit::has_quick_edit( $config ) ) {
			return [
				'status' => 404,
				'row' => '',
				'notice' => null,
			];
		}

		if ( $item_id < 1 ) {
			return [
				'status' => 400,
				'row' => '',
				'notice' => null,
			];
		}

		$values = [];

		foreach ( InlineEdit::fields( $table_id, $config, 'quick_edit' ) as $name => $field ) {
			// The ids now ticked, which may be none at all.
			if ( InlineChecklist::is( (array) $field ) ) {
				$ticked = InlineChecklist::submitted( (array) $field, (string) $name, $input );

				if ( null !== $ticked ) {
					$values[ $name ] = $ticked;
				}

				continue;
			}

			if ( ! isset( $input[ $name ] ) || ! is_scalar( $input[ $name ] ) ) {
				continue;
			}

			$submitted = sanitize_text_field( (string) $input[ $name ] );

			if ( ! self::accepts( $field, $submitted ) ) {
				continue;
			}

			$values[ $name ] = $submitted;
		}

		$names = ActionNotice::names_before( $config, [ $item_id ] );

		Outcome::reset();

		/**
		 * Fires when a row is quick edited.
		 *
		 * @param int                  $item_id The row.
		 * @param array<string, mixed> $values  What to set on it.
		 *
		 * @since 1.0.0
		 */
		do_action( "sugarcommerce_table_quick_edit_{$table_id}", $item_id, $values );

		return [
			'status' => 200,
			'row'    => self::saved_row( $table_id, $config, $item_id ),
			'notice' => self::notice( $config, $item_id, $values, $names ),
		];
	}

	/**
	 * What the last bulk edit was given: its rows, what it set, and the
	 * rows' names as they were before.
	 *
	 * @return array{ids: array<int>, values: array<string, mixed>, names: array<int, string>}
	 */
	public static function last_bulk_edit(): array {
		return self::$last_bulk_edit;
	}

	/**
	 * Say what a quick edit did, beside the row it sends back.
	 *
	 * The script shows it as a notice above the table, and says it aloud in
	 * place of "Changes saved."
	 *
	 * @param array              $config  Table configuration.
	 * @param int                $item_id The row.
	 * @param array              $values  What was set.
	 * @param array<int, string> $names   Its name, read before.
	 *
	 * @return array|null
	 */
	private static function notice( array $config, int $item_id, array $values, array $names ): ?array {
		return ActionNotice::compose(
			$config,
			[
				'action' => 'quick_edit',
				'kind'   => 'quick_edit',
				'ids'    => [ $item_id ],
				'failed' => Outcome::failed(),
				'names'  => $names,
				'values' => $values,
			],
			ActionNotice::messages( $config, 'quick_edit' )
		);
	}

	/**
	 * The capability an inline edit needs.
	 *
	 * The edit one, not the view one. A table can be readable by anyone who
	 * can see the menu and editable only by a shop manager -- that is what
	 * `capabilities['edit']` declares -- and both editors used to test the
	 * view capability, so declaring the edit one changed nothing.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return string
	 */
	public static function edit_capability( array $config ): string {
		return (string) ( $config['capabilities']['edit'] ?? $config['capability'] ?? 'manage_options' );
	}

	/**
	 * Draw one row, the way the table would.
	 *
	 * @param string $table_id The table id.
	 * @param array  $config   Table configuration.
	 * @param int    $item_id  The row.
	 *
	 * @return string The row's markup, or nothing without a get_item callback.
	 */
	private static function saved_row( string $table_id, array $config, int $item_id ): string {
		$get = $config['callbacks']['get_item'] ?? null;

		if ( ! is_callable( $get ) ) {
			return '';
		}

		$item = $get( $item_id );

		if ( ! $item ) {
			return '';
		}

		// A REST request loads none of the admin: the list table asks for
		// its screen and its column headers.
		if ( ! function_exists( 'convert_to_screen' ) && defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/admin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/admin.php';
		}

		// The column headers and nothing else. prepare_items() would also
		// fetch the counts and a whole page of rows in order to draw this
		// one, which the get_item callback has already handed over.
		$table = new Table( $table_id, $config );

		$table->prepare_columns();

		ob_start();
		$table->single_row( $item );

		return (string) ob_get_clean();
	}

	/**
	 * Whether a field will accept the value submitted for it.
	 *
	 * A select may only be set to something it actually offered -- the markup
	 * constrains a browser, and nothing constrains a POST. A field with no
	 * options is a text input, which takes what it is given, already
	 * sanitised by the caller.
	 *
	 * @param array  $field     The field.
	 * @param string $submitted What came in.
	 *
	 * @return bool
	 */
	private static function accepts( array $field, string $submitted ): bool {
		$options = (array) ( $field['options'] ?? [] );

		return [] === $options || array_key_exists( $submitted, $options );
	}
}
