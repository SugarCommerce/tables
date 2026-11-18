<?php
/**
 * Which rows of a list are lit, and why.
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Seen;


defined( 'ABSPATH' ) || exit;

/**
 * The ways a row of a list can be lit, in the order they are tried.
 *
 * One list of them per table. `highlight()` tries each in turn and stops at
 * the first that is true of the row, so which wins when two are true is the
 * `priority` and nowhere else: red before amber before green. A row's class
 * is `{class}-{state}` -- `sugarcommerce-row-new` unless the table names its
 * own `class` -- beside `sugarcommerce-row-lit`, which the stylesheet draws.
 *
 * Each state says:
 *
 * - `label`: what a row in this state is called, for whoever cannot see its
 *   colour -- read aloud in the row's first cell. Empty for a state whose
 *   row already says why in words.
 * - `color`: the colour of the bar, as a hex. The same in every admin
 *   colour scheme: the scheme's highlight is blue in one and orange in
 *   another, and "new" has to be the one colour everywhere.
 * - `tint`: whether the row is tinted with it as well. Only a row that
 *   wants something done.
 * - `priority`: lower is tried first.
 * - `when`: asked with the row and the table's id, true when the row is in
 *   this state. Left out for a state only the row filter ever gives.
 * - `forced`: how the bar is told apart when Windows is in a high-contrast
 *   mode, which keeps borders and chooses their colour, the same one for
 *   all: `highlight` (the system's own), `double`, `dashed` or `solid`.
 *
 * A table with a `seen` marker has three without saying so: `blocked` (red,
 * dashed), `excused` (amber, double) and `new` (green, the system's
 * highlight). A state of the same name in `states` changes them.
 *
 * @since 1.0.0
 */
final class Highlights {

	/**
	 * Each table's states, once asked on this request.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private static array $states = [];

	/**
	 * A table's states, lowest priority first.
	 *
	 * @param string $table_id The table's id.
	 *
	 * @return array<string, array{label: string, color: string, tint: bool, priority: int, when: callable|null, forced: string}>
	 * @since 1.0.0
	 */
	public static function states( string $table_id ): array {
		if ( isset( self::$states[ $table_id ] ) ) {
			return self::$states[ $table_id ];
		}

		$list = Seen::get( $table_id );

		if ( null === $list ) {
			return [];
		}

		$given  = (array) ( $list['highlights']['states'] ?? [] );
		$states = [];

		foreach ( self::built_in( $table_id, $list ) as $state => $built_in ) {
			$states[ $state ] = array_merge( $built_in, is_array( $given[ $state ] ?? null ) ? $given[ $state ] : [] );
		}

		$states += $given;

		/**
		 * Filter the ways a row of a table can be lit.
		 *
		 * Add a state under its name -- `vip` -- with a `label`, a `color`
		 * (a hex), whether it has a `tint`, a `priority` (10 is blocked, 50
		 * is new; lower wins) and `when`: a callable given the row and the
		 * table's id that says whether the row is in it. Its bar is drawn
		 * from the colour; no stylesheet is needed.
		 *
		 * @param array<string, array<string, mixed>> $states   By state.
		 * @param string                              $table_id The table's id.
		 *
		 * @since 1.0.0
		 */
		$states = (array) apply_filters( "sugarcommerce_table_highlights_{$table_id}", $states, $table_id );
		$clean  = [];

		foreach ( $states as $state => $highlight ) {
			$state = sanitize_html_class( (string) $state );

			if ( '' === $state || ! is_array( $highlight ) ) {
				continue;
			}

			$when   = $highlight['when'] ?? null;
			$forced = (string) ( $highlight['forced'] ?? 'solid' );

			$clean[ $state ] = [
				'label'    => (string) ( $highlight['label'] ?? '' ),
				'color'    => (string) sanitize_hex_color( (string) ( $highlight['color'] ?? '' ) ),
				'tint'     => ! empty( $highlight['tint'] ),
				'priority' => (int) ( $highlight['priority'] ?? 100 ),
				'when'     => is_callable( $when ) ? $when : null,
				'forced'   => in_array( $forced, [ 'highlight', 'double', 'dashed', 'solid' ], true ) ? $forced : 'solid',
			];
		}

		// Lowest first, and as they were given where two are level.
		uasort( $clean, static fn( array $one, array $other ): int => $one['priority'] <=> $other['priority'] );

		self::$states[ $table_id ] = $clean;

		return $clean;
	}

