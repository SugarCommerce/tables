<?php
/**
 * Toggle Cells
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * A cell that is a switch: pressed, it saves over REST and redraws itself.
 *
 * Two shapes, because two kinds of switch turn up in a list. A **star** --
 * a narrow column of them, each one a button, as WooCommerce marks a
 * featured product -- says its state with a glyph and nothing else. A
 * **line** -- "✓ Subscribed" or "Not subscribed" under an address -- says
 * it in words, quietly, and looks like the text it is until it is pointed
 * at:
 *
 *     'featured' => [
 *         'label'  => 'Featured',
 *         'toggle' => [
 *             'style'        => 'star',
 *             'on'           => 'Featured',
 *             'off'          => 'Not featured',
 *             'turn_on'      => 'Mark as Featured',
 *             'turn_off'     => 'Remove from Featured',
 *             'turn_on_row'  => 'Mark “%s” as Featured',
 *             'turn_off_row' => 'Remove “%s” from Featured',
 *             'rest'         => [ 'path' => 'shop/v1/products/{id}', 'method' => 'PATCH', 'key' => 'is_featured' ],
 *             'capability'   => 'edit_products',
 *         ],
 *     ],
 *
 * Either way the cell is a real button: it does something on the page and
 * goes nowhere, so a link -- which reloads the screen -- would be the wrong
 * element. `aria-pressed` says whether it is on, which is how a screen
 * reader expects a toggle to be told. Its accessible name is either fixed
 * (`name`: "Subscribed to Marketing") or what pressing it does to this row
 * ("Mark “Analog Drums” as Featured"): a column of twenty buttons each
 * called "Mark as Featured" cannot be told apart by somebody listening to
 * a list of them.
 *
 * Saving goes one of two ways. `rest` names a route of the consumer's own
 * -- the route an app uses, so who may press it is decided where every
 * other change to the record is decided and nothing here re-implements a
 * permission. Or `save` is a callable, and the library's own route calls
 * it after checking the capability; see ToggleRoute.
 *
 * Somebody who may see the list but not change the record sees the state
 * and is not offered a button the server would refuse.
 *
 * A toggle that belongs inside another cell -- a line under an address --
 * is declared under the table's `toggles` instead, by a key of its own,
 * and drawn from that cell's callback with Toggle::render().
 *
 * @since 1.0.0
 */
final class Toggle {

	/**
	 * Every key a toggle takes, and what it is when left out.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULTS = [
		'field'        => '',      // The row's property that says on or off; the column key when empty.
		'style'        => 'line',  // 'line' or 'star'.
		'icons'        => null,    // [ 'on' => icon, 'off' => icon ], `set:name` or a Dashicons name; by style when null.
		'on'           => '',      // The state's word when on.
		'off'          => '',      // And when off.
		'name'         => '',      // A fixed accessible name; empty for the per-row action wording.
		'turn_on'      => '',      // What pressing does while off.
		'turn_off'     => '',      // And while on.
		'turn_on_row'  => '',      // The same with the row's name, as %s.
		'turn_off_row' => '',
		'done_on'      => '',      // Said aloud once it is on; %s is the row's name.
		'done_off'     => '',
		'confirm_on'   => '',      // Said before the first ever "on", which then waits for a second press.
		'confirm_off'  => '',      // The same before the first "off".
		'press_again'  => '',      // Said after a confirm_* while it waits.
		'since'        => null,    // callable( $row ): string, before the action in the tooltip while on.
		'since_label'  => '',      // "Since %s", for a date the save answers with.
		'failed'       => '',      // Said when the save did not work.
		'say_why'      => false,   // Show the server's own message instead of `failed`.
		'capability'   => '',      // Who gets a button; the table's edit capability when empty.
		'title'        => null,    // callable( $row ): string, the row's name in the wording.
		'rest'         => [],      // [ path, method, key, data, state, since ]: the consumer's route.
		'save'         => null,    // callable( int $id, bool $on ): bool|array|WP_Error, through the library's route.
	];

	/**
	 * A heading that is a glyph, with its word for a screen reader.
	 *
	 * As core heads its comments column with a bubble. Core strips the
	 * markup for Screen Options and for the label a folded row shows on a
	 * phone, so both of those read the word.
	 *
	 * @param string $word The column's name.
	 * @param string $icon A `set:name` icon (`heroicons-outline:heart`) or a
	 *                     Dashicons name (`star-filled`).
	 *
	 * @return string
	 */
	public static function heading( string $word, string $icon = 'heroicons-solid:star' ): string {
		return sprintf(
			'<span class="sugarcommerce-toggle-heading" aria-hidden="true" title="%1$s">%2$s</span><span class="screen-reader-text">%3$s</span>',
			esc_attr( $word ),
			Columns::icon( $icon ),
			esc_html( $word )
		);
	}

