<?php
/**
 * Admin Tables Registration Manager
 *
 * Central manager class for registering and rendering WordPress admin list tables.
 * Provides a configuration-driven approach to creating admin tables with support for:
 * - Automatic menu page registration
 * - Column definitions with automatic formatting
 * - Row actions (edit, delete, custom)
 * - Bulk actions with callbacks
 * - Status views (tabs)
 * - Dropdown filters
 * - Search functionality
 * - Screen options (items per page)
 * - Help tabs
 * - Modern EDD-style headers
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables;

use SugarCommerce\Tables\Traits\Tables\ActionProcessing;
use SugarCommerce\Tables\Traits\Tables\AdminNotices;
use SugarCommerce\Tables\Traits\Tables\AssetManager;
use SugarCommerce\Tables\Traits\Tables\BulkProcessing;
use SugarCommerce\Tables\Traits\Tables\MenuBuilder;
use SugarCommerce\Tables\Traits\Tables\PageRenderer;
use SugarCommerce\Tables\Traits\Tables\Registration;
use SugarCommerce\Tables\Traits\Tables\ScreenSetup;
use SugarCommerce\Tables\Traits\Tables\Urls;
use SugarCommerce\Tables\Utils\Runtime;
use SugarCommerce\Tables\Editing\InlineEditSave;
use SugarCommerce\Tables\Support\Request;

// Exit if accessed directly

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Class Tables
 *
 * Static manager class for admin table registration and rendering.
 *
 * ## Basic Usage
 *
 * ```php
 * // Register a table (menu page is created automatically)
 * Tables::register( 'my_customers', [
 *     'page_title'  => 'Customers',
 *     'menu_title'  => 'Customers',
 *     'menu_slug'   => 'my-plugin-customers',
 *     'parent_slug' => 'my-plugin',
 *     'capability'  => 'manage_options',
 *     'labels'      => [
 *         'singular' => 'customer',
 *         'plural'   => 'customers',
 *     ],
 *     'columns'     => [
 *         'name'   => 'Name',
 *         'email'  => 'Email',
 *         'status' => 'Status',
 *     ],
 *     'callbacks'   => [
 *         'get_items'  => [ Customers::class, 'query' ],
 *         'get_counts' => [ Customers::class, 'get_counts' ],
 *     ],
 * ] );
 * ```
 *
 * ## Configuration Options
 *
 * | Option              | Type              | Description                                      |
 * |---------------------|-------------------|--------------------------------------------------|
 * | page_title          | string            | Page title tag text                              |
 * | menu_title          | string            | Menu item text                                   |
 * | menu_slug           | string            | Admin page slug (required)                       |
 * | parent_slug         | string            | Parent menu slug (for submenu pages)             |
 * | capability          | string            | Capability required to view page                 |
 * | icon                | string            | Dashicon or icon URL (top-level only)            |
 * | position            | int|null          | Menu position (top-level only)                   |
 * | labels              | array             | UI labels (singular, plural, title, etc.)        |
 * | columns             | array             | Column definitions                               |
 * | sortable            | array             | Sortable column keys                             |
 * | primary_column      | string            | Column for row actions; a phone shows only it    |
 * | summary             | array|false       | Columns repeated under the title on a phone      |
 * | hidden_columns      | array             | Columns hidden by default                        |
 * | row_actions         | array|callable    | Row action definitions                           |
 * | bulk_actions        | array             | Bulk action definitions                          |
 * | views               | array             | Status view definitions                          |
 * | filters             | array             | Dropdown filter definitions                      |
 * | callbacks           | array             | Data callbacks (get_items, get_counts, delete)   |
 * | capabilities        | array             | Per-action capabilities (overrides capability)   |
 * | per_page            | int               | Items per page default (30)                      |
 * | searchable          | bool              | Enable search box (true)                         |
 * | header_title        | string            | Custom header title                              |
 * | flyouts             | array             | Flyout IDs ['edit' => '', 'view' => '']          |
 * | add_button          | string|callable   | Add button: flyout ID, URL, or callback          |
 * | help                | array             | Help tab definitions                             |
 * | body_class          | string            | Additional CSS class for admin body               |
 *
 * ## Action Hooks
 *
 * - `sugarcommerce_after_render_table_header_{$id}` - After the heading and notices, before the status links
 * - `sugarcommerce_before_render_table_{$id}`    - Before the table renders
 * - `sugarcommerce_after_render_table_{$id}`     - After it renders
 * - `sugarcommerce_table_item_deleted_{$id}`     - After an item is deleted
 * - `sugarcommerce_table_bulk_action_{$id}`      - After a bulk action
 * - `sugarcommerce_table_bulk_action_{$id}_{$action}` - After one particular bulk action
 * - `sugarcommerce_table_single_action_{$id}`    - A row action with no handler of its own
 * - `sugarcommerce_table_admin_notices_{$id}`    - Notices above the table
 *
 * ## Filter Hooks
 *
 * - `sugarcommerce_table_admin_notices_{$id}`    - Custom notices for specific table
 *
 * @since 1.0.0
 */
