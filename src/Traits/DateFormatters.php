<?php
/**
 * Date Column Formatters
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Traits;

use SugarCommerce\Text\Duration;
use SugarCommerce\Tables\ListDate;

/**
 * When something happened, and how long it took.
 *
 * In the site's own date format rather than a hardcoded one: a column that
 * prints American dates on a site configured for British ones is wrong twelve
 * days a month and plausible the rest of the time.
 */
trait DateFormatters {

	/**
	 * Format a date/datetime value as a `<time>` in the list's date mode
	 *
	 * "2 weeks ago" with the exact moment on hover, or -- for a person who
	 * chose Exact in the table's Screen Options (see ListDate and the
	 * table's `date_preference`) -- "13 Oct 2026, 3:20 pm" with how long ago
	 * on hover. The renderer hands the mode over as `_date_mode`; called
	 * without one, a date is relative.
	 *
	 * BerlinDB's zero date is no date: `none` (escaped text, "Never") is
	 * said for it, or the usual empty placeholder.
	 *
	 * @param mixed  $value       Date string, timestamp, or DateTime object.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'none'       (string) What to say for no date.
	 *                            - 'format'     (string) The exact reading's format.
	 *                            - '_date_mode' (string) `relative` or `exact`.
	 *
	 * @return string Formatted date HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_date( $value, $item, string $column_name, array $config = [] ): string {
		return ListDate::cell(
			$value,
			(string) ( $config['_date_mode'] ?? 'relative' ),
			[
				'none'   => (string) ( $config['none'] ?? '' ),
				'empty'  => self::render_empty(),
				'format' => (string) ( $config['format'] ?? '' ),
			]
		);
	}

	/**
	 * Format a duration value in seconds as a human-readable string
	 *
	 * Renders a count of seconds as "2h 15m".
	 *
	 * @param mixed  $value       Duration in seconds.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Formatted duration HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_duration( $value, $item, string $column_name, array $config = [] ): string {
		if ( ! is_numeric( $value ) ) {
			return self::render_empty();
		}

		return esc_html( Duration::compact( (int) $value ) );
	}
}