	/**
	 * A column's toggle, filled in, or null when the column is not one.
	 *
	 * @param array  $config Table configuration.
	 * @param string $column Column key.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function config( array $config, string $column ): ?array {
		$definition = $config['columns'][ $column ] ?? null;
		$declared   = is_array( $definition ) && is_array( $definition['toggle'] ?? null )
			? $definition['toggle']
			: ( $config['toggles'][ $column ] ?? null );

		if ( ! is_array( $declared ) ) {
			return null;
		}

		$toggle = array_merge( self::DEFAULTS, $declared );

		$toggle['style'] = 'star' === $toggle['style'] ? 'star' : 'line';
		$toggle['field'] = '' !== (string) $toggle['field'] ? (string) $toggle['field'] : $column;

		// A star is a glyph and nothing else; a line is a check before
		// the word for "on" and nothing before the word for "off" -- a
		// check beside "Not subscribed" says the opposite of its words.
		if ( ! is_array( $toggle['icons'] ) ) {
			$toggle['icons'] = 'star' === $toggle['style']
				? [
					'on'  => 'heroicons-solid:star',
					'off' => 'heroicons-outline:star',
				]
				: [
					'on'  => 'heroicons-outline:check',
					'off' => '',
				];
		}

		$words = [
			'on'       => __( 'On', 'sugarcommerce' ),
			'off'      => __( 'Off', 'sugarcommerce' ),
			'turn_on'  => __( 'Turn on', 'sugarcommerce' ),
			'turn_off' => __( 'Turn off', 'sugarcommerce' ),
			'failed'   => __( 'Not saved. Try again.', 'sugarcommerce' ),
		];

		foreach ( $words as $key => $word ) {
			if ( '' === (string) $toggle[ $key ] ) {
				$toggle[ $key ] = $word;
			}
		}

		return $toggle;
	}

	/**
	 * Every toggle column a table has, filled in, by column key.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all( array $config ): array {
		$found = [];

		$keys = array_merge( array_keys( (array) ( $config['columns'] ?? [] ) ), array_keys( (array) ( $config['toggles'] ?? [] ) ) );

		foreach ( array_unique( $keys ) as $column ) {
			$toggle = self::config( $config, (string) $column );

			if ( null !== $toggle ) {
				$found[ (string) $column ] = $toggle;
			}
		}

		return $found;
	}

	/**
	 * A toggle drawn inside another cell: one declared in the table's
	 * `toggles` rather than as a column of its own, such as a line under
	 * an address. Call it from that column's callback.
	 *
	 * @param string $table Table id.
	 * @param string $key   The toggle's key in `toggles`.
	 * @param mixed  $row   The row.
	 *
	 * @return string
	 */
	public static function render( string $table, string $key, mixed $row ): string {
		$config = Tables::get_table( $table );

		return null !== $config ? self::cell( $table, $key, $config, $row ) : '';
	}

