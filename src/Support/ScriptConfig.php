<?php
/**
 * Script Configuration
 *
 * @package     SugarCommerce\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Support;

use SugarCommerce\Tables\Utils\Runtime;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * What PHP hands a script, in the build's own entry of one registry.
 *
 * Two plugins each bundling a copy of this library load each script twice,
 * under two handles. Localizing a script would print both copies'
 * settings under one fixed name -- `sugarCommerceReorder` and the rest --
 * and whichever printed last would own the other's route. So each script's
 * settings go into `window.SugarCommerceTables`, keyed by the script's
 * handle, and the script finds its own entry from the id WordPress gives its
 * element, "{handle}-js" (assets/js/lib/config.js).
 *
 * Every entry also names its build, which the page's `.wrap` carries too
 * (`data-sugarcommerce-tables`): a script listens inside its own build's
 * screen and nowhere else.
 */
final class ScriptConfig {

	/**
	 * The registry: one name, its keys each build's handles.
	 *
	 * @var string
	 */
	public const string REGISTRY = 'SugarCommerceTables';

	/**
	 * The attribute the screen's `.wrap` names its build in.
	 *
	 * @var string
	 */
	public const string ATTRIBUTE = 'data-sugarcommerce-tables';

	/**
	 * Hand a script its settings, printed before it.
	 *
	 * @param string               $handle The script's handle.
	 * @param array<string, mixed> $config What it is told.
	 *
	 * @return void
	 */
	public static function attach( string $handle, array $config ): void {
		wp_add_inline_script( $handle, self::inline( $handle, $config ), 'before' );
	}

	/**
	 * The inline script that files the settings under the handle.
	 *
	 * @param string               $handle The script's handle.
	 * @param array<string, mixed> $config What it is told.
	 *
	 * @return string
	 */
	public static function inline( string $handle, array $config ): string {
		return sprintf(
			'window.%1$s=window.%1$s||{};window.%1$s[%2$s]=%3$s;',
			self::REGISTRY,
			wp_json_encode( $handle ),
			wp_json_encode( [ 'build' => Runtime::prefix() ] + $config )
		);
	}

	/**
	 * The attribute that marks a screen as this build's, ready to print.
	 *
	 * @return string
	 */
	public static function marker(): string {
		return sprintf( '%s="%s"', self::ATTRIBUTE, esc_attr( Runtime::prefix() ) );
	}
}
