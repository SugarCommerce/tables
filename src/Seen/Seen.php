<?php
/**
 * What is new on a list to whoever is looking at it.
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
 * A marker for each person on each list: how far down it they have seen.
 *
 * "New" is each person's own. It is new since *you* last opened that list,
 * so two people running one site each have their own and neither clears the
 * other's. What is remembered is a number, not a date: the id of the newest
 * row there was when the list was last opened. Everything with a higher id
 * has arrived since, which is a question the primary key answers without
 * reading a row.
 *
 * And no older than a few days (`window`, seven unless the table says
 * otherwise). The marker alone was wrong at both ends: somebody back from a
 * fortnight away had two hundred rows lit, which is a list with nothing lit;
 * and somebody who had never opened the list had either all of it or none.
 * With the window the first is the last week, and so is the second -- a
 * marker that is not there is a marker at nought, and the window is what
 * stops that being everything.
 *
 * A table turns it on with a `seen` key. The table registers it here as it
 * is registered; a plugin that registers its tables on admin screens only
 * calls `Seen::register()` itself on every request as well, because a row
 * arrives in a webhook and the count beside the menu has to hear about it.
 * What is hooked everywhere reads nothing: everything that reads is hooked to
 * the list being drawn and to the admin menu.
 *
 * The parts are split by what they touch: Markers is the user meta, Rows the
 * rows and the count, Highlights how a row is lit, MenuCount the bubble.
 *
 * @since 1.0.0
 */
final class Seen {

	/**
	 * The lists that have a marker, by table id, as registered.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $lists = [];

	/**
	 * The hooks already listened to, so registering twice hooks once.
	 *
	 * @var array<string, true>
	 */
	private static array $hooked = [];

	/**
	 * The marker each list's rows are lit against on this request.
	 *
	 * @var array<string, int>
	 */
	private static array $from = [];

	/**
	 * The newest row there was as each list was drawn on this request.
	 *
	 * @var array<string, int>
	 */
	private static array $newest = [];

	/**
	 * Give a list a marker, or say more about one that has one.
	 *
	 * `$config` is the table's own configuration, or as much of it as is
	 * known: its `seen` and `highlights`, and -- once the table itself is
	 * registered -- the `menu_slug`, `parent_slug` and `capabilities` the
	 * menu count and the capability are read from. Called a second time for
	 * the same table it adds what the first did not know.
	 *
	 * @param string $table_id The table's id.
	 * @param array  $config   The table's configuration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function register( string $table_id, array $config ): void {
		if ( '' === $table_id || ( empty( $config['seen'] ) && empty( $config['highlights'] ) ) ) {
			return;
		}

		// What was said before, and what is said now on top of it: the keys
		// this call leaves empty are ones it did not know, not ones it is
		// taking away.
		$raw = array_merge(
			self::$lists[ $table_id ]['raw'] ?? [],
			array_filter( $config, static fn( $value ): bool => null !== $value && '' !== $value && [] !== $value )
		);

		self::$lists[ $table_id ] = Config::normalise( $table_id, $raw );

		self::listen( $table_id );
	}

	/**
	 * Every list that has a marker or lit rows, by table id.
	 *
	 * @return array<string, array<string, mixed>>
	 * @since 1.0.0
	 */
	public static function lists(): array {
		return self::$lists;
	}

	/**
	 * One list, as registered; null for a table that has none.
	 *
	 * @param string $table_id The table's id.
	 *
	 * @return array<string, mixed>|null
	 * @since 1.0.0
	 */
	public static function get( string $table_id ): ?array {
		return self::$lists[ $table_id ] ?? null;
	}

	/**
	 * Whether a table keeps a marker, rather than only lighting rows.
	 *
	 * @param string $table_id The table's id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function has_marker( string $table_id ): bool {
		return ! empty( self::$lists[ $table_id ]['on'] );
	}

	/**
	 * Which of a list's sets of rows the site is showing.
	 *
	 * A list that is kept apart -- test rows and real ones, never both --
	 * names its sets in `scopes` and which one is showing in `scope`. Each
	 * person has a marker in each.
	 *
	 * @param string $table_id The table's id.
	 *
	 * @return string The set's name; empty for a list that has one set.
	 * @since 1.0.0
	 */
	public static function scope( string $table_id ): string {
		$list = self::$lists[ $table_id ] ?? null;

		if ( null === $list || [] === $list['scopes'] || null === $list['scope'] ) {
			return '';
		}

		$scope = (string) call_user_func( $list['scope'] );

		return isset( $list['scopes'][ $scope ] ) ? $scope : '';
	}