	/**
	 * The capability that gets a button.
	 *
	 * @param array $config Table configuration.
	 * @param array $toggle The toggle, filled in.
	 *
	 * @return string
	 */
	public static function capability( array $config, array $toggle ): string {
		if ( '' !== (string) $toggle['capability'] ) {
			return (string) $toggle['capability'];
		}

		return (string) ( $config['capabilities']['edit'] ?? $config['capability'] ?? 'manage_options' );
	}

	/**
	 * One row's cell.
	 *
	 * It reads the row's own field -- what was set -- rather than anything
	 * that may switch it on by a rule a click cannot change.
	 *
	 * @param string $table  Table id.
	 * @param string $column Column key.
	 * @param array  $config Table configuration.
	 * @param mixed  $row    The row.
	 *
	 * @return string
	 */
	public static function cell( string $table, string $column, array $config, mixed $row ): string {
		$toggle = self::config( $config, $column );

		if ( null === $toggle ) {
			return '';
		}

		$on    = ! empty( Row::get( $row, (string) $toggle['field'] ) );
		$name  = self::name( $toggle, $row );
		$since = $on && is_callable( $toggle['since'] ) ? (string) call_user_func( $toggle['since'], $row ) : '';
		$state = $on ? (string) $toggle['on'] : (string) $toggle['off'];
		$class = 'sugarcommerce-toggle-cell sugarcommerce-toggle-cell--' . $toggle['style'] . ( $on ? ' is-on' : '' );
		$icon  = Columns::icon( $toggle['icons'][ $on ? 'on' : 'off' ] ?? '' );

		// A star's word is for a screen reader only; a line's is the line.
		$word = 'star' === $toggle['style']
			? '<span class="screen-reader-text">' . esc_html( $state ) . '</span>'
			: '<span class="sugarcommerce-toggle__word">' . esc_html( $state ) . '</span>';

		if ( ! current_user_can( self::capability( $config, $toggle ) ) ) {
			return sprintf(
				'<span class="%1$s"%2$s>%3$s%4$s</span>',
				esc_attr( $class ),
				'' !== $since ? ' title="' . esc_attr( $since ) . '"' : '',
				$icon,
				$word
			);
		}

		$title = self::action( $toggle, $on );

		if ( '' !== $since ) {
			$title = $since . ' · ' . $title;
		}

		return sprintf(
			'<span class="%1$s"><button type="button" class="sugarcommerce-toggle button-link" data-toggle="%2$s" data-id="%3$d" data-name="%4$s" aria-pressed="%5$s" aria-label="%6$s" title="%7$s">%8$s%9$s</button><span class="sugarcommerce-toggle__message" hidden></span></span>',
			esc_attr( $class ),
			esc_attr( $table . ':' . $column ),
			Row::id( $row ),
			esc_attr( $name ),
			$on ? 'true' : 'false',
			esc_attr( self::label( $toggle, $on, $name ) ),
			esc_attr( $title ),
			$icon,
			'star' === $toggle['style'] ? '' : $word
		);
	}

	/**
	 * What pressing it does, as its tooltip says: the action, and the
	 * warning that goes with it when there is one -- turning consent on is
	 * a record of something said elsewhere, so the tooltip says to do it
	 * only if it was.
	 *
	 * @param array $toggle The toggle.
	 * @param bool  $on     Whether it is on now.
	 *
	 * @return string
	 */
	private static function action( array $toggle, bool $on ): string {
		$action  = $on ? (string) $toggle['turn_off'] : (string) $toggle['turn_on'];
		$confirm = $on ? (string) $toggle['confirm_off'] : (string) $toggle['confirm_on'];

		return '' !== $confirm ? $action . ' ' . $confirm : $action;
	}