final class Tables {

    use ActionProcessing;
    use AdminNotices;
    use AssetManager;
    use BulkProcessing;
    use MenuBuilder;
    use PageRenderer;
    use Registration;
    use ScreenSetup;
    use Urls;

    /* =========================================================================
     * PROPERTIES
     * ========================================================================= */

    /**
     * Registered tables storage
     *
     * Associative array of table ID => configuration pairs.
     * Populated by register() calls.
     *
     * @since 1.0.0
     * @var array<string, array>
     */
    private static array $tables = [];

    /**
     * Hook suffixes for registered menu pages
     *
     * Maps table ID => hook suffix returned by add_menu_page/add_submenu_page.
     * Used for targeting screen options and help tabs.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    private static array $hook_suffixes = [];

    /**
     * Asset enqueue flag
     *
     * Prevents duplicate asset enqueuing when multiple tables
     * are registered on the same page.
     *
     * @since 1.0.0
     * @var bool
     */
    private static bool $assets_enqueued = false;

    /**
     * Initialization flag
     *
     * Prevents duplicate hook registration when multiple tables
     * are registered.
     *
     * @since 1.0.0
     * @var bool
     */
    private static bool $initialized = false;

    /* =========================================================================
     * REGISTRATION
     * ========================================================================= */

/* =========================================================================
     * INITIALIZATION
     * ========================================================================= */

    /**
     * Initialize the manager
     *
     * Hooks into WordPress admin to enable menu registration, action processing,
     * screen options, and asset enqueuing. Called automatically on first
     * register() call.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public static function init(): void {
        // Only initialize once
        if ( self::$initialized ) {
            return;
        }
        self::$initialized = true;

        // Handle screen option saves (must be early, before WP processes the form)
        self::handle_screen_options();

        // Register admin menu pages
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );

        // Process actions early (before output) to enable redirects
        add_action( 'admin_init', [ __CLASS__, 'process_early_actions' ], 20 );

        // Setup screen options after page loads
        add_action( 'admin_init', [ __CLASS__, 'setup_load_hooks' ], 999 );

        // Enqueue CSS/JS assets
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // Quick Edit saves over ajax, which is a separate request that never
        // touches the admin page or its screen hooks -- so the endpoint is
        // registered here rather than anywhere near the table it saves.
        add_action( 'wp_ajax_' . Runtime::key( 'quick_edit' ), [ InlineEditSave::class, 'handle_quick_edit' ] );

        // Add body classes for table pages
        // Which action a notice is for rides in the address, and core tidies
        // the arguments it knows out of the address bar once the page has
        // loaded. These two are ours, so core has to be told: left in, a
        // reload repeats the notice and a copied link carries it to
        // somebody else.
        add_filter(
            'removable_query_args',
            static fn( $args ): array => array_merge( (array) $args, [ '_row_action', '_bulk_action', ActionNotice::ARG ] )
        );

        add_filter( 'admin_body_class', [ __CLASS__, 'add_body_class' ] );

        // Fix menu highlight for submenu pages
        add_filter( 'parent_file', [ __CLASS__, 'fix_parent_menu_highlight' ] );
        add_filter( 'submenu_file', [ __CLASS__, 'fix_submenu_highlight' ] );
    }

    /* =========================================================================
     * MENU REGISTRATION
     * ========================================================================= */

/* =========================================================================
     * ASSETS
     * ========================================================================= */

/* =========================================================================
     * SCREEN OPTIONS
     * ========================================================================= */

/* =========================================================================
     * ACTION PROCESSING
     * ========================================================================= */

/* =========================================================================
     * RENDERING
     * ========================================================================= */

/* =========================================================================
     * UTILITY METHODS
     * ========================================================================= */

