<?php
/**
 * A stand-in for wp-register-flyouts' registry.
 *
 * Only what FlyoutLink asks of it. Which panels exist is
 * `$GLOBALS['rt_flyouts']` -- full id => true -- so a test can say "no such
 * panel" without the class disappearing, which PHP cannot do once it is
 * loaded.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Flyouts;

/**
 * Resolves a full panel id to a manager and the panel's own id.
 */
final class Registry {

	/**
	 * As the real one: the prefix before the first underscore names the
	 * manager, and the rest is the panel.
	 *
	 * @param string $id     Full panel id.
	 * @param bool   $create Unused.
	 *
	 * @return array{manager: FakeManager, flyout_id: string}|null
	 */
	public static function resolve( string $id, bool $create = false ): ?array {
		if ( empty( $GLOBALS['rt_flyouts'][ $id ] ) ) {
			return null;
		}

		$at = (int) strpos( $id, '_' );

		return [
			'manager'   => new FakeManager( substr( $id, 0, $at ) ),
			'flyout_id' => substr( $id, $at + 1 ),
		];
	}
}

/**
 * Draws what it was asked for, so a test can read it back.
 */
final class FakeManager {

	/**
	 * Constructor.
	 *
	 * @param string $prefix The manager's prefix.
	 */
	public function __construct( private string $prefix ) {}

	/**
	 * A trigger link.
	 *
	 * @param string $flyout_id Panel id.
	 * @param string $text      Link text.
	 * @param array  $data      Data attributes.
	 * @param array  $args      Link arguments.
	 *
	 * @return string
	 */
	public function link( string $flyout_id, string $text, array $data = [], array $args = [] ): string {
		return sprintf(
			'<a href="#" class="%s" data-flyout-manager="%s" data-flyout="%s" data-id="%s">%s</a>',
			trim( 'wp-flyout-trigger ' . ( $args['class'] ?? '' ) ),
			$this->prefix,
			$flyout_id,
			$data['id'] ?? '',
			$text
		);
	}

	/**
	 * A trigger button.
	 *
	 * @param string $flyout_id Panel id.
	 * @param array  $data      Data attributes.
	 * @param array  $args      Button arguments.
	 *
	 * @return string
	 */
	public function get_button( string $flyout_id, array $data = [], array $args = [] ): string {
		return sprintf( '<button class="%s" data-flyout="%s">%s</button>', $args['class'] ?? '', $flyout_id, $args['text'] ?? '' );
	}
}
