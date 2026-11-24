# Changelog

## 1.0.0 - 2026-11

First version.

- XML sitemap of menu items, article categories and articles, valid against the official schema, split above
  50,000 URLs.
- HTML sitemap page (menu item type), optional XSL browser view in English and Ukrainian.
- Generation from the administrator, the Joomla console (`qrzlite-sitemap:generate`) and the Joomla Scheduler.
- Correct addresses from the console and the scheduler, language prefixes on multilingual sites.
- Atomic file writing, with an in-place fallback for read-only site roots.
- Options saved empty stay empty: *None* for `changefreq` and `priority` leaves them out, an emptied URL filter
  filters nothing. A `--options` file can turn options off with `""`.
- Unit tests for the whole component against Joomla 5.4 and 6.2; PHP, HTML and CSS linters.
- Performance tests and a benchmark of CPU time and memory, also on a real Joomla site with 100,000 articles.
