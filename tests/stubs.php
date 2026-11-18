<?php
/**
 * WordPress stubs for the test suite.
 *
 * This library is a list-table builder rather than a field renderer, so it
 * needs a different and much smaller set than the field libraries do:
 * escaping, dates, and the handful of lookups a formatted column makes.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// wp-composer-assets resolves a library's URL from these, and does it at
// the moment an asset is first asked for rather than on load.
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', dirname( __DIR__, 3 ) );
}

if ( ! defined( 'WP_CONTENT_URL' ) ) {
	define( 'WP_CONTENT_URL', 'https://example.test/wp-content' );
}

/**
 * Reset every stubbed global between tests.
 *
 * @return void
 */
function rt_reset_globals(): void {
	$GLOBALS['rt_options'] = [
		'date_format' => 'F j, Y',
		'time_format' => 'g:i a',
		'timezone_string' => 'UTC',
		'gmt_offset' => 0,
	];
	$GLOBALS['rt_users']   = [];
	$GLOBALS['rt_terms']   = [];

	// Everything allowed and every nonce good, so a test that is not about
	// security need not say so.
	$GLOBALS['rt_caps']     = null;
	$GLOBALS['rt_nonce_ok'] = true;

	unset(
		$GLOBALS['rt_screen'],
		$GLOBALS['rt_user_meta'],
		$GLOBALS['rt_redirect'],
		$GLOBALS['rt_fired'],
		$GLOBALS['rt_actions'],
		$GLOBALS['rt_filters'],
		$GLOBALS['rt_inline_styles'],
		$GLOBALS['rt_referer'],
		$GLOBALS['rt_user_id'],
		$GLOBALS['rt_nonce_actions'],
		$GLOBALS['rt_flyouts'],
		$GLOBALS['rt_localized'],
		$GLOBALS['rt_routes'],
		$GLOBALS['rt_meta_by_user'],
		$GLOBALS['rt_user_options'],
		$GLOBALS['rt_attachments'],
		$GLOBALS['rt_user_caps'],
		$GLOBALS['rt_meta_writes'],
		$GLOBALS['rt_cache'],
		$GLOBALS['rt_current_action']
	);

	$GLOBALS['rt_transients'] = [];
}

rt_reset_globals();

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url, $protocols = null, $context = 'display' ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : '';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class, $fallback = '' ) {
		$class = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $class );

		return '' === $class ? $fallback : $class;
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		if ( '' === $color ) {
			return '';
		}

		return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : null;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default_value = false ) {
		return $GLOBALS['rt_options'][ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals );
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		return $GLOBALS['rt_users'][ $user_id ] ?? false;
	}
}

if ( ! function_exists( 'get_edit_user_link' ) ) {
	function get_edit_user_link( $user_id = null ) {
		return 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $user_id;
	}
}

if ( ! function_exists( 'get_avatar' ) ) {
	function get_avatar( $id_or_email, $size = 96, $default = '', $alt = '' ) {
		return '<img class="avatar" alt="" />';
	}
}

if ( ! function_exists( 'mysql2date' ) ) {
	function mysql2date( $format, $date, $translate = true ) {
		$time = strtotime( (string) $date );

		return false === $time ? false : gmdate( (string) $format, $time );
	}
}

if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( $from, $to = 0 ) {
		return 'a while';
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return 'timestamp' === $type ? 1000000000 : gmdate( 'Y-m-d H:i:s', 1000000000 );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		return gmdate( (string) $format, $timestamp ?? 1000000000 );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', (string) $path );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'https://example.test/wp-content/plugins/x/';
	}
}

if ( ! function_exists( 'content_url' ) ) {
	function content_url( $path = '' ) {
		return WP_CONTENT_URL . '/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '', $scheme = null ) {
		return 'https://example.test/' . ltrim( (string) $path, '/' );
	}
}

/*
 * The status badge registers its own stylesheet the first time one is
 * rendered, which reaches wp-composer-assets and from there the whole asset
 * API. Stubbed rather than avoided, because "render a status" is exactly
 * what the test is for.
 *
 * Written out one at a time rather than generated in a loop. A loop needs the
 * runtime code interpreter to declare a function from a name, and this file
 * ships -- Composer installs tests/ alongside src/ unless a .gitattributes
 * says otherwise, and that construct is the first thing a WordPress security
 * scanner looks for in a plugin's vendor tree.
 */
if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( $handle, $css ) {
		$GLOBALS['rt_inline_styles'][ $handle ] = ( $GLOBALS['rt_inline_styles'][ $handle ] ?? '' ) . $css;

		return true;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_style_is' ) ) {
	function wp_style_is( $handle, $status = 'enqueued' ) {
		return false;
	}
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook ) {
		return 1;
	}
}

