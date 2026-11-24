# QrzLite Sitemap

A lightweight sitemap for Joomla 5 and 6. It writes a static `sitemap.xml` into the site root and shows the same
links on an HTML sitemap page. It covers what most small sites need: menu items, article categories and articles.
There are no source plugins to install and nothing runs on ordinary page views.

## What it does

- **XML sitemap.** Writes `sitemap.xml` following the [Sitemaps protocol](https://www.sitemaps.org/protocol.html) and
  valid against its official schema. Above 50,000 URLs (or a lower limit you set) it writes `sitemap_1.xml`,
  `sitemap_2.xml`, … and a sitemap index in `sitemap.xml`.
- **Browser view.** An optional XSL stylesheet (English or Ukrainian, following the site language) shows the
  sitemap as a table when opened in a browser.
- **HTML sitemap page.** Add a menu item of type *QrzLite Sitemap → Sitemap (HTML page)*. The page lists the links
  of the last generation, indented by depth, with pagination.
- **Three ways to run it**, all with the same result:
  - the *Generate Now* button in *Components → QrzLite Sitemap*;
  - the Joomla Scheduler: add a task of type *Generate the sitemap* (*System → Scheduled Tasks*). It runs from the
    lazy scheduler, a web cron or `php cli/joomla.php scheduler:run`;
  - the console: `php cli/joomla.php qrzlite-sitemap:generate`.

  The sitemap is never generated from a public URL, so there is no access key to protect.

## Which pages are listed

A page is listed when a guest (a visitor who is not logged in) can open it and search engines may index it:

| Source | Listed when |
|---|---|
| Menu items | Published, inside their publishing dates, public, of type *component* with the component enabled, in a published content language. A menu item that opens one article or one category is listed only if that article or category can be listed. |
| Categories | The category and **every parent category** are published (or archived, if enabled) and public, in a published content language. |
| Articles | Published (or archived, if enabled), inside their publishing dates, public, in a published language, in a reachable category. |

- **noindex** in the robots setting of a menu item, a category or an article (or in the Global Configuration, when
  the item has none) removes that URL, even when another item leads to the same address.
- **Leave Out URLs Containing** removes URLs whose path contains any of the texts you list. The defaults are
  `/component/` and `?view=`, which catch pages that have no menu item.
- Items that lead to the same URL are merged: the menu item's title is used and the latest change wins.
- `lastmod` is the article's last change. For a category it is the later of the category's own change and the last
  change of its listed articles.
- The site root `/` is always listed. Home pages are never filtered out.
- **Multilingual sites** (*System - Language Filter* enabled) get language prefixes such as `/en/`. Items for all
  languages are routed in the default site language.

Every generation reports what was left out and why: on the dashboard after *Generate Now*, or with
`qrzlite-sitemap:generate -v`.

## Install

Download `pkg_qrzlitesitemap-<version>.zip` from the
[releases](https://github.com/YuriyWozniak/qrzlite-sitemap/releases) and install it in *System → Install →
Extensions*, or from the console:

```sh
php cli/joomla.php extension:install --url=https://github.com/YuriyWozniak/qrzlite-sitemap/releases/download/v1.0.0/pkg_qrzlitesitemap-1.0.0.zip
```

The package contains the component and two small plugins, *Console - QrzLite Sitemap* and *Task - QrzLite Sitemap*.
Both are turned on when the package is first installed. Add `Sitemap: https://example.com/sitemap.xml` to
`robots.txt`.

## Console

```sh
php cli/joomla.php qrzlite-sitemap:generate              # write sitemap.xml and save the HTML sitemap links
php cli/joomla.php qrzlite-sitemap:generate --dry-run -v # only show what would be listed and left out (-vv lists the URLs)
php cli/joomla.php qrzlite-sitemap:generate --output=/tmp/sitemap   # write the files elsewhere
```

The console needs the site URL. It is taken from the *Site URL* option, then from `$live_site` in
`configuration.php`, then from `--live-site=https://example.com`.

## Writing the file

When the site root is writable, the sitemap is written into a temporary file and renamed over the old one, so
readers never see a half-written file. When only `sitemap.xml` itself is writable (a read-only site root), it is
rewritten in place under a lock. An empty result never replaces an existing sitemap.

## Options

*Components → QrzLite Sitemap → Options*: file name, site URL, `changefreq` and `priority` (or leave them out),
browser view, URLs per file, which sources to list, archived content, URL filter and links per page of the HTML
sitemap.

## Requirements

Joomla 5 or 6, PHP 8.1 or newer (Joomla 6 itself needs 8.3), MySQL or MariaDB.

## Development

Every feature or fix goes in through a pull request from its own branch; see [CONTRIBUTING](CONTRIBUTING.md).

```sh
composer install
npm install          # only for the HTML and CSS linters
composer test        # unit tests
composer lint        # PHP: syntax (all PHP versions in CI), code style, static analysis
composer lint:html   # HTML and CSS of the rendered layouts and of the XSL browser view
composer fix         # fix the code style
composer coverage    # unit tests with a coverage report in .cache/coverage (needs pcov or Xdebug)
composer test:performance  # CPU time and memory of generation against budgets (PHP 8.2+)
composer benchmark   # CPU time and memory at 1,000 to 100,000 articles, in .cache/benchmark.md
composer build       # dist/pkg_qrzlitesitemap-<version>.zip
```

**Unit tests** cover the whole component: the sitemap rules and the XML writer
(`src/component/administrator/src/Sitemap`), the file writer and the publisher of the XML files (in `Storage`), none
of which need Joomla, and everything that runs inside Joomla: reading the content and routing (the adapters in
`Joomla`), the generator, the dashboard and its *Generate Now* button, the HTML sitemap page (controller,
model, view, layout, router), the console command, the scheduled task, the plugins' service providers and the
package installer script. Those run against the real Joomla libraries, which `composer test` fetches into
`.cache/joomla` (Joomla 5.4 by default; `JOOMLA_VERSION=6.2.1` for Joomla 6), with an SQLite database in memory.
CI runs them on PHP 8.1–8.5 with Joomla 5.4 and on PHP 8.3–8.5 with Joomla 6.2, and requires 98% statement coverage.

**Performance tests** (`tests/Performance`) measure CPU time and PHP memory per URL: a whole generation of a
20,000-article site through the component, and the core alone with 120,000 URLs. They fail when either goes over a
budget several times what a slow machine needs, or when the cost per URL grows with the size of the site or of a
file. Routing there is a cheap stand-in for Joomla's site router, so they measure the component's own cost;
`composer benchmark` reports the same at several sizes. In CI, `tests/Integration/benchmark.sh` measures the console
command on a real Joomla site with up to 100,000 articles, the site router included, and adds the table to the
job's summary.

**Linters**: PHP CS Fixer (PSR-12 with Joomla's additions), PHPStan level 6 against Joomla 5.4 and 6.2,
html-validate for the HTML that the layouts and the XSL stylesheets produce, and Stylelint for the stylesheets' CSS.
The component has no JavaScript.

The integration test installs the package into a fresh Joomla 5.4 and 6.2 site (GitHub Actions, MariaDB) with test
content that exercises every rule, then checks the sitemap from the console command and from a scheduled task, and
the HTML sitemap page: `tests/Integration/setup-content.php` and `tests/Integration/smoke.sh`.

`tests/Integration/run-on-site.php` runs the console command on an existing Joomla site from this source tree
without installing anything; use it with `--dry-run` or `--output` to compare the result with a site's current
sitemap.

## License

GNU General Public License version 2 or later, see [LICENSE](LICENSE).
