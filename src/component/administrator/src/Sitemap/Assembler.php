<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * Routes the candidates and builds the final list of URLs:
 *  - candidates that route to the same URL are merged (the menu item's title wins, the latest change wins);
 *  - a URL of a noindex page is removed even when another candidate routes to it;
 *  - URLs matching the path filter are left out;
 *  - the site root "/" is always listed, home pages are never filtered;
 *  - the result is sorted by path.
 */
final class Assembler
{
    /** Title priority when candidates share a URL. */
    private const TITLE_PRIORITY = [
        Candidate::TYPE_MENU     => 3,
        Candidate::TYPE_CATEGORY => 2,
        Candidate::TYPE_ARTICLE  => 1,
    ];

    /** @var \Closure(string): ?string */
    private \Closure $router;

    /**
     * @param   callable(string): ?string  $router     Turns a non-SEF route into a path relative to the site root
     *                                                 ("/news.html"); null or '' when it can't be routed.
     * @param   PathFilter                 $filter     Path filter.
     * @param   string                     $homeTitle  Title of the site root (the site name).
     */
    public function __construct(callable $router, private readonly PathFilter $filter, private readonly string $homeTitle)
    {
        $this->router = \Closure::fromCallable($router);
    }

    /**
     * @param   iterable<Candidate>  $candidates
     */
    public function assemble(iterable $candidates): Assembly
    {
        // path => [title, title priority, lastmod]
        $urls       = ['/' => [$this->homeTitle, 0, null]];
        $blocked    = [];
        $exclusions = [];

        foreach ($candidates as $candidate) {
            if (!$candidate->isIncluded() && !$candidate->blocks) {
                $exclusions[] = [$candidate->describe(), $candidate->excluded];
                continue;
            }

            try {
                $path = self::normalize(($this->router)($candidate->route));
            } catch (\Throwable $e) {
                $exclusions[] = [$candidate->describe(), 'routing failed: ' . $e->getMessage()];
                continue;
            }

            if ($path === null) {
                $exclusions[] = [$candidate->describe(), 'no URL'];
                continue;
            }

            if ($candidate->blocks) {
                $blocked[$path] = true;
                $exclusions[]   = [$candidate->describe() . ' ' . $path, $candidate->excluded];
                continue;
            }

            if (!$candidate->home && $path !== '/' && ($pattern = $this->filter->match($path)) !== null) {
                $exclusions[] = [$candidate->describe() . ' ' . $path, 'filter "' . $pattern . '"'];
                continue;
            }

            $priority = self::TITLE_PRIORITY[$candidate->type] ?? 0;
            $current  = $urls[$path] ?? ['', -1, null];

            if ($candidate->title !== '' && ($priority > $current[1] || $current[0] === '')) {
                $current[0] = $candidate->title;
                $current[1] = $priority;
            }

            $current[2]   = Rules::latest($current[2], $candidate->lastmod);
            $urls[$path] = $current;
        }

        foreach (array_keys($blocked) as $path) {
            if ($path !== '/') {
                unset($urls[$path]);
            }
        }

        uksort($urls, 'strcmp');

        $entries = [];

        foreach ($urls as $path => [$title, , $lastmod]) {
            $entries[] = new Entry((string) $path, $title, $lastmod);
        }

        return new Assembly($entries, $exclusions);
    }

    /**
     * Make sure the path starts with exactly one "/"; null for an empty or absolute (external) URL.
     */
    private static function normalize(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '' || preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $path)) {
            return null;
        }

        return '/' . ltrim($path, '/');
    }
}
