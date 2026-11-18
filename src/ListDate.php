<?php
/**
 * List Dates
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables;

use SugarCommerce\Dates\Site;
use SugarCommerce\Dates\Timestamp;

defined( 'ABSPATH' ) || exit;

/**
 * A date in a list, said the way the person looking has asked for dates.
 *
 * `relative` is "2 weeks ago" and `exact` is "13 Oct 2026, 3:20 pm", with
 * the other reading as the tooltip, so neither is ever more than a hover
 * away whichever was chosen. One choice for each person, made in Screen
 * Options and kept as user meta under a key the table names: every list
 * that names the same key shares it, because somebody who wants the date on
 * one list wants it on the next, and a setting per screen would be the same
 * question asked eight times.
 *
 * Relative unless they have said otherwise. A list is read to see what has
 * just happened, and "3 hours ago" says that where a date has to be worked
 * out against today's.
 *
 * The same words at any age: "2 years ago" stays that and does not turn
 * into a date, because a column that changes its form half way down is two
 * columns to read. A date still to come reads "in 3 weeks".
 *
 * The control is a second field in core's own Screen Options form, saved by
 * the same Apply button under the nonce core has already checked by the
 * time it asks `set-screen-option` -- so there is no request of its own to
 * protect.
 *
 * @since 1.0.0
 */
final class ListDate {

	/**
	 * The two ways a date can be said.
	 */
	public const MODES = [ 'relative', 'exact' ];

	/**
	 * Whether a stored value holds a date.
	 *
	 * Not empty(): BerlinDB stores `0000-00-00 00:00:00` for a datetime with
	 * no value, which empty() reads as a real date, and a list that trusts it
	 * says "2,025 years ago".
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function has_date( mixed $value ): bool {
		return is_string( $value ) && ! Timestamp::is_empty( $value );
	}

	/**
	 * The user meta key a table keeps its preference under, or '' for none.
	 *
	 * `date_preference` in the table's configuration: `true` for a key of
	 * the table's own, `{table_id}_dates`, or a key to share with other
	 * lists. Left out or false, the table has no preference and its dates
	 * are relative.
	 *
	 * @param string $table_id The table.
	 * @param array  $config   Its configuration.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function meta_key( string $table_id, array $config ): string {
		$preference = $config['date_preference'] ?? false;

		if ( true === $preference ) {
			return sanitize_key( $table_id . '_dates' );
		}

		return is_string( $preference ) ? sanitize_key( $preference ) : '';
	}

	/**
	 * How somebody has asked for dates to be said.
	 *
	 * @param string   $meta_key Where the preference is kept; '' for none.
	 * @param int|null $user_id  Whose. The person signed in when not given.
	 *
	 * @return string `relative` or `exact`.
	 * @since 1.0.0
	 */
	public static function mode( string $meta_key, ?int $user_id = null ): string {
		$user_id ??= (int) get_current_user_id();

		$mode = '' !== $meta_key && $user_id > 0 ? get_user_meta( $user_id, $meta_key, true ) : '';
		$mode = 'exact' === $mode ? 'exact' : 'relative';

		if ( '' !== $meta_key ) {
			/**
			 * Filter how the dates in the lists sharing a preference are said.
			 *
			 * For a site that wants one way for everybody: return `relative`
			 * or `exact`. Anything else is read as `relative`.
			 *
			 * @param string $mode    What this person chose.
			 * @param int    $user_id Whose lists these are; 0 when nobody is signed in.
			 *
			 * @since 1.0.0
			 */
			$mode = apply_filters( "sugarcommerce_table_date_mode_{$meta_key}", $mode, $user_id );
		}

		return 'exact' === $mode ? 'exact' : 'relative';
	}

	/**
	 * Keep how somebody wants dates said.
	 *
	 * @param string   $meta_key Where the preference is kept.
	 * @param string   $mode     `relative` or `exact`; anything else is `relative`.
	 * @param int|null $user_id  Whose. The person signed in when not given.
	 *
	 * @return bool False when there is nobody, or nowhere, to keep it.
	 * @since 1.0.0
	 */
	public static function set_mode( string $meta_key, string $mode, ?int $user_id = null ): bool {
		$user_id ??= (int) get_current_user_id();

		if ( '' === $meta_key || $user_id < 1 ) {
			return false;
		}

		update_user_meta( $user_id, $meta_key, 'exact' === $mode ? 'exact' : 'relative' );

		return true;
	}

