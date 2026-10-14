=== Blocklingo ===
Contributors: renautmestdagh
Tags: multilingual, translation, language switcher, hreflang, block editor
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multilingual WordPress, block by block: translate pages, templates, headers, footers and menus in the block editor, with translated URLs.

== Description ==

Blocklingo makes a WordPress site multilingual without duplicating pages. You write content once, in your default language, and translate it block by block, right where it is, in the block editor.

**Free, completely.** No Pro version, no limit on languages, no account, no external service.

* **Any number of languages**, configured under Settings → Blocklingo. Turn a language off to hide it without losing its translations.
* **Translated addresses**: every page and post gets its own slug per language (`/fr/tarifs/`, `/en/rates/`), parents included. Links inside your content follow the language of the page.
* **Language prefixes** in the URL for every language, optionally also for the default one (`/en/` …). Old addresses are redirected permanently.
* **Search engines**: `hreflang` links for every language plus `x-default`, and the right `lang` on the page.
* **WordPress itself in the visitor's language**: its own texts (dates, buttons, form messages) follow the language of the page; translations are installed when you add a language.
* **Language Switcher block** for your header or footer, linking to the same page in the other languages.
* **No lock-in**: translations are stored with the blocks they belong to. Remove the plugin and your site is your original content again.

* **Translate in the block editor**: choose a language in the Translate sidebar and type the translation into the blocks themselves, with the same layout. Blocks still to translate are faded; blocks whose original changed after translating are marked as outdated. Works in the Site Editor too, for headers, footers, templates and menus.
* **Blocks from any plugin**: every block with text content can be translated, not only WordPress's own.

Blocklingo is under active development.

= For developers =

* `blocklingo_current_language()`, `blocklingo_default_language()`, `blocklingo_languages()`, `blocklingo_current_url( $code )`, `blocklingo_localize_url( $url, $code )`.
* Filters: `blocklingo_post_types`, `blocklingo_print_hreflang`, `blocklingo_text_attributes`.
* Development happens on [GitHub](https://github.com/RenautMestdagh/blocklingo).

== Installation ==

1. Install and activate Blocklingo.
2. Go to Settings → Blocklingo, add your languages and choose the default one: the language your content is written in.
3. Add the Language Switcher block to your header (Appearance → Editor).
4. Open a page, open the Translate sidebar (the translation icon at the top), choose a language and type the translations into the blocks. Set the translated title and address in the same sidebar.

Your site needs pretty permalinks (Settings → Permalinks) for language prefixes; with plain permalinks Blocklingo adds `?lang=fr` instead.

== Frequently Asked Questions ==

= Does it work with my theme? =

Yes, with block themes and classic themes. Translating headers, footers and templates in the Site Editor needs a block theme.

= What happens to my content when I remove the plugin? =

Nothing: your pages stay as they are, in your default language. Deleting the plugin removes its settings and the translated titles and addresses.

= Does it send my content anywhere? =

No. Blocklingo works entirely within your site. When you save a new language, WordPress downloads its own translation files from WordPress.org, as it does when you change the site language.

== Changelog ==

= 0.1.0 =
* First version: languages, language prefixes, translated slugs, hreflang, Language Switcher block, translating blocks in the editor.