	/**
	 * What somebody needs to be able to do to be told what is new on a list.
	 *
	 * The one that can open the list, unless the table says otherwise.
	 * Somebody without it is given no count, and none is worked out for them.
	 *
	 * @param string $table_id The table's id.
	 *
	 * @return string A capability.
	 * @since 1.0.0
	 */
	public static function capability( string $table_id ): string {
		/**
		 * Filter the capability that is told what is new on a list.
		 *
		 * @param string $capability A capability.
		 * @param string $table_id   The table's id.
		 *
		 * @since 1.0.0
		 */
		return (string) apply_filters(
			"sugarcommerce_table_seen_capability_{$table_id}",
			(string) ( self::$lists[ $table_id ]['capability'] ?? 'manage_options' ),
			$table_id
		);
	}

	/**
	 * How many rows of a list are new to somebody.
	 *
	 * The figure the menu's bubble shows, for anything else that wants to
	 * show it: a dashboard, an app. Nought, without asking the database
	 * anything, for a list that has no marker and for somebody who may not
	 * see it. In the set the site is showing.
	 *
	 * @param string   $table_id The table's id.
	 * @param int|null $user_id  Whose; whoever is signed in when not said.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count( string $table_id, ?int $user_id = null ): int {
		$user_id = $user_id ?? (int) get_current_user_id();
		$scope   = self::scope( $table_id );

		/**
		 * Filter how many rows of a list are new to somebody.
		 *
		 * @param int    $count    How many.
		 * @param int    $user_id  Whose.
		 * @param string $scope    The set that was counted; empty for a list that has one.
		 * @param string $table_id The table's id.
		 *
		 * @since 1.0.0
		 */
		$count = (int) apply_filters(
			"sugarcommerce_table_new_count_{$table_id}",
			Rows::count( $table_id, $user_id, $scope ),
			$user_id,
			$scope,
			$table_id
		);