    /**
     * Get clean base URL for redirects
     *
     * Builds a URL with the page parameter and preserves status, search,
     * and custom filter parameters. Used for post-action redirects.
     *
     * @param array $config Table configuration.
     *
     * @return string Clean admin URL.
     * @since 1.0.0
     *
     */
    /**
     * A callable that draws one table, for a page made by hand.
     *
     * Tables::register() makes the menu page itself; this is for a table
     * drawn inside a page of your own, as that page's callback.
     *
     * @param string $table_id Table id, as registered.
     *
     * @return callable
     * @since 1.0.0
     */
    public static function renderer( string $table_id ): callable {
        return static function () use ( $table_id ): void {
            self::render_table( $table_id );
        };
    }

    /**
     * Add body classes to admin table pages
     *
     * Adds CSS classes to the admin body element for styling table pages.
     * Classes added:
     * - `admin-table` - Generic class for all table pages
     * - `admin-table-{$id}` - Table-specific class
     * - Custom class from `body_class` config option
     *
     * @param string $classes Space-separated list of body classes.
     *
     * @return string Modified classes string.
     * @since 1.0.0
     *
     */
    public static function add_body_class( string $classes ): string {
        $page = Request::text( 'page' );

        if ( empty( $page ) ) {
            return $classes;
        }

        // Find matching table config
        foreach ( self::$tables as $id => $config ) {
            if ( $config['menu_slug'] === $page ) {
                // Add generic table class
                $classes .= ' admin-table';

                // Add table-specific class
                $classes .= ' admin-table-' . sanitize_html_class( $id );

                // Add custom class from config if provided
                if ( ! empty( $config['body_class'] ) ) {
                    $classes .= ' ' . sanitize_html_class( $config['body_class'] );
                }

                break;
            }
        }

        return $classes;
    }

    /* =========================================================================
     * TABLE MANAGEMENT
     * ========================================================================= */

    /**
     * Get a registered table configuration
     *
     * @param string $id Table identifier.
     *
     * @return array|null Table configuration or null if not found.
     * @since 1.0.0
     *
     */
    public static function get_table( string $id ): ?array {
        return self::$tables[ $id ] ?? null;
    }

    /**
     * Check if a table is registered
     *
     * @param string $id Table identifier.
     *
     * @return bool True if registered.
     * @since 1.0.0
     *
     */
    public static function has_table( string $id ): bool {
        return isset( self::$tables[ $id ] );
    }

    /**
     * Unregister a table
     *
     * Removes a table from the registry. Useful for conditional table removal.
     *
     * @param string $id Table identifier.
     *
     * @return bool True if removed, false if not found.
     * @since 1.0.0
     *
     */
    public static function unregister( string $id ): bool {
        if ( isset( self::$tables[ $id ] ) ) {
            unset( self::$tables[ $id ] );

            return true;
        }

        return false;
    }

    /**
     * The name a table's selected rows are posted under, and its bulk nonce.
     *
     * The plural label, as core reduces it: WP_List_Table passes its
     * `plural` through sanitize_key() before it prints the bulk nonce, so
     * "blocked emails" is signed as `bulk-blockedemails`. Read back as the
     * label it was checked as `bulk-blocked emails` and never matched, and
     * the checkboxes, posted as `blocked emails[]`, arrived as
     * `blocked_emails` -- PHP turns a space in a request name into an
     * underscore -- so every bulk action on a list with a two-word plural
     * did nothing, and said nothing. One reduction, used by all four.
     *
     * @param array $config Table configuration.
     *
     * @return string
     * @since 1.0.0
     */
    public static function bulk_key( array $config ): string {
        $key = sanitize_key( (string) ( $config['labels']['plural'] ?? '' ) );

        return '' !== $key ? $key : 'items';
    }

    /**
     * Get all registered tables
     *
     * @return array All registered table configurations.
     * @since 1.0.0
     *
     */
    public static function get_all_tables(): array {
        return self::$tables;
    }

    /**
     * Get the hook suffix for a registered table
     *
     * Returns the hook suffix from add_menu_page/add_submenu_page,
     * useful for targeting specific admin pages.
     *
     * @param string $id Table identifier.
     *
     * @return string|null Hook suffix or null if not found.
     * @since 1.0.0
     *
     */
    public static function get_hook_suffix( string $id ): ?string {
        return self::$hook_suffixes[ $id ] ?? null;
    }
}