	/**
	 * Pick the states that are a list's out of one shared registry.
	 *
	 * For a plugin that keeps one registry for all its lists, each state
	 * naming the `lists` it can be on (empty for any) and perhaps a better
	 * word for one of them in `labels`: what a table's `states` should be.
	 *
	 * @param array<string, array<string, mixed>> $registry By state.
	 * @param string                              $name     The list's name in it.
	 *
	 * @return array<string, array<string, mixed>>
	 * @since 1.0.0
	 */
	public static function for_list( array $registry, string $name ): array {
		$states = [];

		foreach ( $registry as $state => $highlight ) {
			$lists = (array) ( $highlight['lists'] ?? [] );

			if ( ! is_array( $highlight ) || ( [] !== $lists && ! in_array( $name, $lists, true ) ) ) {
				continue;
			}

			if ( isset( $highlight['labels'][ $name ] ) ) {
				$highlight['label'] = (string) $highlight['labels'][ $name ];
			}

			unset( $highlight['lists'], $highlight['labels'] );

			$states[ $state ] = $highlight;
		}

		return $states;
	}

	/**
	 * How a row of a table is lit, if it is.
	 *
	 * The one place that decides, so the list, the tests and an add-on
	 * agree: the first of the table's states the row is in.
	 *
	 * @param string       $table_id The table's id.
	 * @param object|array $row      The row.
	 *
	 * @return string A state, or empty.
	 * @since 1.0.0
	 */
	public static function highlight( string $table_id, $row ): string {
		$states = self::states( $table_id );
		$state  = '';

		foreach ( $states as $name => $highlight ) {
			// One that only a filter gives.
			if ( null !== $highlight['when'] && call_user_func( $highlight['when'], $row, $table_id ) ) {
				$state = (string) $name;
				break;
			}
		}

		/**
		 * Filter how a row of a table is lit.
		 *
		 * Return one of the table's states or an empty string; anything
		 * else is read as not lit.
		 *
		 * @param string       $state    The state, or empty.
		 * @param object|array $row      The row.
		 * @param string       $table_id The table's id.
		 *
		 * @since 1.0.0
		 */
		$state = (string) apply_filters( "sugarcommerce_table_row_highlight_{$table_id}", $state, $row, $table_id );

		return isset( $states[ $state ] ) ? $state : '';
	}

	/**
	 * The classes a row is given for how it is lit; none when it is not.
	 *
	 * @param string       $table_id The table's id.
	 * @param object|array $row      The row.
	 *
	 * @return array<int, string>
	 * @since 1.0.0
	 */
	public static function row_classes( string $table_id, $row ): array {
		$state = Seen::get( $table_id ) ? self::highlight( $table_id, $row ) : '';

		if ( '' === $state ) {
			return [];
		}

		$classes = [ self::class_name( $table_id, $state ), 'sugarcommerce-row-lit' ];

		if ( self::states( $table_id )[ $state ]['tint'] ) {
			$classes[] = 'sugarcommerce-row-tinted';
		}

		return $classes;
	}

	/**
	 * A table's `row_class`, with the classes of how a row is lit after the
	 * table's own.
	 *
	 * @param string        $table_id The table's id.
	 * @param callable|null $own      The table's own `row_class`, if it has one.
	 *
	 * @return callable Given the row; returns its classes.
	 * @since 1.0.0
	 */
	public static function row_class( string $table_id, $own ): callable {
		return static function ( $row ) use ( $table_id, $own ): array {
			$classes = is_callable( $own ) ? (array) call_user_func( $own, $row ) : [];

			return array_values( array_merge( array_filter( array_map( 'strval', $classes ) ), self::row_classes( $table_id, $row ) ) );
		};
	}

