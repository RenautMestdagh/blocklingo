=== Blocklingo ===
Contributors: renautmestdagh
Tags: multilingual, translation, language switcher, hreflang, block editor
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multilingual WordPress, block by block: translate pages, templates, headers, footers and menus in the block editor, with translated URLs.

== Description ==

Blocklingo makes a WordPress site multilingual without duplicating pages. You write content once, in your default language, and translate it block by block, right where it is, in the block editor.

**Free, completely.** No Pro version, no limit on languages, no account, no external service needed.

* **Translate in the block editor**: choose a language in the Translate sidebar and type the translation into the blocks themselves, in the same layout. Blocks still to translate are faded; blocks whose original changed after translating are marked as outdated. The same works in the Site Editor, for headers, footers, templates and menus.
* **Blocks from any plugin**: every block with text content can be translated, not only WordPress's own.
* **Classic themes too**: menus from Appearance → Menus get a label and address per language.
* **Any number of languages**, configured under Settings → Blocklingo. Turn a language off to hide it without losing its translations.
* **Translated addresses**: every page and post gets its own slug per language (`/fr/tarifs/`, `/en/rates/`), parents included. Links inside your content follow the language of the page.
* **Language prefixes** in the URL for every language, optionally also for the default one (`/en/` …). Old addresses are redirected permanently.
* **Search engines**: `hreflang` links for every language plus `x-default`, the right `lang` on the page, and every translated address in WordPress's own sitemap.
* **WordPress itself in the visitor's language**: its own texts (dates, buttons, form messages) follow the language of the page; translations are installed when you add a language.
* **Language Switcher block** for your header or footer, linking to the same page in the other languages: as a horizontal or vertical list, or as a dropdown.
* **Texts outside blocks**: the site title, the tagline and texts of themes and plugins that support Blocklingo (or WPML's string functions) are translated under Settings → Blocklingo → Strings.
* **Contact Form 7**: labels, placeholders, messages and the auto-reply in every language; messages after sending come back in the language of the page.
* **Optional machine translation**: with your own DeepL API key, fill in all untranslated blocks of a page with one click, then check and adjust.
* **Change the default language later**: translations become the content and the content becomes a translation, with a dry run first and WP-CLI for large sites.
* **No lock-in**: translations are stored with the blocks they belong to. Remove the plugin and your site is your original content again.

= For developers =

* `blocklingo_current_language()`, `blocklingo_default_language()`, `blocklingo_languages()`, `blocklingo_current_url( $code )`, `blocklingo_localize_url( $url, $code )`.
* `blocklingo_register_string( $text, $group, $label, $multiline )` and `blocklingo_translate( $text, $group )` for texts outside blocks.
* WPML's hooks (`wpml_translate_single_string`, `wpml_register_single_string`, `wpml_current_language`, `wpml_active_languages`…) are answered when WPML is not active.
* Filters: `blocklingo_post_types`, `blocklingo_print_hreflang`, `blocklingo_text_attributes`, `blocklingo_strings`, `blocklingo_wpml_compat`.
* Development happens on [GitHub](https://github.com/RenautMestdagh/blocklingo). The editor scripts in `build/` are compiled from the readable source in `src/` there (`npm run build`).

== Installation ==

1. Install and activate Blocklingo.
2. Go to Settings → Blocklingo, add your languages and choose the default one: the language your content is written in.
3. Add the Language Switcher block to your header (Appearance → Editor).
4. Open a page, open the Translate sidebar (the translation icon at the top), choose a language and type the translations into the blocks. Set the translated title and address in the same sidebar.

Your site needs pretty permalinks (Settings → Permalinks) for language prefixes; with plain permalinks Blocklingo adds `?lang=fr` instead.

== Theme compatibility ==

**Block themes** (Twenty Twenty-Five and most new themes): everything that is made of blocks is translated in the editor and the Site Editor: pages, templates, headers, footers, menus (Navigation block) and patterns.

**Classic themes**: page and post content, titles and addresses are translated in the editor; menus (Appearance → Menus) get a label, title attribute and address per language for every item.

**Any theme**: links to posts, pages, categories, tags and archives follow the language of the page; `<html lang>` and `hreflang` are automatic. The theme's own texts appear in the visitor's language when the theme comes with translation files (most themes do, through translate.wordpress.org). Texts of themes and plugins written for WPML are translated too.

**What a theme can't get automatically**: texts it prints from its own settings (Customizer fields, an options page) without translation files. Themes can register those for Settings → Blocklingo → Strings with two functions; the guide for theme authors is at https://github.com/RenautMestdagh/blocklingo/blob/main/docs/themes.md

**Not supported yet**: widgets in Appearance → Widgets (classic or block widgets). Put translatable content in a template part, a synced pattern or the page itself.

== Frequently Asked Questions ==

= Does it work with my theme? =

Most likely, without changes. See Theme compatibility below.

= Can I change the default language later? =

Yes. Under Settings → Blocklingo, "Change the default language" turns the translations into the content and the current content into a translation, for pages, posts, templates, menus and patterns; titles and addresses too. A dry run first shows how many blocks have no translation (they keep their text). Back up your database first. For large sites: `wp blocklingo switch-default fr`.

Simply choosing another default language changes nothing in your content: it is then treated as written in that language.

= What happens to my content when I remove the plugin? =

Nothing: your pages stay as they are, in your default language. Deleting the plugin removes its settings and the translated titles and addresses.

= Does it send my content anywhere? =

Not unless you ask it to. Blocklingo works entirely within your site. When you save a new language, WordPress downloads its own translation files from WordPress.org, as it does when you change the site language. Machine translation is off until you add a DeepL API key; see External services.

== External services ==

Blocklingo can use **DeepL** (https://www.deepl.com) to translate texts, only when you add your own DeepL API key under Settings → Blocklingo and then press "Translate empty blocks with DeepL" in the editor.

* What is sent: the texts of the blocks on that page that have no translation yet (including their formatting markup), the default language and the target language, and your API key.
* When: only when you press the button, from your site's server; never automatically and never from visitors' browsers.
* Where: api-free.deepl.com (free keys) or api.deepl.com (paid keys).

DeepL's terms: https://www.deepl.com/pro-license and privacy policy: https://www.deepl.com/privacy

== Screenshots ==

1. Translating in the block editor: the same page and layout in French, a block still to translate faded, progress, and the French title and address.
2. Settings → Blocklingo: languages, their URL prefixes and WordPress languages, addresses and optional machine translation.
3. Settings → Blocklingo → Strings: the site title, tagline and texts of themes and plugins in every language.

== Changelog ==

= Unreleased =
* Tables can be translated cell by cell, like any other text (WordPress 6.9 and later: earlier versions don't mark table cells as content).
* Numbers, prices and codes without words no longer count as untranslated.
* Typing a translation also works in blocks that update their attributes through a function, like the table in WordPress 7.
* Language Switcher: show it as a dropdown (for headers) or as a vertical list.
* Links can differ per language: while translating, a button's, menu item's, social icon's, image's or file's link is stored for that language only. Links don't count as untranslated.

= 0.2.0 =
* Classic themes: menus from Appearance → Menus get a label, title attribute and address per language.
* Links to categories, tags, archives, search and page 2 of lists follow the language of the page.
* Theme compatibility section and a guide for theme authors.
* The editor script is no longer loaded on the widgets screen.
* Language packs are only downloaded when you save the settings screen, never when settings change from code.

= 0.1.0 =
* First version: languages, language prefixes, translated slugs, hreflang, Language Switcher block, translating blocks in the editor, strings for themes and plugins, sitemap, Contact Form 7, optional DeepL.
