/**
 * WordPress's Prettier settings, without formatting the markup inside
 * `html` templates: whitespace there is content.
 */
module.exports = {
	...require( '@wordpress/prettier-config' ),
	embeddedLanguageFormatting: 'off',
};
