<?php
/**
 * Inline Checklist
 *
 * @package SugarCommerce\Tables
 */

declare( strict_types=1 );

namespace SugarCommerce\Tables\Editing;

use SugarCommerce\Tables\Support\Row;

defined( 'ABSPATH' ) || exit;

/**
 * A list of things a row can be in, ticked in an inline editor.
 *
 * Core's category checklist on the Posts screen, for a table that is not a
 * post type: a field declared `'type' => 'checklist'` with its `options`
 * (id => name) is drawn as the scrolling `ul.cat-checklist` core draws for
 * categories, in the same middle fieldset, with the same classes -- so
 * core's list-tables.css styles it and somebody who has filed a post under
 * a category already knows how it works.
 *
 * Core's behaviour comes with it, and core's Bulk Edit is the subtle part.
 * In WordPress 7.1 a category every ticked post is in opens ticked, one
 * some of them are in opens half-ticked (indeterminate), and on Update
 * bulk_edit_posts() keeps each post's half-ticked ones as they were and
 * sets the rest to what the boxes say -- so unticking a category every
 * selected post has takes it off all of them. Bulk Edit can remove, not
 * only add. The save here hands the caller that as two lists, `add` and
 * `remove`, which applied to a row as (current - remove) + add come out
 * at exactly core's (current ∩ indeterminate) ∪ (checked - indeterminate).
 *
 * @since 1.0.0
 */
final class InlineChecklist {

	/**
	 * Whether a field is a checklist.
	 *
	 * @param array $field The field.
	 *
	 * @return bool
	 */
	public static function is( array $field ): bool {
		return 'checklist' === ( $field['type'] ?? '' );
	}

	/**
	 * What a row is in, for the hidden data block the script reads.
	 *
	 * A comma list, as core prints a post's categories into
	 * `<div class="post_category">`. From the field's `value` callback when
	 * it has one -- what a row is in is usually another table, not a
	 * property of the row -- and otherwise off the row itself, which may
	 * hold an array or a comma list already.
	 *
	 * @param array  $field The field.
	 * @param mixed  $item  The row.
	 * @param string $name  The field's name.
	 *
	 * @return string
	 */
	public static function row_value( array $field, $item, string $name ): string {
		$value = isset( $field['value'] ) && is_callable( $field['value'] )
			? call_user_func( $field['value'], $item )
			: Row::get( $item, $name, '' );

		if ( is_array( $value ) ) {
			return implode( ',', array_map( 'strval', $value ) );
		}

		return (string) $value;
	}

	/**
	 * Draw the middle fieldset holding every checklist in an editor.
	 *
	 * Core's markup line for line: the label, a hidden input under the
	 * field's own name so that nothing ticked is still a submission -- core
	 * posts `post_category[]=0` for the same reason -- and the list. The
	 * sentinel here is empty rather than 0, because 0 can be a real id on a
	 * table that is not WordPress's.
	 *
	 * Ids on each box are prefixed with the editor, because Quick Edit is
	 * cloned into the table while Bulk Edit is not, and two boxes sharing an
	 * id would leave a label ticking the wrong one.
	 *
	 * @param array<string, array> $fields The checklist fields.
	 * @param bool                 $bulk   Whether this is the bulk row.
	 *
	 * @return void
	 */
	public static function render( array $fields, bool $bulk ): void {
		$fields = array_filter( $fields, static fn( $field ): bool => [] !== (array) ( $field['options'] ?? [] ) );

		if ( [] === $fields ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-center inline-edit-categories">
			<div class="inline-edit-col">
				<?php foreach ( $fields as $name => $field ) : ?>
					<?php $name = (string) $name; ?>
					<span class="title inline-edit-categories-label"><?php echo esc_html( (string) ( $field['label'] ?? $name ) ); ?></span>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>[]" value="" />
					<ul class="cat-checklist <?php echo esc_attr( sanitize_html_class( $name ) ); ?>-checklist" data-checklist="<?php echo esc_attr( $name ); ?>">
						<?php foreach ( (array) $field['options'] as $value => $label ) : ?>
							<?php $id = ( $bulk ? 'bulk-' : 'quick-' ) . sanitize_html_class( $name ) . '-' . sanitize_html_class( (string) $value ); ?>
							<li id="<?php echo esc_attr( $id ); ?>"><label class="selectit"><input value="<?php echo esc_attr( (string) $value ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" id="in-<?php echo esc_attr( $id ); ?>" /> <?php echo esc_html( (string) $label ); ?></label></li>
						<?php endforeach; ?>
					</ul>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * What a Quick Edit submitted for a checklist: the ids now ticked.
	 *
	 * Null when the field did not post at all -- its sentinel missing --
	 * which is "leave it alone", as against an empty list, which is "in
	 * nothing". Only ids the field offered survive; the markup constrains a
	 * browser and nothing constrains a POST.
	 *
	 * @param array  $field  The field.
	 * @param string $name   Its name.
	 * @param array  $source The request.
	 *
	 * @return array<int, string>|null Option keys, as strings.
	 */
	public static function submitted( array $field, string $name, array $source ): ?array {
		if ( ! isset( $source[ $name ] ) || ! is_array( $source[ $name ] ) ) {
			return null;
		}

		return self::offered( $field, $source[ $name ] );
	}

	/**
	 * What a Bulk Edit submitted for a checklist: what to add and what to
	 * take away.
	 *
	 * `add` is what is ticked and was not left half-ticked; `remove` is every
	 * option neither ticked nor half-ticked. A half-ticked one arrives in
	 * `indeterminate_{name}[]` -- the script's copy of core's
	 * `indeterminate_post_category[]` -- and is in neither, so each row keeps
	 * it or not as it already did.
	 *
	 * @param array  $field  The field.
	 * @param string $name   Its name.
	 * @param array  $source The request.
	 *
	 * @return array{add: array<int, string>, remove: array<int, string>}|null
	 *         Option keys as strings; null when the field did not post.
	 */
	public static function bulk_submitted( array $field, string $name, array $source ): ?array {
		$checked = self::submitted( $field, $name, $source );

		if ( null === $checked ) {
			return null;
		}

		$half    = self::offered( $field, (array) ( $source[ 'indeterminate_' . $name ] ?? [] ) );
		$options = array_map( 'strval', array_keys( (array) ( $field['options'] ?? [] ) ) );

		return [
			'add'    => array_values( array_diff( $checked, $half ) ),
			'remove' => array_values( array_diff( $options, $checked, $half ) ),
		];
	}

	/**
	 * The submitted values the field offers, once each, in its order.
	 *
	 * @param array $field     The field.
	 * @param array $submitted What came in.
	 *
	 * @return array<int, string>
	 */
	private static function offered( array $field, array $submitted ): array {
		$submitted = array_map(
			static fn( $value ): string => is_scalar( $value ) ? sanitize_text_field( wp_unslash( (string) $value ) ) : '',
			$submitted
		);

		$kept = [];

		foreach ( array_keys( (array) ( $field['options'] ?? [] ) ) as $option ) {
			if ( in_array( (string) $option, $submitted, true ) ) {
				$kept[] = (string) $option;
			}
		}

		return $kept;
	}
}