		return max( 0, $count );
	}

	/**
	 * Whether a row is new to whoever is looking. See Rows::is_new().
	 *
	 * @param string       $table_id The table's id.
	 * @param object|array $row      The row.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_new( string $table_id, $row ): bool {
		return Rows::is_new( $table_id, $row );
	}

	/**
	 * Listen for rows arriving, and for the list being opened.
	 *
	 * The ones that hear about a row are hooked everywhere, because a row
	 * arrives in a webhook. They read nothing. Everything that reads is
	 * hooked to the list being drawn, so a visitor's request, a webhook, a
	 * REST call and every other admin screen run none of it.
	 *
	 * Each is one method for every list, which works out the list from the
	 * name of the action it was called by. A closure for each list would do
	 * as well and could not be found again: this way anything can see what
	 * is hooked, and take it off.
	 *
	 * @param string $table_id The table's id.
	 *
	 * @return void
	 */
	private static function listen( string $table_id ): void {
		$list = self::$lists[ $table_id ];

		if ( $list['on'] ) {
			foreach ( $list['changed'] as $hook ) {
				self::hook( $hook, 'changed', 10 );
			}

			// Early, before anything that might change who is signed in.
			foreach ( array_keys( $list['by_hand'] ) as $hook ) {
				self::hook( $hook, 'made_by_hand', 5, 10 );
			}

			self::hook( "sugarcommerce_before_render_table_{$table_id}", 'opened', 10 );
			self::hook( "sugarcommerce_after_render_table_{$table_id}", 'looked', 10 );

			if ( false !== $list['menu_count'] ) {
				// After a plugin that rebuilds its submenu late in admin_menu.
				add_action( 'admin_menu', [ MenuCount::class, 'badge' ], 1000 );
			}
		}

		Bars::listen();
	}

	/**
	 * Hook one of this class's methods, once.
	 *
	 * @param string $hook     The action.
	 * @param string $method   The method.
	 * @param int    $priority Priority.
	 * @param int    $args     How many arguments it takes.
	 *
	 * @return void
	 */
	private static function hook( string $hook, string $method, int $priority, int $args = 1 ): void {
		$key = $hook . '|' . $method;

		if ( isset( self::$hooked[ $key ] ) ) {
			return;
		}

		self::$hooked[ $key ] = true;

		add_action( $hook, [ self::class, $method ], $priority, $args );
	}

	/**
	 * A row arrived or went: every count kept for its list is out of date.
	 *
	 * @param mixed  $unused   What the action passed; the row's id.
	 * @param string $table_id The list, when this is not called by one of its `changed` actions.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function changed( $unused = null, string $table_id = '' ): void {
		$action = (string) current_action();

		foreach ( self::$lists as $id => $list ) {
			if ( $id === $table_id || in_array( $action, $list['changed'], true ) ) {
				Rows::touch( (string) $id );
			}
		}
	}

	/**
	 * Somebody signed in has just made a row by hand: it is theirs.
	 *
	 * Hooked to each list's `by_hand` actions, which are fired where the
	 * making is done. A row made in the admin is not news to whoever made it:
	 * they were there. It is to everybody else. An action that also fires for
	 * rows nobody made by hand -- a checkout, where whoever is signed in is
	 * the buyer -- names a callable that is given the action's arguments and
	 * answers with the row's id, or nought for "not by hand", or the id and
	 * the set it belongs to.
	 *
	 * @param mixed ...$args What the action passed.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function made_by_hand( ...$args ): void {
		$action  = (string) current_action();
		$user_id = (int) get_current_user_id();

		foreach ( self::$lists as $id => $list ) {
			if ( ! array_key_exists( $action, $list['by_hand'] ) ) {
				continue;
			}

			$ask    = $list['by_hand'][ $action ];
			$answer = null !== $ask ? call_user_func_array( $ask, $args ) : ( $args[0] ?? 0 );
			$row    = is_array( $answer ) ? (int) ( $answer[0] ?? 0 ) : (int) $answer;
			$scope  = is_array( $answer ) && isset( $answer[1] ) ? (string) $answer[1] : self::scope( (string) $id );

			Markers::add_own( (string) $id, $user_id, $scope, $row );
		}
	}

	/**
	 * A list is about to be drawn: note what is new on it.
	 *
	 * The marker is read here and moved in looked(), once the rows are out,
	 * so the rows that were new are lit for this one visit and plain on the
	 * next.
	 *
	 * @param mixed $table_id The table's id, or whatever the action passed.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function opened( $table_id = '' ): void {
		$id      = self::named( $table_id, 'sugarcommerce_before_render_table_' );
		$user_id = (int) get_current_user_id();

		if ( '' === $id || $user_id < 1 || ! self::has_marker( $id ) ) {
			return;
		}

		$scope = self::scope( $id );

		self::$from[ $id ]   = Markers::seen( $id, $user_id, $scope );
		self::$newest[ $id ] = Rows::newest( $id, $scope );

		Markers::show( $id, $user_id, $scope, self::$from[ $id ] );
	}

	/**
	 * A list has been drawn: everything up to now has been seen.
	 *
	 * Whichever page of the list, and however it was filtered. Up to the
	 * newest row there was when the drawing began and no further, so a row
	 * that arrived while the page was being built is still new.
	 *
	 * @param mixed $table_id The table's id, or whatever the action passed.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function looked( $table_id = '' ): void {
		$id = self::named( $table_id, 'sugarcommerce_after_render_table_' );

		if ( '' !== $id && isset( self::$from[ $id ] ) ) {
			Markers::mark( $id, (int) get_current_user_id(), self::scope( $id ), self::$newest[ $id ] );
		}
	}

	/**
	 * Say that somebody has seen every row a list has.
	 *
	 * What opening the list does, for anything else that shows somebody its
	 * rows -- a dashboard, an app.
	 *
	 * @param string   $table_id The table's id.
	 * @param int|null $user_id  Who; whoever is signed in when not said.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function mark_all( string $table_id, ?int $user_id = null ): void {
		if ( self::has_marker( $table_id ) ) {
			$scope = self::scope( $table_id );

			Markers::mark( $table_id, $user_id ?? (int) get_current_user_id(), $scope, Rows::newest( $table_id, $scope ) );
		}
	}

	/**
	 * The marker a list's rows are lit against on this request.
	 *
	 * The one the page was drawn from when it is being drawn; otherwise the
	 * one the last page was drawn from. A row redrawn on its own -- after a
	 * quick edit, over ajax -- is a request the list was not opened on, and
	 * is held to the marker of the page it belongs to, so it comes back lit
	 * as it was.
	 *
	 * @param string $table_id The table's id.
	 * @param int    $user_id  Whose.
	 * @param string $scope    The set.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function from( string $table_id, int $user_id, string $scope ): int {
		return self::$from[ $table_id ] ?? Markers::shown( $table_id, $user_id, $scope );
	}

	/**
	 * Forget what this request worked out, and what is registered.
	 *
	 * For tests, which draw a list more than once in one request.
	 *
	 * @param bool $everything Forget the registered lists as well.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function reset( bool $everything = false ): void {
		self::$from   = [];
		self::$newest = [];

		Rows::reset();
		Highlights::reset();

		if ( $everything ) {
			self::$lists  = [];
			self::$hooked = [];

			Bars::reset();
		}
	}

	/**
	 * The list a table's action is about, or the one that was named.
	 *
	 * @param mixed  $table_id A table id, or whatever the action passed.
	 * @param string $prefix   What the action's name begins with, before the table.
	 *
	 * @return string The table id; empty when it is not one of these.
	 */
	private static function named( $table_id, string $prefix ): string {
		if ( is_string( $table_id ) && isset( self::$lists[ $table_id ] ) ) {
			return $table_id;
		}

		$action = (string) current_action();

		return str_starts_with( $action, $prefix ) && isset( self::$lists[ substr( $action, strlen( $prefix ) ) ] )
			? substr( $action, strlen( $prefix ) )
			: '';
	}
}
