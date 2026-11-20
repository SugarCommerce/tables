<?php
/**
 * Item Cell
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Cells;

defined( 'ABSPATH' ) || exit;

/**
 * One thing a row names, as a cell: its picture, what it is called, how many
 * more there are, and any quieter lines beneath.
 *
 * What an order's Items cell is, a customer's Products, the plan of a
 * membership: a small picture and a name beside it. Built from parts and not
 * handed over as a finished string, so a list can put a second line under the
 * name -- what has been downloaded, say -- without taking the cell apart to
 * do it.
 *
 * The name is one line, cut short with an ellipsis; the whole of it is the
 * tooltip. "+ 2 others" follows it on the same line where there is room and
 * drops beneath it where there is not. The stylesheet is the library's
 * (`.sugarcommerce-item*` in admin-tables.css).
 *
 * @since 1.0.0
 */
final class Item {

	/**
	 * The placeholder tiles, by kind: the glyph drawn on the grey tile.
	 *
	 * In a forty-unit square. `box` is a parcel, for a thing that is the
	 * thing itself -- a product -- and `stack` is three cards in a stack, for
	 * a thing that groups others. Add one with `placeholder` on picture().
	 *
	 * @var array<string, string>
	 */
	private const array GLYPHS = [
		// Sixteen units square at 1.25: twenty across, centred in forty.
		'box'   => '<path transform="translate(10 10) scale(1.25)" fill="#8a94a6" fill-rule="evenodd" clip-rule="evenodd"'
			. ' d="M7.973 0a2 2 0 0 0-.894.211L1.607 2.947A2 2 0 0 0 .5 4.737v6.568a2 2 0 0 0 1.052 1.761l4.782 2.575A3 3 0 0 0 7.757 16h.488a3 3 0 0 0 1.422-.359l4.781-2.575a2 2 0 0 0 1.052-1.76v-6.57a2 2 0 0 0-1.105-1.789L8.922.211A2 2 0 0 0 8.03 0h-.056ZM14 11.306V5.62L8.75 8.448v5.964c.07-.025.14-.056.206-.091l4.781-2.575a.5.5 0 0 0 .263-.44ZM8.252 1.553l5.257 2.629-2.06 1.109-5.38-2.898 1.68-.84a.5.5 0 0 1 .224-.053h.056a.5.5 0 0 1 .223.053ZM4.756 3.05 2.491 4.182 8 7.148l2.184-1.176L4.756 3.05ZM7.25 8.448 2 5.622v5.683a.5.5 0 0 0 .263.44l4.782 2.576c.066.035.134.066.204.09V8.449Z"/>',

		// The top card whole and the two beneath it showing an edge each,
		// drawn to the weight of the box, so the two read as one family.
		'stack' => '<g fill="none" stroke="#8a94a6" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
			. '<rect x="11" y="16.5" width="18" height="12.5" rx="2.5"/>'
			. '<path d="M13.5 13.25h13M16 10h8"/>'
			. '</g>',
	];

	/**
	 * The cell.
	 *
	 * @param array $parts {
	 *     @type string               $title   What it is called, not escaped.
	 *     @type string               $link    Escaped markup to show in place
	 *                                         of the title, when the name opens
	 *                                         something.
	 *     @type string               $picture Escaped markup: an image, as
	 *                                         picture() makes one. Left out, no
	 *                                         picture.
	 *     @type string|int           $more    The rest, not escaped: "+ 2 others".
	 *                                         An integer is worded by more().
	 *     @type string               $tooltip The name's tooltip, when it is not
	 *                                         the title.
	 *     @type array<int|string, string> $lines Escaped markup, a line each,
	 *                                         under the name. Blank ones are
	 *                                         left out.
	 * }
	 *
	 * @return string Escaped markup.
	 * @since 1.0.0
	 */
	public static function cell( array $parts ): string {
		$title   = trim( (string) ( $parts['title'] ?? '' ) );
		$link    = (string) ( $parts['link'] ?? '' );
		$more    = is_int( $parts['more'] ?? null ) ? self::more( $parts['more'] ) : trim( (string) ( $parts['more'] ?? '' ) );
		$tooltip = trim( (string) ( $parts['tooltip'] ?? '' ) );
		$lines   = array_filter(
			array_map( 'strval', (array) ( $parts['lines'] ?? [] ) ),
			static fn( string $line ): bool => '' !== trim( $line )
		);

		$text = sprintf(
			'<span class="sugarcommerce-item-line"><span class="sugarcommerce-item-title" title="%1$s">%2$s</span>%3$s</span>',
			esc_attr( '' !== $tooltip ? $tooltip : $title ),
			'' !== $link ? $link : esc_html( $title ),
			'' !== $more ? ' <span class="sugarcommerce-item-more">' . esc_html( $more ) . '</span>' : ''
		);

		foreach ( $lines as $line ) {
			$text .= '<span class="sugarcommerce-item-meta">' . $line . '</span>';
		}

		return sprintf(
			'<span class="sugarcommerce-item">%1$s<span class="sugarcommerce-item-text">%2$s</span></span>',
			(string) ( $parts['picture'] ?? '' ),
			$text
		);
	}

