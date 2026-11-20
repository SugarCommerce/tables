<?php
/**
 * A stand-in for core's WP_List_Table.
 *
 * `Table` requires the real one from ABSPATH, and ABSPATH is the tests
 * directory here — so this file sits exactly where that require looks and no
 * production code has to know a test is running.
 *
 * Only the members `Table` actually touches are here. Core's is fourteen
 * hundred lines of markup, and a copy of it would drift; what matters for a
 * test is that the subclass's own decisions — which columns, which views,
 * which query arguments — are reachable and correct.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

/**
 * The parts of core's list table this library builds on.
 */
class WP_List_Table {

	/**
	 * The rows.
	 *
	 * @var array<int, mixed>
	 */
	public $items = [];

	/**
	 * Columns, hidden columns, sortable columns, primary column.
	 *
	 * @var array<int, mixed>
	 */
	protected $_column_headers = [];

	/**
	 * What was passed to the constructor.
	 *
	 * @var array<string, mixed>
	 */
	protected $_args = [];

	/**
	 * What set_pagination_args() was given.
	 *
	 * @var array<string, mixed>
	 */
	protected $_pagination_args = [];

	/**
	 * The screen.
	 *
	 * @var object|null
	 */
	public $screen = null;

	/**
	 * Construct.
	 *
	 * @param array<string, mixed> $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$this->_args = (array) $args;
	}

	/**
	 * Record the pagination.
	 *
	 * @param array<string, mixed> $args Pagination arguments.
	 *
	 * @return void
	 */
	public function set_pagination_args( $args ) {
		$this->_pagination_args = (array) $args;
	}

	/**
	 * What set_pagination_args() was given.
	 *
	 * Not core's API — core has `get_pagination_arg( $key )` — but a test
	 * needs the whole lot, and reaching into a protected property from
	 * outside is worse than one accessor that says what it is for.
	 *
	 * @return array<string, mixed>
	 */
	public function pagination_args() {
		return $this->_pagination_args;
	}

	/**
	 * What the constructor was given.
	 *
	 * @return array<string, mixed>
	 */
	public function args() {
		return $this->_args;
	}

	/**
	 * The page being viewed.
	 *
	 * @return int
	 */
	public function get_pagenum() {
		$page = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1;

		return max( 1, $page );
	}

	/**
	 * How many rows a page holds, as core reads it: the user option, else the
	 * default, then the "{$option}" filter.
	 *
	 * @param string $option   The user option name.
	 * @param int    $fallback The default.
	 *
	 * @return int
	 */
	protected function get_items_per_page( $option, $fallback = 20 ) {
		$per_page = (int) get_user_option( $option );

		if ( empty( $per_page ) || $per_page < 1 ) {
			$per_page = $fallback;
		}

		return (int) apply_filters( "{$option}", $per_page );
	}

	/**
	 * The action the request is asking for.
	 *
	 * @return string|false
	 */
	public function current_action() {
		if ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			return $_REQUEST['action'];
		}

		return false;
	}

	/**
	 * The first column, which carries the row actions.
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		$columns = $this->get_columns();

		unset( $columns['cb'] );

		return (string) array_key_first( $columns );
	}

	/**
	 * The table's classes.
	 *
	 * @return string[]
	 */
	protected function get_table_classes() {
		return [ 'widefat', 'fixed', 'striped' ];
	}

	/**
	 * The columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return [];
	}

	/**
	 * Whether there is anything to show.
	 *
	 * @return bool
	 */
	public function has_items() {
		return ! empty( $this->items );
	}

	/**
	 * Row actions, as core renders them.
	 *
	 * @param array<string, string> $actions The actions.
	 * @param bool                  $always  Whether to always show them.
	 *
	 * @return string
	 */
	protected function row_actions( $actions, $always = false ) {
		if ( empty( $actions ) ) {
			return '';
		}

		$rendered = [];

		foreach ( $actions as $action => $link ) {
			$rendered[] = sprintf( '<span class="%s">%s</span>', $action, $link );
		}

		return '<div class="row-actions">' . implode( ' | ', $rendered ) . '</div>';
	}

	/**
	 * One row's cells.
	 *
	 * @param mixed $item The row.
	 *
	 * @return void
	 */
	protected function single_row_columns( $item ) {
		echo '<td>row</td>';
	}

	/**
	 * The heading row's cells, as core prints them: single-quoted classes,
	 * `hidden` on a column switched off, `td` for the checkbox, and no ids
	 * the second time, for the footer.
	 *
	 * @param bool $with_id Whether to print ids.
	 *
	 * @return void
	 */
	public function print_column_headers( $with_id = true ) {
		$columns = (array) ( $this->_column_headers[0] ?? [] );
		$hidden  = (array) ( $this->_column_headers[1] ?? [] );

		foreach ( $columns as $key => $label ) {
			$class = [ 'manage-column', "column-$key" ];

			if ( in_array( $key, $hidden, true ) ) {
				$class[] = 'hidden';
			}

			$tag   = 'cb' === $key ? 'td' : 'th';
			$scope = 'th' === $tag ? 'scope="col"' : '';
			$id    = $with_id ? "id='$key'" : '';

			echo "<$tag $scope $id class='" . implode( ' ', $class ) . "'  >$label</$tag>";
		}
	}

	/**
	 * The table.
	 *
	 * @return void
	 */
	public function display() {
		echo '<table class="' . esc_attr( implode( ' ', $this->get_table_classes() ) ) . '"></table>';
	}

	/**
	 * Members core declares and `Table` overrides, so `#[\Override]` has a
	 * parent to find. Core's bodies are not needed: `Table` never calls them.
	 *
	 * @return void
	 */
	public function prepare_items() {}

	/**
	 * See prepare_items().
	 *
	 * @return void
	 */
	public function no_items() {}

	/**
	 * See prepare_items().
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		return [];
	}

	/**
	 * See prepare_items().
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return [];
	}

	/**
	 * See prepare_items().
	 *
	 * @return array<string, mixed>
	 */
	protected function get_sortable_columns() {
		return [];
	}

	/**
	 * See prepare_items().
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {}

	/**
	 * See prepare_items().
	 *
	 * @param mixed  $item        The row.
	 * @param string $column_name The column.
	 * @return void
	 */
	protected function column_default( $item, $column_name ) {}

	/**
	 * See prepare_items().
	 *
	 * @param mixed $item The row.
	 * @return void
	 */
	protected function column_cb( $item ) {}

	/**
	 * See prepare_items().
	 *
	 * @param mixed $item The row.
	 * @return void
	 */
	public function single_row( $item ) {}

	/**
	 * See prepare_items().
	 *
	 * @param mixed  $item        The row.
	 * @param string $column_name The column.
	 * @param string $primary     The primary column.
	 * @return string
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		return '';
	}
}
