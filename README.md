# Blocklingo

Multilingual WordPress, block by block. Write content once in your default language and translate
it block by block in the block editor, including templates, headers, footers and menus in the Site
Editor. Translated URLs, `hreflang`, a language switcher block. Free, GPL, no external service.

User documentation: [readme.txt](readme.txt) (shown on WordPress.org).

> Status: early development. Done: languages, URL prefixes, translated slugs, link localisation,
> hreflang, Language Switcher block. Next: translating blocks in the editor.

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
| Functions for themes and plugins | `includes/functions.php` |

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
| `ddev npm run lint` / `ddev npm run format` | JS and CSS: WordPress ESLint, Stylelint and Prettier configs |
| `ddev npm run plugin-zip` | Zip of the plugin as it ships |

GitHub Actions run on every push and pull request: **Lint** (PHPCS, PHP syntax 7.4 to 8.5, ESLint,
Stylelint, build), **Tests** (PHPUnit on every PHP version from 7.4 to 8.5 against WordPress 6.6, 6.9 and the latest), **Plugin Check**
(the checks WordPress.org runs) and, on `main`, **Format** (fixes style and commits it).

## License

GPL-2.0-or-later.