	/**
	 * A stored date, as a cell: a `<time>` carrying the moment itself, for
	 * anything that reads the page and not the words.
	 *
	 * @param mixed  $value The stored UTC value. A DateTime or a Unix
	 *                      timestamp (an integer) is read as the moment it is.
	 * @param string $mode  `relative` or `exact`.
	 * @param array  $args  {
	 *     Optional.
	 *
	 *     @type string $none   What to say when there is no date: "Never".
	 *                          A dash when not given (or `$args['empty']`).
	 *     @type string $empty  Escaped markup for no date, when `none` is not
	 *                          given. Default a dash nobody hears.
	 *     @type string $class  A class for the `<time>`.
	 *     @type string $format The exact reading's date format. Default a
	 *                          short date and the time: "13 Oct 2026, 3:20 pm".
	 * }
	 *
	 * @return string Escaped markup.
	 * @since 1.0.0
	 */
	public static function cell( mixed $value, string $mode = 'relative', array $args = [] ): string {
		if ( $value instanceof \DateTimeInterface || is_int( $value ) ) {
			$value = (string) Timestamp::to_sql( $value );
		}

		if ( ! self::has_date( $value ) ) {
			$none = (string) ( $args['none'] ?? '' );

			if ( '' !== $none ) {
				return esc_html( $none );
			}

			return (string) ( $args['empty'] ?? '<span aria-hidden="true">&mdash;</span>' );
		}

		$relative = self::relative( $value );
		$exact    = self::exact( $value, (string) ( $args['format'] ?? '' ) );

		if ( '' === $relative && '' === $exact ) {
			return (string) ( $args['empty'] ?? '<span aria-hidden="true">&mdash;</span>' );
		}

		[ $shown, $other ] = 'exact' === $mode ? [ $exact, $relative ] : [ $relative, $exact ];

		$class = (string) ( $args['class'] ?? '' );

		return sprintf(
			'<time%1$s datetime="%2$s" title="%3$s">%4$s</time>',
			'' !== $class ? ' class="' . esc_attr( $class ) . '"' : '',
			esc_attr( Timestamp::iso( $value ) ),
			esc_attr( $other ),
			esc_html( $shown )
		);
	}

	/**
	 * How long ago, or how long until, in WordPress's own words and with no
	 * cutoff past which it turns into a date.
	 *
	 * @param string $value A stored UTC value.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function relative( string $value ): string {
		return self::has_date( $value ) ? Site::relative( $value, null, Site::LIKE_WORDPRESS ) : '';
	}

	/**
	 * The short date and the time, in the site's timezone.
	 *
	 * @param string $value  A stored UTC value.
	 * @param string $format A date format; empty for "13 Oct 2026, 3:20 pm".
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function exact( string $value, string $format = '' ): string {
		if ( '' === $format ) {
			// Not the site's date_format and time_format, deliberately: those
			// are a long date ("October 13, 2026 3:20 pm") meant for a post,
			// too wide for a list column, and swapping would change every
			// list that already shows this. A table that wants the site's
			// reading passes `format`. Translated, so a locale has its own.
			/* translators: a short date and a time, as PHP's date() writes them: "13 Oct 2026, 3:20 pm". */
			$format = _x( 'j M Y, g:i a', 'short date and time', 'sugarcommerce' );
		}

		return self::has_date( $value ) ? Site::format( $value, $format ) : '';
	}

	/**
	 * The two choices, for a table's Screen Options.
	 *
	 * Each with an example of itself, in the site's own language and time:
	 * "Relative" and "Exact" are words that want one. Core's own markup for
	 * a group of options, and the radios named for the meta key, which is
	 * what save() reads.
	 *
	 * @param string $meta_key Where the preference is kept.
	 *
	 * @return string Escaped markup.
	 * @since 1.0.0
	 */
	public static function control( string $meta_key ): string {
		if ( '' === $meta_key ) {
			return '';
		}

		$mode    = self::mode( $meta_key );
		$example = gmdate( 'Y-m-d H:i:s', time() - 2 * 7 * 86400 );
		$choices = [
			'relative' => [ __( 'Relative', 'sugarcommerce' ), self::relative( $example ) ],
			'exact'    => [ __( 'Exact', 'sugarcommerce' ), self::exact( $example ) ],
		];

		$radios = '';

		foreach ( $choices as $value => [ $label, $shows ] ) {
			$radios .= sprintf(
				'<label><input type="radio" name="%1$s" value="%2$s"%3$s /> %4$s <span class="description">(%5$s)</span></label> ',
				esc_attr( $meta_key ),
				esc_attr( $value ),
				$mode === $value ? " checked='checked'" : '',
				esc_html( $label ),
				esc_html( $shows )
			);
		}

		return sprintf(
			'<fieldset class="metabox-prefs sugarcommerce-table-dates"><legend>%1$s</legend>%2$s</fieldset>',
			esc_html__( 'Dates', 'sugarcommerce' ),
			$radios
		);
	}

	/**
	 * Keep the choice posted with a table's Screen Options.
	 *
	 * Called from the library's `set-screen-option` filter once it knows the
	 * option is one of its own tables' rows per page -- the option that form
	 * posts -- so another screen's form can never set it.
	 *
	 * @param string $meta_key Where the preference is kept.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function save( string $meta_key ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Core's set_screen_options() checked the form's nonce before asking.
		if ( '' === $meta_key || ! isset( $_POST[ $meta_key ] ) || ! is_scalar( $_POST[ $meta_key ] ) ) {
			return;
		}

		self::set_mode( $meta_key, sanitize_key( wp_unslash( (string) $_POST[ $meta_key ] ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
