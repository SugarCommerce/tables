<?php
/**
 * Person Cell
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Cells;

use SugarCommerce\Tables\Support\FlyoutLink;

defined( 'ABSPATH' ) || exit;

/**
 * A person, as a cell of a list: their picture, name, and address beneath.
 *
 * The shape every other WordPress list uses for a person, so the same person
 * looks like the same person on every list that names them. The picture is
 * floated the way core floats the one on the Users screen, and says nothing
 * aloud: the name beside it says who it is.
 *
 * The name is the link -- it opens their panel, or goes where `url` says.
 * The address under it is plain text: a mailto made the one thing in the
 * cell that could be clicked the thing least often wanted. With no name the
 * address is shown once and is the link. With nothing to open there is
 * nothing to click, and the cell is text.
 *
 * One line each, cut short with an ellipsis when the column is narrower
 * than the text. An address that wraps breaks wherever it runs out of room
 * -- in the middle of a word, there being no spaces in one -- and makes every
 * row a line taller. The whole address is the tooltip.
 *
 * Clamped to the line rather than kept from wrapping: a line that cannot
 * wrap is as wide as all of it to a table laid out by its contents, and a
 * long address pushed the list past the edge of the screen. One that may
 * wrap anywhere asks for its whole width when there is room and gives it
 * up when there is not.
 *
 * The layout is inline styles, not the stylesheet, so the cell is the same
 * cell wherever it is drawn: a list, or a table of records inside a panel on
 * a screen that never loaded this library's CSS.
 *
 * @since 1.0.0
 */
final class Person {

	/**
	 * One line, cut short: what the name and the address both are.
	 */
	private const string LINE = 'display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:1;overflow:hidden;overflow-wrap:anywhere';

	/**
	 * The cell.
	 *
	 * @param array $parts {
	 *     What to draw. Text is not escaped; markup is.
	 *
	 *     @type string          $name        Their name.
	 *     @type string          $email       Their address.
	 *     @type int             $id          Their record's id, for `flyout`.
	 *     @type string          $flyout      A panel's full id to open on `id`.
	 *     @type string          $url         Where the name goes, when no panel.
	 *     @type string          $link        Escaped markup to show as the name,
	 *                                        when the consumer made the link.
	 *     @type callable|string|false $avatar `( $email, $name, $size, $args )`
	 *                                        returning escaped markup; markup;
	 *                                        or false for no picture. Default
	 *                                        core's get_avatar() for the address.
	 *     @type int             $avatar_size Pixels. Default 32.
	 *     @type bool            $email_line  False to leave the address off
	 *                                        from under the name -- for a list
	 *                                        with it in a column of its own.
	 *                                        With no name it is still shown.
	 *     @type string          $after       Escaped markup ending the name's
	 *                                        line: tags, a word about them, a
	 *                                        link to their other rows.
	 * }
	 *
	 * @return string Escaped markup, or a dash when there is nobody.
	 * @since 1.0.0
	 */
	public static function cell( array $parts ): string {
		$name  = trim( (string) ( $parts['name'] ?? '' ) );
		$email = trim( (string) ( $parts['email'] ?? '' ) );

		if ( '' === $email && '' === $name ) {
			return '<span aria-hidden="true">&mdash;</span>';
		}

		// What is clicked: the name, or the address standing in for one.
		$title = '' !== $name ? $name : $email;

		$cell = self::avatar( $parts, $email, $name ) . sprintf(
			'<strong class="sugarcommerce-person-name" style="display:flex;gap:8px"%3$s><span style="%1$s">%2$s</span>%4$s</strong>',
			esc_attr( self::LINE ),
			self::link( $parts, $title ),
			'' !== $name ? '' : ' title="' . esc_attr( $email ) . '"',
			(string) ( $parts['after'] ?? '' )
		);

		// The address under the name, and not a second time under itself.
		if ( '' !== $name && '' !== $email && false !== ( $parts['email_line'] ?? true ) ) {
			$cell .= sprintf(
				'<span class="sugarcommerce-person-email" title="%1$s" style="%3$s">%2$s</span>',
				esc_attr( $email ),
				esc_html( $email ),
				// A list's second line: smaller, and a step lighter than
				// core's grey -- #6b6f75 is the lightest that still reads
				// at 4.5:1 on a striped or highlighted row.
				esc_attr( self::LINE . ';color:#6b6f75;font-size:12px' )
			);
		}

		return $cell;
	}

	/**
	 * The name: a panel's link, a URL, the consumer's own link, or text.
	 *
	 * @param array  $parts The cell's parts.
	 * @param string $title What the link says.
	 *
	 * @return string Escaped markup.
	 */
	private static function link( array $parts, string $title ): string {
		if ( '' !== (string) ( $parts['link'] ?? '' ) ) {
			return (string) $parts['link'];
		}

		$id     = (int) ( $parts['id'] ?? 0 );
		$flyout = (string) ( $parts['flyout'] ?? '' );

		if ( $id > 0 && '' !== $flyout ) {
			$link = FlyoutLink::get( $flyout, $id, $title );

			if ( '' !== $link ) {
				return $link;
			}
		}

		$url = (string) ( $parts['url'] ?? '' );

		if ( '' !== $url ) {
			return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $title ) );
		}

		return esc_html( $title );
	}

	/**
	 * The picture, floated beside the name.
	 *
	 * Beside the name, which says who it is; so the picture says nothing.
	 *
	 * @param array  $parts The cell's parts.
	 * @param string $email Their address.
	 * @param string $name  Their name.
	 *
	 * @return string Escaped markup.
	 */
	private static function avatar( array $parts, string $email, string $name ): string {
		$avatar = $parts['avatar'] ?? null;
		$size   = max( 16, (int) ( $parts['avatar_size'] ?? 32 ) );
		$args   = [
			'label' => '',
			'class' => 'sugarcommerce-person-avatar',
			'style' => 'float:left;margin:1px 10px 0 0',
		];

		if ( false === $avatar ) {
			return '';
		}

		if ( is_callable( $avatar ) ) {
			return (string) call_user_func( $avatar, $email, $name, $size, $args );
		}

		if ( is_string( $avatar ) ) {
			return $avatar;
		}

		if ( '' === $email || ! function_exists( 'get_avatar' ) ) {
			return '';
		}

		return (string) get_avatar(
			$email,
			$size,
			'',
			'',
			[
				'class'      => $args['class'],
				'extra_attr' => 'style="' . esc_attr( $args['style'] ) . '"',
			]
		);
	}
}
