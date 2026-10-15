# Themes and Blocklingo

Most themes work with Blocklingo without any change. This page explains what is translated
automatically, what a theme has to do itself, and how to test a theme. It is written for theme
authors; site owners find the short version in the plugin's readme ("Theme compatibility").

## What works without changes

Blocklingo translates **blocks** and **WordPress's own data**, wherever they are shown:

| What | How it is translated |
|---|---|
| Page and post content | Block by block, in the block editor (Translate sidebar) |
| Templates, template parts (header, footer), synced patterns | Block by block, in the Site Editor |
| Navigation block (block themes' menus) | Its links are blocks: labels translated in the Site Editor, addresses follow the language |
| Classic menus (`wp_nav_menu()`, Appearance → Menus) | Label, title attribute and custom link address per language in Appearance → Menus. Items without their own label show the translated page title |
| Titles, excerpts, addresses of posts and pages | Per language in the Translate sidebar |
| Links to posts, pages, categories, tags, archives, search, page 2 | `get_permalink()`, `get_term_link()` and friends return the address in the language being shown, with translated slugs for posts and pages |
| Links you write in content (`/contact/`) | Rewritten to the same language (`/fr/contact/`) |
| `<html lang>`, `hreflang`, sitemap | Automatic |
| Site title and tagline | Settings → Blocklingo → Strings |
| **Your theme's translatable texts** (`__( 'Read more', 'mytheme' )`) | WordPress loads your translation files (`.mo`) for the visitor's language, because Blocklingo sets the language per page. Ship translations, or let translate.wordpress.org provide them, and they just work |
| Texts of themes and plugins written for WPML | WPML's hooks (`wpml_translate_single_string` and the others) are answered when WPML itself is not active |

So a **block theme with translation files** needs nothing at all.

## What a theme has to do itself

Only texts that are **not** in blocks and **not** in translation files: typically texts a site
owner types into your theme's own settings (a Customizer field, an options page), or texts you
print from PHP in the site's language without gettext.

### Texts from your own settings

Register them, so they appear on Settings → Blocklingo → Strings, and translate them when you
print them. Guard both calls with `function_exists()`: your theme must keep working without the
plugin.

```php
// Register on every request (nothing is written to the database).
add_action( 'init', function () {
	if ( function_exists( 'blocklingo_register_string' ) ) {
		blocklingo_register_string( get_theme_mod( 'footer_text', '' ), 'My Theme', 'Footer text' );
	}
} );

// Print in the visitor's language.
$text = get_theme_mod( 'footer_text', '' );
if ( function_exists( 'blocklingo_translate' ) ) {
	$text = blocklingo_translate( $text, 'My Theme' );
}
echo esc_html( $text );
```

- The **group** (`'My Theme'`) is an identifier: never pass it through `__()`, or the admin and
  the visitor (in different languages) would look in different groups.
- The **original** is the key: when the site owner changes the text, the new text is simply
  untranslated until it is translated again.

### Never translate on your settings screen

If your settings screen reads values through a function that translates them, saving that screen
would store a translation over the original. Keep two getters: one that returns what is saved
(for the settings screen and for `blocklingo_register_string()`), one that translates (for the
site).

```php
function mytheme_option_raw( string $key ): string {
	return (string) get_theme_mod( $key, '' );
}

function mytheme_option( string $key ): string {
	$value = mytheme_option_raw( $key );
	if ( ! is_admin() && function_exists( 'blocklingo_translate' ) ) {
		return blocklingo_translate( $value, 'My Theme' );
	}
	return $value;
}
```

### No fixed language codes

Print the language of the page, not a fixed one: `get_locale()` (`fr_FR`) or
`get_bloginfo( 'language' )` (`fr-FR`) follow the page with Blocklingo. A hard-coded
`og:locale` of `en_US` would tell search engines every language is English.

### A language switcher

Point users to the **Language Switcher block** for headers and footers. In a classic theme you can
print one yourself:

```php
if ( function_exists( 'blocklingo_languages' ) ) {
	foreach ( blocklingo_languages() as $code => $language ) {
		printf(
			'<a href="%s" hreflang="%s"%s>%s</a> ',
			esc_url( blocklingo_current_url( $code ) ),
			esc_attr( str_replace( '_', '-', $language['locale'] ) ),
			$code === blocklingo_current_language() ? ' aria-current="true"' : '',
			esc_html( $language['name'] )
		);
	}
}
```

### Building URLs yourself

Use WordPress's functions (`get_permalink()`, `home_url( '/' )`, `get_term_link()`): they return
the address in the current language. For a URL you build from a string, use
`blocklingo_localize_url( $url )`.

## Not supported (yet)

- **Classic widgets** (text widgets and widget titles in the old widgets screen) and **block
  widgets** in Appearance → Widgets: the widgets screen has no Translate sidebar. Put translatable
  content in a template part or synced pattern, or in blocks on the page.
- **Customizer fields** of themes are only translated when the theme registers them as above.

## Checklist for testing a theme

1. Add two languages under Settings → Blocklingo and visit `/xx/` (the second language's prefix).
2. Header and footer: menus, site title, tagline, buttons and texts in that language? Links stay in
   that language?
3. A post: title, content, categories and tags links, previous/next links.
4. An archive and page 2 of it, the search results, the 404 page.
5. View source: `<html lang>`, `og:locale` (if the theme prints it), `hreflang` links.
6. Switch the plugin off: everything as before?

## Example

The [Kalm theme](https://github.com/RenautMestdagh/kalm) (a block theme) needed nothing: its
texts are in blocks and patterns, and its header and mobile menu show the language switcher when
Blocklingo is active. The [Site Details](https://github.com/RenautMestdagh/site-details) plugin,
which shows the business details in Kalm, registers them with `blocklingo_register_string()` and
shows them through `blocklingo_translate()`, keeping the untranslated value for its settings screen,
and takes `og:locale` from `get_locale()`.
