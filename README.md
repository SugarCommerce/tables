# Tables

A WordPress list table for your own data — the screen, the menu, the search,
the sorting and the paging — from a description of the columns.

## What it does

`WP_List_Table` is a class you extend, and extending it means implementing
half a dozen methods before anything appears: the columns, the rows, the
sortable list, the bulk actions, the pagination, the search, plus the menu
page around it. It is a day's work for a table of orders, and the next table
is the same day again.

This takes the description instead. You supply a callback that returns rows
and one that counts them; everything from the menu to the "no items found"
message follows.

## Features

* Get a working list-table screen from a callback and a list of columns
* Search, sort and paginate without implementing any of the three
* Add status views along the top, with counts beside each
* Add filters, bulk actions and row actions to the same table
* Read rows, counts and related rows from a BerlinDB table by naming its query class
* Offer each row its next status, and say what moved by name
* Format a column as a price, a date, a status badge or a country, by naming the type
* Lay columns out by what is in them: a figure's column is as wide as the figure, the names get the rest
* Show a row's status and amount under its title on a phone, where core folds the other columns away
* Draw a person, or one thing with its picture, the same way on every list
* Let each person choose relative or exact dates, and skip columns nobody is looking at
* Make a cell a switch that saves itself -- a star, or a line of words
* Let rows be dragged into an order somebody chooses
* Light the rows that are new since each person last looked, and count them on the menu
* Hang the screen off any menu — top level, a post type, Tools, Settings
* Get core's own list-screen heading, with the add button beside it

## Installation

```bash
composer require sugarcommerce/tables
```

## Quick start

```php
use SugarCommerce\Tables\Tables;

Tables::register( 'my_orders', [
	'page_title' => __( 'Orders', 'my-plugin' ),
	'menu_title' => __( 'Orders', 'my-plugin' ),
	'menu_slug'  => 'my-orders',
	'capability' => 'manage_options',
	'icon'       => 'dashicons-cart',

	'labels'     => [
		'singular' => __( 'order', 'my-plugin' ),
		'plural'   => __( 'orders', 'my-plugin' ),
	],

	'callbacks'  => [
		'get_items'  => '\MyPlugin\get_orders',
		'get_counts' => '\MyPlugin\get_order_counts',
	],

	'columns'    => [
		'id'      => [ 'label' => __( 'Order', 'my-plugin' ), 'sortable' => true ],
		'total'   => [ 'label' => __( 'Total', 'my-plugin' ), 'type' => 'price' ],
		'status'  => [ 'label' => __( 'Status', 'my-plugin' ), 'type' => 'status' ],
		'created' => [ 'label' => __( 'Date', 'my-plugin' ), 'type' => 'date' ],
	],
] );
```

`get_items` is handed the query — search, sort, page, filters, already
resolved — and returns rows. That is the only part that knows about your data.

A `price` column is in the row's own currency (`get_currency()`), the
column's `currency`, or else sugarcommerce/money's default -- set once for
your copy with `Money::set_default_currency( 'GBP' )`. A table under a menu
of its own takes `ParentMenu::register( 'my-plugin', __( 'Shop',
'my-plugin' ), [ 'icon' => 'dashicons-cart' ] )` and `'parent_slug' =>
'my-plugin'`; one drawn inside a page you made yourself is
`Tables::renderer( 'my_orders' )`, as that page's callback.

## Widths, the primary column and phones