	/**
	 * The button's accessible name.
	 *
	 * @param array  $toggle The toggle.
	 * @param bool   $on     Whether it is on now.
	 * @param string $name   The row's name.
	 *
	 * @return string
	 */
	private static function label( array $toggle, bool $on, string $name ): string {
		if ( '' !== (string) $toggle['name'] ) {
			return (string) $toggle['name'];
		}

		$row  = $on ? (string) $toggle['turn_off_row'] : (string) $toggle['turn_on_row'];
		$bare = $on ? (string) $toggle['turn_off'] : (string) $toggle['turn_on'];

		return '' !== $row ? sprintf( $row, $name ) : $bare;
	}

	/**
	 * What the row is called in the wording.
	 *
	 * @param array $toggle The toggle.
	 * @param mixed $row    The row.
	 *
	 * @return string
	 */
	private static function name( array $toggle, mixed $row ): string {
		if ( is_callable( $toggle['title'] ) ) {
			return (string) call_user_func( $toggle['title'], $row );
		}

		foreach ( [ 'title', 'name', 'label', 'email' ] as $field ) {
			$value = (string) Row::get( $row, $field, '' );

			if ( '' !== $value ) {
				return $value;
			}
		}

		/* translators: %d: a row's id. */
		return sprintf( __( '#%d', 'sugarcommerce' ), Row::id( $row ) );
	}

	/**
	 * What the script is told about a table's toggles: their words and
	 * where each saves, by `table:column`.
	 *
	 * The address is left with `{id}` in it, for the script to fill: the
	 * id is the row's, and the template is not run through esc_url, which
	 * would strip the braces.
	 *
	 * @param string $table  Table id.
	 * @param array  $config Table configuration.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function script_data( string $table, array $config ): array {
		$data = [];

		foreach ( self::all( $config ) as $column => $toggle ) {
			$data[ $table . ':' . $column ] = [
				'style'      => $toggle['style'],
				// Drawn here, so the script swaps markup and never builds a glyph.
				'icons'      => [
					'on'  => Columns::icon( $toggle['icons']['on'] ?? '' ),
					'off' => Columns::icon( $toggle['icons']['off'] ?? '' ),
				],
				'on'         => $toggle['on'],
				'off'        => $toggle['off'],
				'name'       => $toggle['name'],
				'turnOn'     => $toggle['turn_on'],
				'turnOff'    => $toggle['turn_off'],
				'turnOnRow'  => $toggle['turn_on_row'],
				'turnOffRow' => $toggle['turn_off_row'],
				'doneOn'     => $toggle['done_on'],
				'doneOff'    => $toggle['done_off'],
				'confirmOn'  => $toggle['confirm_on'],
				'confirmOff' => $toggle['confirm_off'],
				'pressAgain' => $toggle['press_again'],
				'sinceLabel' => $toggle['since_label'],
				'failed'     => $toggle['failed'],
				'sayWhy'     => (bool) $toggle['say_why'],
				'request'    => self::request( $table, (string) $column, $toggle ),
			];
		}

		return $data;
	}

	/**
	 * Where a toggle saves, and how.
	 *
	 * @param string $table  Table id.
	 * @param string $column Column key.
	 * @param array  $toggle The toggle.
	 *
	 * @return array<string, mixed>
	 */
	private static function request( string $table, string $column, array $toggle ): array {
		$rest = (array) $toggle['rest'];

		if ( '' !== (string) ( $rest['path'] ?? '' ) ) {
			$key = (string) ( $rest['key'] ?? $toggle['field'] );

			return [
				'url'    => rest_url( ltrim( (string) $rest['path'], '/' ) ),
				'method' => strtoupper( (string) ( $rest['method'] ?? 'POST' ) ),
				'key'    => $key,
				'data'   => (object) (array) ( $rest['data'] ?? [] ),
				'state'  => (string) ( $rest['state'] ?? $key ),
				'since'  => (string) ( $rest['since'] ?? '' ),
			];
		}

		return [
			'url'    => rest_url( ToggleRoute::path( $table ) . '/{id}' ),
			'method' => 'POST',
			'key'    => 'on',
			'data'   => (object) [ 'column' => $column ],
			'state'  => 'on',
			'since'  => '',
		];
	}
}