/*
 * Admin URLs, for the page-address tests.
 *
 * add_query_arg() is written out rather than approximated: the real one keeps
 * the argument order it was given and merges into an existing query string,
 * and both matter to what page_url() produces.
 */

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) {
			$pairs = $args[0];
			$url   = (string) ( $args[1] ?? '' );
		} else {
			$pairs = [ (string) $args[0] => $args[1] ];
			$url   = (string) ( $args[2] ?? '' );
		}

		$parts    = explode( '?', $url, 2 );
		$existing = [];

		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $existing );
		}

		$query = array_merge( $existing, $pairs );

		return '' === $query ? $parts[0] : $parts[0] . '?' . http_build_query( $query );
	}
}

if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $key, $url = '' ) {
		$parts = explode( '?', (string) $url, 2 );
		$query = [];

		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
		}

		foreach ( (array) $key as $one ) {
			unset( $query[ $one ] );
		}

		return [] === $query ? $parts[0] : $parts[0] . '?' . http_build_query( $query );
	}
}

/*
 * The screen, and the user meta the per-page option lives in. Null by default:
 * a table has a per-page long before anyone has opened Screen Options, and
 * that is the path worth having as the default in a test.
 */
if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen() {
		return $GLOBALS['rt_screen'] ?? null;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return $GLOBALS['rt_user_id'] ?? 1;
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key = '', $single = false ) {
		// Per person when a test kept it per person, and the old shared
		// table otherwise.
		return $GLOBALS['rt_meta_by_user'][ (int) $user_id ][ $key ] ?? ( $GLOBALS['rt_user_meta'][ $key ] ?? '' );
	}
}

if ( ! function_exists( 'get_user_option' ) ) {
	function get_user_option( $option, $user = 0 ) {
		return $GLOBALS['rt_user_options'][ $option ] ?? false;
	}
}

/*
 * Core's reading of the hidden columns (wp-admin/includes/screen.php): the
 * saved choice, or `default_hidden_columns`, then `hidden_columns`.
 */
if ( ! function_exists( 'get_hidden_columns' ) ) {
	function get_hidden_columns( $screen ) {
		$hidden       = get_user_option( 'manage' . $screen->id . 'columnshidden' );
		$use_defaults = ! is_array( $hidden );

		if ( $use_defaults ) {
			$hidden = apply_filters( 'default_hidden_columns', array(), $screen );
		}

		return apply_filters( 'hidden_columns', $hidden, $screen, $use_defaults );
	}
}

if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_get_attachment_image' ) ) {
	function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = '' ) {
		if ( empty( $GLOBALS['rt_attachments'][ (int) $id ] ) ) {
			return '';
		}

		$attr = (array) $attr;

		return sprintf(
			'<img src="%s" class="%s" alt="%s"%s />',
			$GLOBALS['rt_attachments'][ (int) $id ],
			$attr['class'] ?? '',
			$attr['alt'] ?? 'picture',
			isset( $attr['width'] ) ? ' width="' . $attr['width'] . '" height="' . $attr['height'] . '"' : ''
		);
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value, $prev = '' ) {
		$GLOBALS['rt_meta_by_user'][ (int) $user_id ][ $key ] = $value;
		$GLOBALS['rt_meta_writes'][]                          = $key;

		return true;
	}
}