	/**
	 * "+ 2 others": how many more there are than the one named.
	 *
	 * @param int $rest How many more.
	 *
	 * @return string Not escaped. Empty for none.
	 * @since 1.0.0
	 */
	public static function more( int $rest ): string {
		if ( $rest < 1 ) {
			return '';
		}

		return sprintf(
			/* translators: %s: how many more things a row names than the one shown. */
			_n( '+ %s other', '+ %s others', $rest, 'sugarcommerce' ),
			number_format_i18n( $rest )
		);
	}

	/**
	 * A picture, or a placeholder tile standing in for one.
	 *
	 * The fallback is drawn rather than fetched: a remote placeholder is a
	 * request per row on a screen that is mostly rows, and one that fails
	 * leaves a broken image where the thing should be.
	 *
	 * @param int    $image_id Attachment id, or 0.
	 * @param string $kind     Which tile stands in when there is no image:
	 *                         `box` or `stack`.
	 * @param int    $size     Pixels each way. Forty beside the title of a row
	 *                         that is the thing itself; smaller beside a name
	 *                         in a row that is about something else, where it
	 *                         is decoration and says nothing -- and shown, in a
	 *                         list, at a person's avatar's 32px.
	 * @param array  $args     {
	 *     Optional.
	 *
	 *     @type string $class       Classes in place of the library's
	 *                               `sugarcommerce-thumb` (and `-small`).
	 *     @type string $placeholder The tile's glyph, as SVG drawn in a
	 *                               forty-unit square, for a kind of your own.
	 * }
	 *
	 * @return string Escaped markup.
	 * @since 1.0.0
	 */
	public static function picture( int $image_id, string $kind = 'box', int $size = 40, array $args = [] ): string {
		$size  = max( 16, $size );
		$small = $size < 40;
		$class = (string) ( $args['class'] ?? ( $small ? 'sugarcommerce-thumb sugarcommerce-thumb-small' : 'sugarcommerce-thumb' ) );

		if ( $image_id > 0 && function_exists( 'wp_get_attachment_image' ) ) {
			// The small one is a square whatever shape the picture is --
			// the stylesheet crops it -- and says nothing aloud: the name
			// is beside it.
			$image = (string) wp_get_attachment_image(
				$image_id,
				[ $size, $size ],
				false,
				$small
					? [
						'class'  => $class,
						'alt'    => '',
						'width'  => $size,
						'height' => $size,
					]
					: [ 'class' => $class ]
			);

			if ( '' !== $image ) {
				return $image;
			}
		}

		return self::placeholder( $kind, $size, $class, (string) ( $args['placeholder'] ?? '' ) );
	}

	/**
	 * The grey tile.
	 *
	 * An <img>, not a <span> wrapping an <svg>. The slot this goes in is the
	 * one core puts an avatar in, and core floats it with `.column-username
	 * img` -- so anything that is not an image is not floated, and a 40px
	 * block followed by the title put the name underneath the picture.
	 *
	 * A data URI rather than a file: it is a handful of shapes, it never
	 * changes, and a request per row for a placeholder is a request per row.
	 *
	 * A box rather than the usual picture-frame glyph. The row is missing an
	 * image, but what it stands for is the thing -- a torn-photo icon says
	 * something failed to load, which is a different statement and a wrong
	 * one.
	 *
	 * Drawn forty square and shown at whatever size was asked for: one
	 * drawing, and its corner stays a fifth of its side.
	 *
	 * @param string $kind  `box` or `stack`.
	 * @param int    $size  Pixels each way.
	 * @param string $classes Classes for the image.
	 * @param string $glyph SVG to draw instead of the kind's.
	 *
	 * @return string Escaped markup.
	 * @since 1.0.0
	 */
	public static function placeholder( string $kind = 'box', int $size = 40, string $classes = 'sugarcommerce-thumb', string $glyph = '' ): string {
		$glyph = '' !== $glyph ? $glyph : ( self::GLYPHS[ $kind ] ?? self::GLYPHS['box'] );

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40">'
			. '<rect width="40" height="40" rx="8" fill="#eef2f7"/>'
			. $glyph
			. '</svg>';

		return sprintf(
			'<img src="data:image/svg+xml;base64,%1$s" alt="" width="%2$d" height="%2$d" class="%3$s" />',
			base64_encode( $svg ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a data URI for a drawn tile.
			max( 16, $size ),
			esc_attr( $classes )
		);
	}
}