Nobody sets a width. The table is laid out by what is in it (core's `fixed`
layout is left off, as on core's Plugins screen), and a column whose type is
a figure -- `price`, `count`, `percentage`, `rate`, `duration`, `file_size`,
`date`, `status`, `boolean`, `color`, `image`, a `toggle` or the reorder
handle -- is drawn on one line and is as wide as that line. The columns with
words in them share what is left. A column says otherwise with `compact`:

```php
'marketing' => [ 'label' => __( 'Marketing', 'my-plugin' ), 'compact' => true ],  // a short line of words
'last_order' => [ 'label' => __( 'Last Order', 'my-plugin' ), 'compact' => false ], // a date with a product under it
```

Row actions take no room until the row is pointed at or has the keyboard's
focus (on a phone, which cannot point, they are shown as core shows them).

On a phone core shows the primary column alone, with the rest behind its
"Show more details" toggle, so the primary column is the one that says what
the row is: `primary_column`, or a column's `'primary' => true`, and when
neither is set the first column that is not a bare `id`. A status and an
amount are repeated under its title there -- the first `status` and the
first `price` column -- or the columns `summary` names, or none with
`'summary' => false`:

```php
'primary_column' => 'customer',
'summary'        => [ 'status', 'total' ],
```

Columns before the primary one fold away on a phone too, and an opened row
lists the primary column first.

## A title with states

A structured column's `states` go inside the title's `<strong>`, so they sit
on the same line as the name, the way core shows "— Draft" on the Posts
list. (Core's stylesheet makes that `<strong>` a block, so anything placed
after it, `after` included, starts a new line.) Return escaped markup, or
an empty string for none:

```php
'title' => [
	'label'  => __( 'Product', 'my-plugin' ),
	'title'  => static fn( object $row ): string => $row->title,
	'link'   => 'edit_flyout',
	'states' => static fn( object $row ): string => 'draft' === $row->status
		? ' &mdash; <span class="post-state">' . esc_html__( 'Draft', 'my-plugin' ) . '</span>'
		: '',
],
```

## Opening a row in a panel

With [sugarcommerce/flyouts](https://github.com/SugarCommerce/flyouts)
installed, a title can open the row's panel and a row action can be its Edit
link. `flyouts` names the table's panels by their full id; a row action's
`flyout` is `true` for the edit panel or a panel's own id:

```php
'flyouts'     => [ 'edit' => 'shop_edit_order' ],
'columns'     => [
	'title' => [ 'label' => __( 'Order', 'my-plugin' ), 'title' => 'my_plugin_order_title', 'link' => 'edit_flyout' ],
],
'row_actions' => [
	'edit'    => [ 'label' => __( 'Edit', 'my-plugin' ), 'flyout' => true ],
	'refunds' => [ 'label' => __( 'Refunds', 'my-plugin' ), 'flyout' => 'shop_order_refunds' ],
],
```

The link is made by the flyout library's `Registry` class, never its global
`get_flyout_link()`, so a copy bundled with Strauss reaches its own prefixed
flyouts and not another plugin's. Without the flyout library (or without
the capability the panel asks) the title is drawn unlinked and the action
is left out. The `add_button` and the empty list's button work the same
way, and a `sync` button (a sync id, or a list of them) goes through
sugarcommerce/jobs' `Sync` class.

## A figure under a column

A column's `footer` puts a figure for the whole view -- every page of it, as
searched, filtered and narrowed to a status -- in that column's cell of the
footer row, where core repeats the heading. It is handed the same query
arguments `get_items` is, less the page and the order, and how many rows the
view has; it returns escaped markup, and a `<small>` in it is a quieter
second line:

```php
'total' => [
	'label'  => __( 'Total', 'my-plugin' ),
	'footer' => static fn( array $args, int $count ): string => '<strong>'
		. esc_html( my_plugin_sum_orders( $args ) )
		. '</strong> <small>' . esc_html( sprintf( __( 'for all %d orders', 'my-plugin' ), $count ) ) . '</small>',
],
```

It is asked once a request, and not at all while the view is empty. Where
the cell is not on screen -- the column switched off in Screen Options, or a
phone, where core shows only the primary column -- the figure is shown in
the bottom navigation instead, after the column's name.

## A filter with too many choices to list

A dropdown of every product stops being usable at a few hundred. A filter of
`'type' => 'search'` is sugarcommerce/fields' searchable select instead, asking a
fields search source over REST as somebody types -- the same source a
settings field names, registered once with `Sources::shared()->register()`:

```php
'filters' => [
	'product_id' => [
		'type'           => 'search',
		'label'          => __( 'All Products', 'my-plugin' ),
		'source'         => 'my_products',
		// The fields ajax-field contract: ( '', [ $value ], $args ) => [ [ 'id', 'text' ] ].
		'label_callback' => [ My_Search::class, 'products' ],
		'chip'           => __( 'Product: %s', 'my-plugin' ),
		'clears'         => [ 'price_id' ],
	],
	// No source: not drawn, but carried and shown as a chip while it is set.
	'price_id'   => [ 'type' => 'search', 'chip' => __( 'Price: %s', 'my-plugin' ) ],
],
```

The value reaches `get_items` under its key like any filter's. While one is
on, a chip above the table names it and its cross takes that one filter off;
the status links keep it, and `get_counts` is handed its query arguments so
their counts are counted within it.

## What the list says after an action

After a row action, a bulk action, Bulk Edit, Quick Edit or Delete, the list
says what happened and to what -- "Elena Okafor was blocked.", "3 orders
marked as complete." -- in the consumer's words, named by the table's own
title (or an `item_name` callback). The sentence is kept on the server for
whoever did it and only a token goes in the redirect, which lands back on the
view the action was taken from: status, search, filters, sort and page.

```php
'bulk_actions' => [
	'block' => [
		'label'    => __( 'Block', 'my-plugin' ),
		'messages' => [
			'one'  => __( '%s was blocked.', 'my-plugin' ),      // the name
			'some' => __( '%s were blocked.', 'my-plugin' ),     // up to three names
			'many' => __( '%s customers blocked.', 'my-plugin' ), // the count
		],
	],
],
// Bulk Edit, Quick Edit and Delete are worded in the table's own `messages`,
// as `edit`, `quick_edit` and `delete`; a callable gets the whole outcome.
'messages' => [ 'quick_edit' => static fn( array $outcome ): string => '...' ],
```

A handler that could not change a row says so, and the notice says which and
why: `SugarCommerce\Tables\Outcome::fail( $id, 'Order #98 is refunded' );`.

A redirect of your own can say an error by code, `?error=no_stock`, worded in
the table's `errors`: `'errors' => [ 'no_stock' => __( 'Out of stock.', 'my-plugin' ) ]`.
Text in `?error=` is never printed, since anyone can send a link: a code the
table did not declare shows only "That could not be done. Please try again."

Every notice is core's own `wp_admin_notice()` -- the markup it always had --
so core's `wp_admin_notice_args` and `wp_admin_notice_markup` filters reach
it.

## Rows from a BerlinDB table

`Sources\BerlinDB::callbacks()` turns the table's request into
[BerlinDB](https://github.com/berlindb/core) query arguments, so a list
declares where its rows are and gets rows, the pager's count, the counts
beside the status links, the row Quick Edit redraws, Delete, and the
`update` a status move writes through:

```php
use SugarCommerce\Tables\Sources\BerlinDB;

'callbacks' => BerlinDB::callbacks( [
	'query'      => \MyPlugin\Database\Queries\Order::class,
	'statuses'   => [ 'complete', 'pending', 'refunded' ], // counted for the views
	'filters'    => [ 'country', 'gateway' ],              // passed through as columns
	'filter_ids' => [ 'customer' => 'customer_id' ],        // an id: 0 or junk matches nothing
	'scope'      => static fn(): array => [ 'is_test' => my_plugin_is_test_mode() ? 1 : 0 ],
	'prime'      => [ 'customer_id' => \MyPlugin\Database\Queries\Customer::class ],
] ),
```

* **`number` and `offset`** are read, as the library sends them -- not
  `per_page` and `paged` -- and `search`, not the request's `s`. A page is
  at most 999 rows.
* **Only declared filters** reach the query. `scope` is applied last, so
  nothing in the address steps outside it (a test mode, an owner).
* **Asked with `count`**, `get_items` answers how many match, which is how
  the pager counts a searched or filtered view; `get_counts` is counted
  within the scope and within any search filter that is on.
* **A country's name finds its rows.** When `country` is one of the
  filters, "germany", "Germ" and "de" all find the `DE` rows as well as
  rows whose words match (`'country_search' => false` to turn it off, or a
  callable `( string $term ): string[]` for a site's own names).
  `search_also` widens a search other ways -- `[ 'customer_id__in' => fn(
  $term ) => my_plugin_customer_ids( $term ) ]` -- and `search_limit` caps
  each half (default: every id, so the pager never lies).
* **`prime`** fetches what a page's rows point at in one query per column:
  a BerlinDB Query class (its object cache answers the cells afterwards),
  a data component, or a callable given the ids. `also` is handed the
  page's rows.
* **A [sugarcommerce/data](https://github.com/SugarCommerce/data) component**
  can stand for its query class, in `query` and in `prime`:
  `'query' => $components->get( 'order' )`. The list then reads through the
  same class, and the same object cache, as everything else that asks the
  component.

Any of `get`, `count`, `one`, `delete` and `update` can be given as a
callable instead of coming from `query` -- how a plugin keeps the filters
its own wrapper functions apply. `BerlinDB::query_args( $args, $options )`
is public, for a list that builds its own query around the same reading,
and `BerlinDB::query( $args, $options )` is that with the search widening
added -- what a sum under the column wants, so it adds up the rows shown.

## Moving a row's status

`transitions` are keyed by the status a row is in: each row offers its next
move -- a draft is published, an archived thing restored -- and never the
status it already has. A bulk action with a `status` sets it on every
ticked row. Both write through `callbacks.set_status` ( `$id`, `$status` ),
or, failing that, `callbacks.update` ( `$id`, `[ 'status' => ... ]` ),
which the BerlinDB source provides. Only a `false` is a failure.

```php
'transitions'  => [
	'active'   => [ 'disabled' => __( 'Block', 'my-plugin' ) ],
	'disabled' => [ 'active' => __( 'Unblock', 'my-plugin' ) ],
],
'bulk_actions' => [
	'block' => [ 'label' => __( 'Block', 'my-plugin' ), 'status' => 'disabled' ],
],
// The word for a status inside a sentence; default the view's label.
'status_label' => static fn( string $status ): string => my_plugin_status_label( $status ),
```

The list then says "Elena Okafor is now blocked." or "12 customers are now
blocked.", and of a ticked row already there, "1 could not be changed: Kira
Ito is already blocked." A table's own `messages` or `callback` on an action
is left alone. For a handler of your own, `Transitions::already( $table_id,
$row, $status )` says "already" the same way, and
`Transitions::edited_messages( $table_id )` words a Quick Edit or Bulk Edit
that set a status (as the table's `messages` `quick_edit` and `edit`).

## A switch in a cell

A column's `toggle` makes each cell a button that saves itself over REST
and redraws in place: a **star** (a glyph, as WooCommerce marks a featured
product) or a **line** of words under something else ("✓ Subscribed" /
"Not subscribed"). Pressed, it turns over at once; if the save is refused
it turns back and says so beside itself. `aria-pressed` says whether it is
on, the new state is read out, and focus never leaves it. Somebody without
the capability sees the state and no button.

```php
'columns' => [
	'featured' => [
		'label'  => SugarCommerce\Tables\Toggle::heading( __( 'Featured', 'my-plugin' ) ),
		'toggle' => [
			'style'        => 'star',
			'on'           => __( 'Featured', 'my-plugin' ),
			'off'          => __( 'Not featured', 'my-plugin' ),
			'turn_on'      => __( 'Mark as Featured', 'my-plugin' ),
			'turn_off'     => __( 'Remove from Featured', 'my-plugin' ),
			// The button's name, with the row's: twenty buttons called
			// "Mark as Featured" cannot be told apart by ear.
			'turn_on_row'  => __( 'Mark “%s” as Featured', 'my-plugin' ),
			'turn_off_row' => __( 'Remove “%s” from Featured', 'my-plugin' ),
			// Any icon in either form (see Icons); this pair is a star's default.
			'icons'        => [ 'on' => 'heroicons-solid:star', 'off' => 'heroicons-outline:star' ],
			'done_on'      => __( '“%s” marked as featured.', 'my-plugin' ),
			'done_off'     => __( '“%s” removed from featured.', 'my-plugin' ),
			// Your own route: the id fills {id}, the body is { key: true|false } plus `data`.
			'rest'         => [ 'path' => 'my-plugin/v1/products/{id}', 'method' => 'PATCH', 'key' => 'is_featured' ],
			'capability'   => 'edit_products',
		],
	],
	'consent'  => [
		'label'  => __( 'Email', 'my-plugin' ),
		'toggle' => [
			'field'       => 'marketing_consent',         // the row's property; the column key by default
			'on'          => __( 'Subscribed', 'my-plugin' ),
			'off'         => __( 'Not subscribed', 'my-plugin' ),
			'name'        => __( 'Subscribed to Marketing', 'my-plugin' ), // a fixed name instead
			'turn_on'     => __( 'Mark as Subscribed', 'my-plugin' ),
			'turn_off'    => __( 'Mark as Not Subscribed', 'my-plugin' ),
			// Said before the first "on" ever (per browser), which then waits for a second press.
			'confirm_on'  => __( 'Only do this if they agreed to hear from you.', 'my-plugin' ),
			'press_again' => __( 'Press again to mark them as subscribed.', 'my-plugin' ),
			'since'       => static fn( $row ): string => sprintf( __( 'Since %s', 'my-plugin' ), my_plugin_day( $row->date_consent ) ),
			'say_why'     => true,                         // show the server's message, not `failed`
			// Or no `rest`: the library's own route checks the capability and calls this.
			'save'        => static fn( int $id, bool $on ): bool => my_plugin_set_consent( $id, $on ),
		],
	],
],
```

A toggle that belongs inside another cell -- the line under an address --
is declared under the table's `toggles` by a key of its own, and drawn from
that column's callback:

```php
'toggles' => [ 'consent' => [ /* as above */ ] ],
'columns' => [
	'email' => [
		'label'    => __( 'Email', 'my-plugin' ),
		'callback' => static fn( $row ): string => esc_html( $row->email )
			. SugarCommerce\Tables\Toggle::render( 'my_customers', 'consent', $row ),
	],
],
```

With `rest`, the answer is read back for the state (`rest.state`, the key
by default) and, if `rest.since` names a date field, the tooltip's
`since_label` ("Since %s"). With `save`, the route is
`sugarcommerce-tables/v1/{table_id}/toggle/{id}` (`ToggleRoute::path()`; a Strauss
build puts its own prefix first, so two plugins never share one); `save` answers `true`,
`false`, a `WP_Error`, or `[ 'on' => bool, 'since' => string ]`. Register
the table by `init`, so the route exists on the REST request. `failed`
(default "Not saved. Try again.") is what a refused save says.

A star is `heroicons-solid:star` when on and `heroicons-outline:star` when
off; a line has `heroicons-outline:check` before "on" and nothing before
"off" (an `icons` state of `''` draws none). `Toggle::heading( $word, $icon )`
heads the column with `heroicons-solid:star` unless given another icon --
`Toggle::heading( __( 'Loved', 'my-plugin' ), 'heroicons-outline:heart' )`.

## Rows in an order somebody chooses

`reorder` gives one column a drag handle and up and down buttons, and saves
the order the rows end up in. The handle drags with a mouse or a finger
and answers the arrow keys; below core's 782px breakpoint, where a row
cannot be dragged along a folded column, the buttons take over. Each move
posts the page's ids, top to bottom, and says where the row went.

```php
Tables::register( 'my_collections', [
	// ...
	'orderby'  => 'sort_order',
	'order'    => 'asc',
	'per_page' => 100, // a row is only dragged past rows on its own page
	'columns'  => [
		'name'       => [ 'label' => __( 'Collection', 'my-plugin' ), 'title' => 'my_plugin_name', 'link' => 'edit_flyout' ],
		'sort_order' => __( 'Position', 'my-plugin' ),
	],
	'reorder'  => [
		'column'   => 'sort_order',                         // where the handle goes
		'save'     => static fn( array $ids ): bool => my_plugin_reorder( $ids ),
		// Optional:
		'orderby'  => 'sort_order',                         // the sort that is this order; `column` by default
		'position' => static fn( $row ): int => my_plugin_place( $row ), // the place to show when sorted otherwise
		'filtered' => false,                                // allow moving within a status or filter
		'labels'   => [ 'moved' => __( '“%1$s” moved to position %2$s of %3$s.', 'my-plugin' ) ],
	],
] );
```

The controls are drawn only while the list shows that order: not sorted by
another column (or by this one going down), not searched, and not narrowed
by a status or filter unless `filtered` says so. The number is the row's
place, first is 1. The save goes through admin-ajax with the table's own
nonce and the table's edit capability (or `capability`); `save` gets the
ids as integers and may return an array, which is handed back.

## A person

A column of `'type' => 'person'` draws someone the way every WordPress list
does: their picture, their name as the link, and their address as a quieter
line under it, each cut short with an ellipsis and whole in its tooltip.
The address is text, not a mailto. With no name the address stands in for
it, once; with nothing to open, the cell is text; with nobody, a dash.

```php
'customer' => [
	'label'      => __( 'Customer', 'my-plugin' ),
	'type'       => 'person',
	// Who the row's person is: name, email, id, and `after` -- escaped
	// markup that ends the name's line (tags, a link to their other rows).
	// Null is nobody. Left out, the row's own name, email and id.
	'person'     => static fn( object $order ): ?array => [
		'name'  => $order->customer_name,
		'email' => $order->email,
		'id'    => (int) $order->customer_id,
		'after' => my_plugin_customer_tags( $order ),
	],
	// The table's 'view_flyout' or 'edit_flyout', a panel's full id, a URL,
	// or a closure returning one.
	'link'       => 'view_flyout',
	// ( $email, $name, $size, $args ) => escaped markup; false for none.
	// Left out, core's get_avatar(). $args carries the float to keep.
	'avatar'     => static fn( string $email, string $name, int $size, array $args ): string => my_plugin_monogram( $email, $name, $size, $args ),
	'email_line' => true, // false when the address has a column of its own
],
```

The cell lays itself out with inline styles, so it is the same inside a
panel's table on a screen that never loaded this library's stylesheet. It is
`SugarCommerce\Tables\Cells\Person::cell( $parts )` for a cell drawn by
hand, and `sugarcommerce_table_person_cell_{table_id}` filters it in a table.
A link given the class `sugarcommerce-reveal` at the end of the name's line is
shown, like a row action, only while the row is pointed at or focused.

## One thing a row names, with its picture

A column of `'type' => 'item'` is what an order's Items cell is: a small
picture, the name on one line with its tooltip, "+ 2 others" beside it, and
quieter lines beneath. Its `item` callable returns the parts:

```php
'items' => [
	'label' => __( 'Items', 'my-plugin' ),
	'type'  => 'item',
	'item'  => static fn( object $order ): array => [
		'title'   => $order->first_item_name,        // text
		'link'    => '',                             // escaped markup in place of the title
		'tooltip' => implode( ', ', $order->names ), // text; the title when left out
		'more'    => count( $order->names ) - 1,     // "+ 2 others"; or your own text
		'picture' => SugarCommerce\Tables\Cells\Item::picture( (int) $order->image_id, 'box', 24 ),
		'lines'   => [ esc_html( $order->downloads_line ) ], // escaped markup, a line each
	],
],
```

`Item::picture( $image_id, $kind, $size )` is the attachment, or a drawn
placeholder tile -- an inline data URI, never a request -- when there is
none or it has gone: `box` for a thing, `stack` for a group of things, or
`[ 'placeholder' => $svg ]` for a glyph of your own in a forty-unit square.
Under forty pixels it is a square that says nothing aloud, for beside a
name. `Item::cell( $parts )` draws one by hand; `sugarcommerce_table_item_cell_{table_id}`
filters it in a table.

## Relative or exact dates

A date column says "2 weeks ago", with the exact moment on hover. Give the
table a `date_preference` and Screen Options gains "Dates: Relative / Exact",
a choice kept for each person: Exact says "13 Oct 2026, 3:20 pm" with how
long ago on hover. The same words at any age -- "2 years ago" never turns
into a date half way down a column -- and "in 3 weeks" for one to come.

```php
Tables::register( 'my_orders', [
	// true: a preference of this table's own (`my_orders_dates`).
	// A string: a user meta key every list naming it shares, so the choice
	// made on one list holds on all of them.
	'date_preference' => 'my_plugin_list_dates',
	'columns'         => [
		'date_created' => __( 'Date', 'my-plugin' ),
		'date_expires' => [ 'label' => __( 'Expires', 'my-plugin' ), 'type' => 'date', 'none' => __( 'Never', 'my-plugin' ) ],
	],
] );
```

BerlinDB's zero date is no date, and says the column's `none` or a dash. A
column's `format` replaces the exact reading's date format. It is saved by
core's own Apply, in the same form and under the nonce core already checked.
For a cell drawn by hand:

```php
use SugarCommerce\Tables\ListDate;

ListDate::cell( $row->date_renews, ListDate::mode( 'my_plugin_list_dates' ), [ 'none' => __( 'Never', 'my-plugin' ) ] );
```

`sugarcommerce_table_date_mode_{meta_key}` can answer `relative` or `exact` for
everybody.

The relative reading is sugarcommerce/dates' `Site::relative()`, the one
relative-time renderer in the family. The exact reading's default is a short
date ("13 Oct 2026, 3:20 pm", translatable), not the site's `date_format` and
`time_format`, which are a long date meant for a post; pass `format` for those.

## A column that costs something to show

A column that asks another table -- what a customer last bought, where a
key was last used -- is still worked out by core while it is switched off
in Screen Options. Declare it `'deferred' => true` and, while it is hidden,
its cells hold a placeholder and its callback is never called; switched on,
the list reloads once core has saved the choice, and the column is drawn.

```php
'last_order' => [
	'label'    => __( 'Last Order', 'my-plugin' ),
	'hidden'   => true,
	'deferred' => true,
	'callback' => 'my_plugin_last_order_cell',
],
```

A column with something it can show for free sets `'deferred' => 'manual'`
and decides itself, with `HiddenColumns::is_hidden( $table_id, $column )`
and `HiddenColumns::placeholder()` -- which is also how a `get_items` skips
priming what a hidden column would have asked for.
`sugarcommerce_table_column_hidden_{table_id}` can say a column is read off the
page whether it is shown or not.

Which columns are hidden is core's reading, `get_hidden_columns()`: what the
person saved in Screen Options, else the table's `hidden_columns` (said to
core through `default_hidden_columns`), then core's `hidden_columns` filter.
How many rows a page holds is core's too, `WP_List_Table::get_items_per_page()`
under the option `{table_id}_per_page` (`Tables::per_page_option( $id )`),
with core's `{table_id}_per_page` filter.

## New since you last looked

A list can light the rows that have arrived since whoever is looking last
opened it -- each person's own, so two people running one site never clear
each other's -- and count them beside its menu item, the way Comments does.
What is remembered is the id of the newest row there was at their last
visit, in user meta named for the table (`{table_id}_seen`), so "new" is a
question the primary key answers. And no older than a window of days (seven
unless you say), which is what keeps a fortnight away, or a first visit,
from lighting everything.

```php
'seen' => [
	'label'      => __( 'New order', 'my-plugin' ), // read aloud on a new row
	'table'      => 'shop_orders',                   // without $wpdb->prefix; default: the table id
	'columns'    => [ 'id' => 'id', 'created' => 'date_created' ],
	'window'     => 7,                               // days, or a callable; 0 for no limit

	// Kept apart: a marker for each set, and only the set showing counts.
	'scopes'     => [ 'test' => [ 'is_test' => 1 ], 'live' => [ 'is_test' => 0 ] ],
	'scope'      => static fn(): string => my_plugin_is_test_mode() ? 'test' : 'live',

	// Never news, however lately they arrived.
	'except'     => [ 'status' => [ 'refunded', 'failed', 'cancelled' ] ],

	// Rows arriving or going, so a count kept for a minute is not believed.
	'changed'    => [ 'my_plugin_add_order', 'my_plugin_delete_order' ],

	// Rows somebody made by hand are not news to them. A callable is given
	// the action's arguments and answers with the row's id, nought for
	// "not by hand", or [ id, set ].
	'by_hand'    => [
		'my_plugin_order_completed' => static fn( $id, $session = null ) => null === $session ? (int) $id : 0,
	],

	'ready'      => 'my_plugin_tables_installed', // nothing is asked until true
	'menu_count' => [
		'text' => static fn( int $count, string $number ): string => sprintf(
			/* translators: %s: a number of orders. */
			_n( '%s new order', '%s new orders', $count, 'my-plugin' ),
			$number
		),
	],
],
```

A row is lit for the visit that sees it and plain on the next: the marker is
read as the list is opened and moved once the rows are out, forwards only,
so two tabs and the back button cannot bring seen rows back, and a row that
arrives while the page is being built is still new next time. A row redrawn
on its own after a Quick Edit comes back lit as it was.

Hearing about a row reads nothing, and is all that is hooked outside the
list's own screen. Tables registered on admin screens only should also call
`SugarCommerce\Tables\Seen\Seen::register( 'shop_orders', [ 'seen' => [ ... ] ] )`
on every request, so a row made in a webhook is heard. The count is one
query (or a `count` callable, for rows that are not a table of their own,
with `newest` beside it), kept in the object cache for a minute.

`Seen::count( 'shop_orders' )` is the figure the bubble shows, for a
dashboard or an app; `Seen::mark_all( 'shop_orders' )` says somebody has seen
everything; `Seen::is_new( 'shop_orders', $row )` answers for one row. The
bubble is `menu_count`: beside the list and beside its parent (`'parent' =>
false` for the list alone), "99+" past ninety-nine with the real number read
aloud, and never on the list's own screen.

## Lit rows

The ways a row can be lit, tried in order of `priority`; the first a row is in
wins. A table with `seen` has `new` (green, 50) without saying so, and
`blocked` (red, 10) and `excused` (amber, 20) when its `seen` names
`blocked` rows and an `excused` callable. Add your own, or change those, in
`highlights`:

```php
'highlights' => [
	'class'  => 'my-plugin-row', // rows get `my-plugin-row-{state}`; default `sugarcommerce-row`
	'states' => [
		'attention' => [
			'label'    => '',        // what it is read aloud as; empty when the row says why in words
			'color'    => '#dba617', // the bar
			'tint'     => true,      // and a tenth of it behind the row
			'priority' => 30,
			'forced'   => 'double',  // high-contrast mode: highlight, double, dashed or solid
			'when'     => static fn( object $row, string $table_id ): bool => 'disputed' === $row->status,
		],
	],
],
```

A lit row is a bar down its start edge -- the mark core gives a comment
waiting to be approved -- as a real border in logical properties, so it is on
the right in a right-to-left admin and kept by a high-contrast mode, where
the states are told apart by line style. Its words are read aloud in the
row's first cell, before the row actions (`'announce' => false` to place
`Highlights::label()` yourself). Nothing moves. The stylesheet loads only on
a list that lights rows, and takes the cream off a pending row there, since a
tint means "wants attention". `Highlights::for_list( $registry, 'orders' )`
picks one list's states out of a registry shared by several, each naming its
`lists` and, where it has a better word for one, `labels`.

## Icons

Everything here that takes an `icon` takes it in either form
sugarcommerce/fields' `Icons::render()` reads: a `set:name` value -- `heroicons-outline:inbox`,
`heroicons-solid:heart`, any set it has -- drawn as inline SVG in the
text's colour, or a Dashicons name, `cart` or `dashicons-cart`, drawn as the
font's span exactly as before. Either way it carries the `sugarcommerce-icon`
class, which the stylesheets size it by.

| Where | Default |
| --- | --- |
| `empty_state.icon` -- the glyph over an empty list; `''` for none | `heroicons-outline:inbox` |
| A toggle's `icons` -- `[ 'on' => …, 'off' => … ]` | star: `heroicons-solid:star` / `heroicons-outline:star`; line: `heroicons-outline:check` / none |
| `Toggle::heading( $word, $icon )` | `heroicons-solid:star` |

```php
'empty_state' => [
	'icon'        => 'heroicons-outline:shopping-cart', // or 'dashicons-cart'
	'heading'     => __( 'No orders yet.', 'my-plugin' ),
	'description' => __( 'Orders appear here as customers check out.', 'my-plugin' ),
],
```

The library's own glyphs are Heroicons too: a boolean column's yes
(`check-circle`) and no (`minus`), and the reorder handle (`bars-3`) and its
buttons (`chevron-up`, `chevron-down`). The top-level menu's `icon` is
WordPress's own `add_menu_page()` argument -- a Dashicons class, an image URL
or a `data:image/svg+xml;base64,` URI -- and is passed to it unchanged.

## Hooks

Every hook is suffixed with the table's own id, so a filter written for one
plugin's orders table never reaches another's. `{table_id}` below is whatever
was passed to `Tables::register()`.

### Filters

| Hook | Filters |
| --- | --- |
| `sugarcommerce_table_columns_{table_id}` | The columns, after the config is read |
| `sugarcommerce_table_hidden_columns_{table_id}` | Which columns start hidden |
| `sugarcommerce_table_sortable_columns_{table_id}` | Which columns can be sorted |
| `sugarcommerce_table_views_{table_id}` | The status links above the table |
| `sugarcommerce_table_row_actions_{table_id}` | The actions under a row |
| `sugarcommerce_table_bulk_actions_{table_id}` | The bulk actions dropdown |
| `sugarcommerce_table_query_args_{table_id}` | The arguments before items are fetched |
| `sugarcommerce_table_admin_notices_{table_id}` | The notices shown above the table |
| `sugarcommerce_table_quick_edit_fields_{table_id}` | The fields in the Quick Edit row |
| `sugarcommerce_table_bulk_edit_fields_{table_id}` | The fields in the Bulk Edit row |
| `sugarcommerce_table_person_cell_{table_id}` | A `person` cell, with its parts and the row |
| `sugarcommerce_table_item_cell_{table_id}` | An `item` cell, with its parts and the row |
| `sugarcommerce_table_column_hidden_{table_id}` | Whether a column counts as hidden, for not working it out |
| `sugarcommerce_table_date_mode_{meta_key}` | `relative` or `exact`, for everyone sharing a date preference |
| `sugarcommerce_table_seen_capability_{table_id}` | Who is told what is new on the list |
| `sugarcommerce_table_new_count_{table_id}` | How many rows are new to somebody |
| `sugarcommerce_table_highlights_{table_id}` | The ways a row can be lit |
| `sugarcommerce_table_row_highlight_{table_id}` | How one row is lit |
| `sugarcommerce_table_highlight_label_{table_id}` | What a lit row says aloud |

### Actions

| Hook | Fires |
| --- | --- |
| `sugarcommerce_after_render_table_header_{table_id}` | After the heading and notices, before the status links |
| `sugarcommerce_before_render_table_{table_id}` | Before the table is drawn |
| `sugarcommerce_after_render_table_{table_id}` | After the table is drawn |
| `sugarcommerce_table_single_action_{table_id}` | A row action with no handler of its own |
| `sugarcommerce_table_bulk_action_{table_id}` | A bulk action was applied |
| `sugarcommerce_table_item_deleted_{table_id}` | An item was deleted |
| `sugarcommerce_table_quick_edit_{table_id}` | One row was quick edited |
| `sugarcommerce_table_bulk_edit_{table_id}` | An edit was applied to a selection |
| `sugarcommerce_table_inline_edit_{table_id}` | Inside either inline editor, to print extra markup |
| `sugarcommerce_table_toggled_{table_id}` | A toggle cell saved through the library's route: `( $id, $column, $on )` |
| `sugarcommerce_table_reordered_{table_id}` | Rows were put in a new order: `( $ids )` |

A bulk action also fires a second, narrower action naming the action itself,
which is usually the one worth hooking:

```php
add_action( 'sugarcommerce_table_bulk_action_{table_id}_{action}', function ( array $ids ) {
	foreach ( $ids as $id ) {
		my_plugin_refund_order( (int) $id );
	}
} );
```

Both the table id and the action name are substituted, and the broader hook in
the table above still fires alongside it.

## Quick Edit and Bulk Edit

Core's two inline editors, on a table that is not a post type. Declaring
either one is enough -- a table with only `quick_edit` gets the same fields in
its bulk row, and the other way round.

```php
Tables::register( 'my_products', [
	// ...
	'quick_edit'   => [
		'status' => [
			'label'   => __( 'Status', 'my-plugin' ),
			'options' => [
				'active' => __( 'Active', 'my-plugin' ),
				'draft'  => __( 'Draft', 'my-plugin' ),
			],
		],
	],
	'bulk_actions' => [ 'edit' => __( 'Bulk edit', 'my-plugin' ) ],
	'callbacks'    => [
		'get_item' => 'my_plugin_get_product',
	],
] );
```

Neither editor writes anything. They validate what was submitted -- a select
only accepts a value it actually offered -- and then fire an action, because
only you know whether a status change also has to reach somewhere else:

```php
add_action( 'sugarcommerce_table_quick_edit_{table_id}', function ( int $id, array $values ) {
	my_plugin_update_product( $id, $values );
}, 10, 2 );

add_action( 'sugarcommerce_table_bulk_edit_{table_id}', function ( array $ids, array $values ) {
	foreach ( $ids as $id ) {
		my_plugin_update_product( (int) $id, $values );
	}
}, 10, 2 );
```

### A checklist

A field with `'type' => 'checklist'` is core's category checklist: the
scrolling list of boxes the Posts screen's Quick Edit and Bulk Edit show for
categories, in the same middle column with the same classes, so core's
stylesheet draws it. For anything a row can be in several of -- collections,
tags, groups. `options` are what can be ticked; `value` says what a row is in,
when that is not a property of the row (an array or a comma list either way).

```php
'quick_edit' => [
	'collections' => [
		'type'    => 'checklist',
		'label'   => __( 'Collections', 'my-plugin' ),
		'options' => [ 3 => 'Drums', 5 => 'Loops' ],          // id => name
		'value'   => fn( $row ) => my_plugin_collection_ids( $row->id ),
	],
],
```

Quick Edit opens with the row's own boxes ticked and hands the save the ids
now ticked, as strings -- `[ '3', '5' ]`, or `[]` when every box was
cleared. Bulk Edit behaves as core's does in WordPress 7.1: a box every
selected row has opens ticked, one only some have opens half-ticked, and the
save receives what to change rather than a list to set:

```php
add_action( 'sugarcommerce_table_bulk_edit_{table_id}', function ( array $ids, array $values ) {
	// [ 'add' => [ '3' ], 'remove' => [ '5' ] ] -- a half-ticked box left
	// alone is in neither, so each row keeps its own answer for it.
	$change = $values['collections'] ?? null;

	foreach ( $ids as $id ) {
		$now = array_diff( my_plugin_collection_ids( $id ), $change['remove'] );
		my_plugin_set_collections( $id, array_unique( array_merge( $now, $change['add'] ) ) );
	}
}, 10, 2 );
```

Unticking a box every selected row had takes it off all of them, as it does
for a category. Either editor leaves the key out when the field was not
posted at all, which is "leave it alone".

Two things are worth knowing. `'edit'` in `bulk_actions` is what reveals the
bulk row -- without it there is a bulk editor nothing opens. And Quick Edit
redraws the saved row from `callbacks.get_item`; leave that out and the save
happens but the row does not change, which reads exactly like a save that
failed.

## Moving from arraypress/wp-register-tables

Every configuration key, cell type, class and method keeps its name; the
config a list passes today is read the same way.

| Old | New |
|---|---|
| `ArrayPress\RegisterTables\…` | `SugarCommerce\Tables\…` |
| `ArrayPress\RegisterTables\Manager` | `SugarCommerce\Tables\Tables` (every method, same name) |
| `register_admin_table( $id, $config )` | `Tables::register( $id, $config )` |
| `get_table_renderer( $id )` | `Tables::renderer( $id )` |
| `register_parent_menu( $slug, $title, $args )` | `ParentMenu::register( $slug, $title, $args )` |
| hooks `arraypress_table_*_{table_id}` | `sugarcommerce_table_*_{table_id}` |
| hooks `arraypress_before_render_table_{id}`, `arraypress_after_render_table_{id}`, `arraypress_after_render_table_header_{id}` | `sugarcommerce_before_render_table_{id}`, `sugarcommerce_after_render_table_{id}`, `sugarcommerce_after_render_table_header_{id}` |
| `arraypress_table_date_mode_{meta_key}` | `sugarcommerce_table_date_mode_{meta_key}` |
| CSS classes `arraypress-*` (`arraypress-reveal`, `arraypress-item-meta`, `arraypress-person-name`, `arraypress-row-{state}`, `arraypress-toggle*`, `arraypress-table-*`…) | `sugarcommerce-*`, the rest of the name unchanged |
| icons' class `arraypress-icon` | `sugarcommerce-icon` (sugarcommerce/fields) |
| REST `arraypress-tables/v1/{table}/toggle/{id}` | `sugarcommerce-tables/v1/…`, or `{prefix}-sugarcommerce-tables/v1/…` in a Strauss build: ask `ToggleRoute::path( $table )` rather than matching a literal |
| `ToggleRoute::NAMESPACE` | `Utils\Runtime::rest_namespace()` |
| admin-ajax `arraypress_table_quick_edit` | `Runtime::key( 'quick_edit' )` (`sugarcommerce_tables_quick_edit`), handed to the script |
| admin-ajax and nonce `arraypress-table-reorder-{table}` | `Reorder::action( $table )` (`sugarcommerce-tables-reorder-{table}`) |
| handles `list-table-styles`, `list-table-inline-edit`, `list-table-deferred-columns`, `arraypress-table-toggle`, `arraypress-table-reorder`, `arraypress-table-highlights` | `Runtime::handle()`: `sugarcommerce-tables`, `sugarcommerce-tables-inline-edit`, `…-deferred-columns`, `…-toggle`, `…-reorder`, `…-highlights` |
| `Bars::HANDLE`, `Rows::CACHE` (`arraypress-table-seen`) | `Bars::handle()`, `Rows::cache_group()` (`sugarcommerce-tables-seen`) |
| JS globals `arrayPressInlineEdit`, `arrayPressInlineEditor`, `arrayPressToggles`, `arrayPressReorder`; header `X-ArrayPress-Table-Notice` | `sugarCommerceInlineEdit`, `sugarCommerceInlineEditor`, `sugarCommerceToggles`, `sugarCommerceReorder`; `X-SugarCommerce-Table-Notice` |
| filter `register_tables_default_currency` | `SugarCommerce\Money\Money::set_default_currency()`, once per plugin |
| wp-register-flyouts `Registry`, wp-inline-sync `Registry` | sugarcommerce/flyouts `Registry`, sugarcommerce/jobs `Sync` |
| wp-field-kit, wp-status-badge, wp-countries, wp-date-utils, wp-money, wp-format-utils, wp-composer-assets | sugarcommerce/fields, status-badge, geo, dates, money, text, plugin |
| text domain `arraypress` | `sugarcommerce` |
| `Table::get_items_per_page()` override (read the user meta by hand) | removed: core's `WP_List_Table::get_items_per_page( Tables::per_page_option( $id ), $default )`, which adds the `{table_id}_per_page` filter |
| hidden columns read from `manage{screen}columnshidden` by hand | `HiddenColumns::of( $table_id, $config )`, core's `get_hidden_columns()` (adds the `hidden_columns` filter) |
| notices printed by hand; `ActionNotice::render()` | `wp_admin_notice()` / `wp_get_admin_notice()`, the same markup |
| `ParentMenu::fix_menus()` unset `[0]` | `remove_submenu_page( $slug, $slug )`: only the entry that links to the parent goes |
| relative dates | sugarcommerce/dates `Site::relative()` (`ListDate::relative()` calls it) |
| `fixed` table layout: every column the same width unless given one | laid out by its contents; figures narrow (`compact`), see [Widths](#widths-the-primary-column-and-phones) |
| primary column: the first column | the first that is not a bare `id`, and its row's status and amount under the title on a phone (`summary`) |
| hidden row actions kept their height under every title | they take none until shown |

What somebody chose is kept: the user meta keys (`{table_id}_seen`, the date
preference) and the BerlinDB source's options are unchanged. Status badge
labels are sentence case now ("On hold"), as sugarcommerce/status-badge
writes them.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
