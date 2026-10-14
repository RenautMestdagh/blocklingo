# Blocklingo

Multilingual WordPress, block by block. Write content once in your default language and translate
it block by block in the block editor, including templates, headers, footers and menus in the Site
Editor. Translated URLs, `hreflang`, a language switcher block. Free, GPL, no external service.

User documentation: [readme.txt](readme.txt) (shown on WordPress.org).

> Status: early development. Done: languages, URL prefixes, translated slugs, link localisation,
> hreflang, Language Switcher block, translating blocks in the editor, strings for themes and
> plugins, WPML hooks, sitemap, Contact Form 7, optional DeepL, switching the default language.
> Next: WordPress.org release.

## How it works

| Part | Where |
|---|---|
| Languages and settings (Settings → Blocklingo) | `includes/class-languages.php`, `includes/class-settings-page.php` |
| Language of a request (`/fr/…`), prefix hidden while WordPress parses the URL | `includes/class-request.php` |
| Translated title, slug, excerpt per post (meta `blocklingo`, lookup rows `_blocklingo_slug`) | `includes/class-posts.php` |
| Permalinks and links in content in the right language | `includes/class-links.php` |
| Translated slug → post, redirects to the one real address | `includes/class-router.php` |
| `hreflang`, URL of the current page in another language | `includes/class-seo.php` |
| Language Switcher block (source in `src/`, built to `build/`) | `includes/class-switcher-block.php` |
| Translations in blocks (attribute `blocklingo`: `{ "fr": { "content": [ original, translation ] } }`), shown on the site | `includes/class-blocks.php` |
| Translating in the editor: language switch, in-place editing, status per block | `src/editor/`, `includes/class-editor.php` |
| Texts outside blocks (site title, theme and plugin strings), Settings → Blocklingo → Strings | `includes/class-strings.php`, `includes/class-strings-page.php` |
| WPML's hooks for themes and plugins written for WPML | `includes/class-wpml-compat.php` |
| One sitemap per other language | `includes/class-sitemap.php`, `includes/class-sitemap-provider.php` |
| Contact Form 7 (form, messages, auto-reply; language of REST submissions) | `includes/class-contact-form-7.php` |
| Optional DeepL (REST `blocklingo/v1/translate`, key only on the server) | `includes/class-machine-translation.php` |
| Changing the default language (swap originals and translations, resumable), `wp blocklingo switch-default` | `includes/class-default-language.php`, `includes/class-cli.php` |
| Functions for themes and plugins | `includes/functions.php` |

### How a block is translated

A block keeps its own attributes in the default language, so it saves exactly as without the
plugin. Translations are an extra attribute: per language and attribute, the original as it was
when translated and the translation. In the editor (`src/editor/block-edit.js`) a block shows its
translations while another language is selected, and typing writes into the translation; adding,
moving or styling blocks is shared by all languages. On the site (`includes/class-blocks.php`) the
stored original is swapped for the translation in the block's own markup, as the whole content of an
element or as a text attribute (`alt`, `title`…), or in its comment attributes for blocks rendered
on the server. When the original has changed since, nothing matches and the current original shows,
while the editor marks the block as outdated. Translatable attributes are found from block types:
attributes with `role: "content"` holding text, so blocks from other plugins work too.

## Development

Requirements: [DDEV](https://ddev.com) (Docker), or PHP 7.4+ with Composer and Node (version in `.nvmrc`).

```sh
ddev start                 # WordPress at https://blocklingo.ddev.site (admin / admin), plugin active, 3 languages
ddev composer install
ddev npm ci
ddev npm run build         # or: ddev npm start (rebuilds on change)
```

| Command | What |
|---|---|
| `ddev composer test` | PHPUnit with the WordPress test suite (database `db_tests`) |
| `ddev composer lint` / `ddev composer fix` | PHP: WordPress Coding Standards, PHP 7.4+ compatibility |
| `ddev npm test` | JS unit tests (Vitest, through `wp-scripts test-unit-js`) |
| `ddev npm run lint` / `ddev npm run format` | JS and CSS: WordPress ESLint, Stylelint and Prettier configs |
| `ddev npm run plugin-zip` | Zip of the plugin as it ships |

GitHub Actions run on every push and pull request: **Lint** (PHPCS, PHP syntax 7.4 to 8.5, ESLint,
Stylelint, JS unit tests, build), **Tests** (PHPUnit on every PHP version from 7.4 to 8.5 against WordPress 6.6, 6.9 and the latest), **Plugin Check**
(the checks WordPress.org runs) and, on `main`, **Format** (fixes style and commits it).

## License

GPL-2.0-or-later.
