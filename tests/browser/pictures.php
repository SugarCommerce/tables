<?php
/**
 * A person's cell and an item's, as a list draws them in one row, for the
 * browser tests.
 *
 * The person's avatar is core's get_avatar() markup at the size Person asks
 * for; the items are the placeholder tile and an image, at the size a list
 * of orders asks for (24). Printed as JSON: `{ "person": "...", "tile":
 * "...", "image": "..." }`.
 *
 * Run by pictures.spec.mjs with `php tests/browser/pictures.php`.
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

require dirname( __DIR__ ) . '/bootstrap.php';

use SugarCommerce\Tables\Cells\Item;
use SugarCommerce\Tables\Cells\Person;

rt_reset_globals();

// A one-pixel PNG standing in for a product's picture.
$GLOBALS['rt_attachments'][7] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

echo wp_json_encode(
	[
		'person' => Person::cell(
			[
				'name'   => 'Charlotte Montgomery',
				'email'  => 'charlotte@example.test',
				'url'    => '#customer',
				// What core's get_avatar() prints for the arguments given.
				'avatar' => static fn( string $email, string $name, int $size, array $args ): string => sprintf(
					'<img alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==" class="avatar avatar-%1$d photo %2$s" height="%1$d" width="%1$d" style="%3$s" />',
					$size,
					$args['class'],
					$args['style']
				),
			]
		),
		'tile'   => Item::cell(
			[
				'title'   => 'Drum & Bass Chords',
				'picture' => Item::picture( 0, 'box', 24 ),
				'lines'   => [ 'Monthly' ],
			]
		),
		'image'  => Item::cell(
			[
				'title'   => 'Drum & Bass Chords',
				'picture' => Item::picture( 7, 'box', 24 ),
			]
		),
	]
);