if ( ! function_exists( 'delete_user_meta' ) ) {
	function delete_user_meta( $user_id, $key, $value = '' ) {
		unset( $GLOBALS['rt_meta_by_user'][ (int) $user_id ][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'user_can' ) ) {
	function user_can( $user, $capability, ...$args ) {
		$allowed = $GLOBALS['rt_user_caps'][ (int) $user ] ?? $GLOBALS['rt_caps'] ?? null;

		return null === $allowed || in_array( $capability, (array) $allowed, true );
	}
}

if ( ! function_exists( 'current_action' ) ) {
	function current_action() {
		$stack = $GLOBALS['rt_current_action'] ?? [];

		return [] === $stack ? false : end( $stack );
	}
}

/*
 * The object cache, as a request without a persistent one has it: kept for
 * the request.
 */
if ( ! function_exists( 'wp_cache_get' ) ) {
	function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
		$found = isset( $GLOBALS['rt_cache'][ $group ][ $key ] );

		return $found ? $GLOBALS['rt_cache'][ $group ][ $key ] : false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
		$GLOBALS['rt_cache'][ $group ][ $key ] = $data;

		return true;
	}
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

/*
 * The rest of what the manager reaches for. Registration fills a table's
 * configuration with defaults, and testing a table built any other way would
 * be testing a shape the library never produces.
 */
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['rt_actions'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['rt_filters'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['rt_filters'][ $hook ] ?? [] as $callback ) {
			$value = $callback( $value, ...$args );
		}

		return $value;
	}
}

/*
 * Hooks that actually hold what is hooked.
 *
 * These were defined twice in this file: an inert pair earlier that returned
 * true and dropped the callback, and this recording pair below. The earlier
 * definitions won the function_exists guard, so add_action stored nothing,
 * apply_filters ignored every filter, and do_action fired hooks nobody was
 * listening to. Any test asserting that a hook did something passed because
 * nothing could disagree.
 */
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		$GLOBALS['rt_fired'][]           = $hook;
		$GLOBALS['rt_current_action'][] = $hook;

		try {
			foreach ( $GLOBALS['rt_actions'][ $hook ] ?? [] as $callback ) {
				$callback( ...$args );
			}
		} finally {
			array_pop( $GLOBALS['rt_current_action'] );
		}
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$args ) {
		$url = is_array( $args[0] ) ? ( $args[1] ?? '' ) : ( $args[2] ?? '' );
		$add = is_array( $args[0] ) ? $args[0] : [ $args[0] => $args[1] ];

		return $url . ( str_contains( (string) $url, '?' ) ? '&' : '?' ) . http_build_query( $add );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

/*
 * The security primitives, so an action can be tested as refused rather than
 * only as performed.
 *
 * $GLOBALS['rt_caps'] is what the current user may do — null for everything,
 * which is the default so most tests need not care. $GLOBALS['rt_nonce_ok']
 * is whether a nonce checks out.
 */
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		$allowed = $GLOBALS['rt_caps'] ?? null;

		return null === $allowed || in_array( $capability, (array) $allowed, true );
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		// Which action each check was for, so a test can say a nonce was
		// checked against the name core signed it with.
		$GLOBALS['rt_nonce_actions'][] = $action;

		return ( $GLOBALS['rt_nonce_ok'] ?? true ) ? 1 : false;
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ) {
		return addslashes( (string) $text );
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
		return add_query_arg( $name, wp_create_nonce( $action ), $actionurl );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'nonce';
	}
}

/**
 * Thrown in place of the redirect, so a test can see what happened after it.
 *
 * Every action in this library ends `wp_safe_redirect(); exit;`, which is
 * right in production and ends the PHP process in a test — PHPUnit reports it
 * as "premature end of PHP process" and the assertions never run. Throwing
 * from the redirect means the exit is never reached and the test regains
 * control, with the destination to inspect.
 */
final class RT_Redirected extends \RuntimeException {

	/**
	 * Where it was going.
	 *
	 * @var string
	 */
	public string $location;

	/**
	 * @param string $location The destination.
	 */
	public function __construct( string $location ) {
		parent::__construct( 'Redirected to ' . $location );

		$this->location = $location;
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) {
		$GLOBALS['rt_redirect'] = $location;

		throw new RT_Redirected( (string) $location );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = [] ) {
		throw new \RuntimeException( is_string( $message ) ? $message : 'wp_die' );
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone(): DateTimeZone {
		return new DateTimeZone( $GLOBALS['rt_options']['timezone'] ?? 'UTC' );
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp = null ) {
		return gmdate( (string) $format, null === $timestamp ? time() : (int) $timestamp );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals );
	}
}

/*
 * page_args() reads the query string back out of the URL page_url() built,
 * so the suite needs core's parser as well as its builder.
 */
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr( $text );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce" />';

		if ( $display ) {
			echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $field;
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true, $other = null ) {
		$button = '<input type="submit" name="' . esc_attr( $name ) . '" class="button button-' . esc_attr( (string) $type )
			. '" value="' . esc_attr( $text ) . '" />';

		if ( $wrap ) {
			$button = '<p class="submit">' . $button . '</p>';
		}

		echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	/**
	 * The nonce check, which stops the request when it fails.
	 *
	 * Real WordPress dies here, and a stub that returned false instead would
	 * let every test past the check continue into code the real thing never
	 * reaches -- which is how a broken nonce check looks like a passing suite.
	 */
	function check_ajax_referer( $action, $name = false, $die = true ) {
		$nonce = $_REQUEST[ $name ] ?? '';

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( -1, 403 );
		}

		return 1;
	}
}

