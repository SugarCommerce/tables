<?php
/**
 * Hidden Columns
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables;

use SugarCommerce\Plugin\Assets;
use SugarCommerce\Tables\Editing\Routes;
use SugarCommerce\Tables\Utils\Runtime;
use SugarCommerce\Tables\Support\ScriptConfig;

defined( 'ABSPATH' ) || exit;

/**
 * A column that is hidden is not worked out.
 *
 * Core draws every column of every row and hides the ones switched off in
 * Screen Options with a class, so a hidden column still runs its callback.
 * For most that is a value off the row and costs nothing. For a column that
 * has to ask another table -- what a customer has bought, where a key was
 * last used -- it is a query a page for something nobody is looking at.
 *
 * A column declared `'deferred' => true` is therefore not drawn while it is
 * hidden: its cells hold placeholder() and its callback is never called.
 * One that has more to decide -- a date it can show for free and a line
 * under it it cannot -- declares `'deferred' => 'manual'`, asks is_hidden()
 * itself, and draws placeholder() where it chooses.
 *
 * Which leaves the moment the column is switched on: core shows it there and
 * then, without asking the server for anything, and it would be a column of
 * blanks. The deferred-columns script (assets/js/columns/deferred.js) sees a
 * column of placeholders being shown, saves the choice as core does, and
 * loads the list again once it is saved.
 *
 * @since 1.0.0
 */
final class HiddenColumns {

	/**
	 * The class the placeholder carries, and the script looks for.
	 */
	public const string PLACEHOLDER_CLASS = 'sugarcommerce-column-deferred';

	/**
	 * Whether a column of a table is hidden for whoever is looking.
	 *
	 * The same reading the table makes when it draws the list: what this
	 * person saved in Screen Options, and what the table declares hidden
	 * when they have saved nothing. So a column this calls hidden is one the
	 * table is hiding.
	 *
	 * @param string $table_id The table, as it was registered.
	 * @param string $column   The column.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_hidden( string $table_id, string $column ): bool {
		$hidden    = self::of( $table_id, (array) Tables::get_table( $table_id ) );
		$is_hidden = in_array( $column, $hidden, true );

		/**
		 * Filter whether a column of a table is hidden, for working it out.
		 *
		 * A hidden column is not worked out. Return false for one that
		 * something else reads off the page whether it is shown or not.
		 *
		 * @param bool   $is_hidden Whether it is hidden.
		 * @param string $column    The column.
		 *
		 * @since 1.0.0
		 */
		return (bool) apply_filters( "sugarcommerce_table_column_hidden_{$table_id}", $is_hidden, $column );
	}

	/**
	 * The columns of a table hidden for whoever is looking.
	 *
	 * Core's reading, get_hidden_columns(): what this person saved in Screen
	 * Options, and when they have saved nothing, `default_hidden_columns` --
	 * which the table answers on its own screen with its `hidden_columns`
	 * (see ScreenSetup) -- then core's `hidden_columns` filter. With no
	 * screen (an ajax request draws a row) there is no saved choice to read,
	 * and the answer is what the table declares.
	 *
	 * @param string $table_id The table, as it was registered.
	 * @param array  $config   Its configuration.
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	public static function of( string $table_id, array $config ): array {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && function_exists( 'get_hidden_columns' ) ) {
			return array_map( 'strval', (array) get_hidden_columns( $screen ) );
		}

		/**
		 * Filter the columns a table hides until someone chooses their own.
		 *
		 * @param array  $hidden Hidden column keys.
		 * @param array  $config Table configuration.
		 *
		 * @since 1.0.0
		 */
		return array_map( 'strval', (array) apply_filters( "sugarcommerce_table_hidden_columns_{$table_id}", $config['hidden_columns'] ?? [], $config ) );
	}

	/**
	 * What a column that was not worked out holds.
	 *
	 * Nothing to read, and a mark for the script that loads the list again
	 * when the column is switched on.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function placeholder(): string {
		return '<span class="' . self::PLACEHOLDER_CLASS . '" aria-hidden="true">&hellip;</span>';
	}

	/**
	 * Whether a table has any column worked out only while shown.
	 *
	 * @param array $config Table configuration.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function any( array $config ): bool {
		foreach ( (array) ( $config['columns'] ?? [] ) as $column ) {
			if ( is_array( $column ) && ! empty( $column['deferred'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The script that reloads the list when such a column is switched on.
	 *
	 * @param array  $config Table configuration.
	 * @param string $id     Table id, for the route the choice is saved through.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function enqueue( array $config, string $id = '' ): void {
		if ( ! self::any( $config ) ) {
			return;
		}

		// The asset library's class, which a bundled copy prefixes, and not
		// its global function, which belongs to whichever copy loaded first.
		Assets::enqueue_built_script( Runtime::handle( 'deferred-columns' ), __FILE__, 'deferred-columns' );

		ScriptConfig::attach(
			Runtime::handle( 'deferred-columns' ),
			[ 'path' => Routes::path( $id, 'hidden-columns' ) ]
		);
	}
}