	/**
	 * What a lit row says about itself, for whoever cannot see the colour.
	 *
	 * The bar down a row is a colour and nothing else, which says nothing to
	 * a screen reader and little to somebody who does not tell green from
	 * grey. So the row says so in its first cell as well, in text that is
	 * read aloud and not drawn: the words of the state it is lit in, and for
	 * a state with no words of its own -- a row that already says why beside
	 * its name -- that it is new, when it is.
	 *
	 * @param string       $table_id The table's id.
	 * @param object|array $row      The row.
	 *
	 * @return string Markup beginning with a space; empty for nothing to say.
	 * @since 1.0.0
	 */
	public static function label( string $table_id, $row ): string {
		$list = Seen::get( $table_id );

		if ( null === $list || ( ! is_object( $row ) && ! is_array( $row ) ) ) {
			return '';
		}

		$state = self::highlight( $table_id, $row );
		$label = '' !== $state ? self::states( $table_id )[ $state ]['label'] : '';

		if ( '' === $label && Rows::is_new( $table_id, $row ) ) {
			$label = (string) $list['label'];
		}

		/**
		 * Filter what a lit row says aloud.
		 *
		 * Empty it where the row already says the same thing in words a
		 * screen reader reads -- a tag beside the name.
		 *
		 * @param string       $label    The words, not escaped.
		 * @param string       $state    The state, or empty.
		 * @param object|array $row      The row.
		 *
		 * @since 1.0.0
		 */
		$label = (string) apply_filters( "sugarcommerce_table_highlight_label_{$table_id}", $label, $state, $row );

		return '' === $label ? '' : ' <span class="screen-reader-text">' . esc_html( $label ) . '</span>';
	}

	/**
	 * The words in the row's first cell, unless the table says it draws them.
	 *
	 * Drawn by the table after the primary column's content, before the row
	 * actions. A table with `announce` off calls label() where it wants it.
	 *
	 * @param string       $table_id The table's id.
	 * @param object|array $row      The row.
	 *
	 * @return string Markup, or empty.
	 * @since 1.0.0
	 */
	public static function announce( string $table_id, $row ): string {
		$list = Seen::get( $table_id );

		if ( null === $list || false === ( $list['highlights']['announce'] ?? true ) ) {
			return '';
		}

		return self::label( $table_id, $row );
	}

	/**
	 * Forget the states worked out on this request.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function reset(): void {
		self::$states = [];
	}

	/**
	 * A state's class on a table: `{class}-{state}`.
	 *
	 * @param string $table_id The table's id.
	 * @param string $state    The state.
	 *
	 * @return string
	 */
	public static function class_name( string $table_id, string $state ): string {
		$prefix = sanitize_html_class( (string) ( Seen::get( $table_id )['highlights']['class'] ?? '' ) );

		return ( '' !== $prefix ? $prefix : 'sugarcommerce-row' ) . '-' . $state;
	}

	/**
	 * The states a table has without saying so.
	 *
	 * @param string               $table_id The table's id.
	 * @param array<string, mixed> $config   The list.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function built_in( string $table_id, array $config ): array {
		if ( ! $config['on'] ) {
			return [];
		}

		$states = [];

		// A row its list calls blocked: shut out, and not news. A standing
		// state, the same for everybody on every visit, until it is undone.
		if ( [] !== $config['blocked'] ) {
			$states['blocked'] = [
				'label'    => __( 'Blocked', 'sugarcommerce' ),
				'color'    => '#d63638',
				'priority' => 10,
				'forced'   => 'dashed',
				'when'     => static fn( $row ): bool => Rows::is_blocked( $table_id, $row ) && ! Rows::is_excused( $table_id, $row ),
			];
		}

		// Blocked, and let through all the same: blocked in name only.
		if ( null !== $config['excused'] ) {
			$states['excused'] = [
				'label'    => __( 'Blocked, but allowed', 'sugarcommerce' ),
				'color'    => '#dba617',
				'priority' => 20,
				'forced'   => 'double',
				'when'     => static fn( $row ): bool => Rows::is_excused( $table_id, $row ),
			];
		}

		// New to whoever is looking. Core's own green for "it worked": the
		// bar core gives a comment waiting to be approved, in the same
		// place and of the same width, in green where core's is red.
		$states['new'] = [
			'label'    => (string) $config['label'],
			'color'    => '#00a32a',
			'priority' => 50,
			'forced'   => 'highlight',
			'when'     => static fn( $row ): bool => Rows::is_new( $table_id, $row ),
		];

		return $states;
	}
}