/*
 * What an action's notice is kept in, for whoever is signed in, and where
 * the action came from. Transients are an array; the user is a global a
 * test can change, to show one person cannot read another's.
 */
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['rt_transients'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['rt_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['rt_transients'][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'wp_get_referer' ) ) {
	function wp_get_referer() {
		return $GLOBALS['rt_referer'] ?? false;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $display = true ) {
		$result = (string) $selected === (string) $current ? ' selected=\'selected\'' : '';

		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

if ( ! function_exists( 'disabled' ) ) {
	function disabled( $disabled, $current = true, $display = true ) {
		$result = (string) $disabled === (string) $current ? ' disabled=\'disabled\'' : '';

		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

/**
 * Thrown in place of a JSON answer, so a test can read what was sent.
 *
 * wp_send_json_*() end the request with `wp_die()` in WordPress, which in a
 * test would end the process; throwing hands the answer back instead.
 */
final class RT_Json extends \RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param bool  $success Whether it was a success.
	 * @param mixed $data    What was sent.
	 * @param int   $status  The HTTP status.
	 */
	public function __construct( public bool $success, public mixed $data, public int $status ) {
		parent::__construct( $success ? 'json success' : 'json error' );
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null, $status_code = null ) {
		throw new RT_Json( true, $data, (int) ( $status_code ?? 200 ) );
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, $status_code = null ) {
		throw new RT_Json( false, $data, (int) ( $status_code ?? 200 ) );
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {
		$GLOBALS['rt_localized'][ $handle ] = [ $name, $data ];

		return true;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $route_namespace, $route, $args = [], $override = false ) {
		$GLOBALS['rt_routes'][ $route_namespace . $route ] = $args;

		return true;
	}
}

/*
 * Core's admin notice markup (wp-admin/includes/misc.php, 6.4+), as core
 * builds it: the message is not escaped, the id and classes are not either,
 * and wp_admin_notice() passes the result through wp_kses_post(), which a
 * test cannot run and so is left out.
 */
if ( ! function_exists( 'wp_get_admin_notice' ) ) {
	function wp_get_admin_notice( $message, $args = array() ) {
		$args = array_merge(
			array(
				'type'               => '',
				'dismissible'        => false,
				'id'                 => '',
				'additional_classes' => array(),
				'attributes'         => array(),
				'paragraph_wrap'     => true,
			),
			(array) $args
		);

		$id         = '';
		$classes    = 'notice';
		$attributes = '';

		if ( is_string( $args['id'] ) && '' !== trim( $args['id'] ) ) {
			$id = 'id="' . trim( $args['id'] ) . '" ';
		}

		if ( is_string( $args['type'] ) && '' !== trim( $args['type'] ) ) {
			$classes .= ' notice-' . trim( $args['type'] );
		}

		if ( true === $args['dismissible'] ) {
			$classes .= ' is-dismissible';
		}

		if ( is_array( $args['additional_classes'] ) && ! empty( $args['additional_classes'] ) ) {
			$classes .= ' ' . implode( ' ', $args['additional_classes'] );
		}

		if ( is_array( $args['attributes'] ) ) {
			foreach ( $args['attributes'] as $attr => $val ) {
				if ( is_bool( $val ) ) {
					$attributes .= $val ? ' ' . $attr : '';
				} elseif ( is_int( $attr ) ) {
					$attributes .= ' ' . esc_attr( trim( $val ) );
				} elseif ( $val ) {
					$attributes .= ' ' . $attr . '="' . esc_attr( trim( $val ) ) . '"';
				}
			}
		}

		if ( false !== $args['paragraph_wrap'] ) {
			$message = "<p>$message</p>";
		}

		return sprintf( '<div %1$sclass="%2$s"%3$s>%4$s</div>', $id, $classes, $attributes, $message );
	}
}

if ( ! function_exists( 'wp_admin_notice' ) ) {
	function wp_admin_notice( $message, $args = array() ) {
		echo wp_get_admin_notice( $message, $args );
	}
}

/*
 * What wp_admin_notice() passes the markup through. The notices here carry
 * only escaped text, which it leaves as it is.
 */
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) {
		return (string) $data;
	}
}

/*
 * Core's remove_submenu_page() (wp-admin/includes/plugin.php).
 */
if ( ! function_exists( 'remove_submenu_page' ) ) {
	function remove_submenu_page( $menu_slug, $submenu_slug ) {
		global $submenu;

		if ( ! isset( $submenu[ $menu_slug ] ) ) {
			return false;
		}

		foreach ( $submenu[ $menu_slug ] as $i => $item ) {
			if ( $submenu_slug === $item[2] ) {
				unset( $submenu[ $menu_slug ][ $i ] );

				return $item;
			}
		}

		return false;
	}
}
