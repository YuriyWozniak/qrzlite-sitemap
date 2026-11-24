<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * Turns database rows of articles, categories and menu items into candidates and decides, for each one,
 * whether a guest can see it and whether search engines may index it.
 *
 * Call articles() first, then categories() and menuItems(): categories take their last change from their
 * articles, and menu items that open an article or a category check that the target is listed.
 */
final class CandidateBuilder
{
    /** @var array<int, Candidate> */
    private array $articles = [];

    /** @var array<int, int> Category id of each article. */
    private array $catids = [];

    /** @var array<int, Candidate> */
    private array $categories = [];

    public function __construct(private readonly Context $context, private readonly CategoryTree $tree)
    {
    }

    /**
     * @param   iterable<array<string, mixed>>  $rows  id, title, alias, catid, state, access, publish_up,
     *                                                 publish_down, created, modified, metadata, language
     *
     * @return  Candidate[]
     */
    public function articles(iterable $rows): array
    {
        $this->articles = [];
        $this->catids   = [];

        foreach ($rows as $row) {
            $id       = (int) $row['id'];
            $catid    = (int) $row['catid'];

            $this->catids[$id] = $catid;

            $alias    = (string) ($row['alias'] ?? '');
            $language = (string) ($row['language'] ?? '*');
            $category = $this->tree->reason($catid);

            $reason = Rules::state((int) $row['state'], $this->context)
                ?? Rules::window($row['publish_up'] ?? null, $row['publish_down'] ?? null, $this->context->now)
                ?? Rules::access((int) $row['access'], $this->context)
                ?? Rules::language($language, $this->context)
                ?? $category;
            $noindex = $reason === null ? Rules::noindex(Rules::robots($row['metadata'] ?? ''), $this->context->siteRobots) : null;

            $this->articles[$id] = new Candidate(
                Candidate::TYPE_ARTICLE,
                $id,
                (string) $row['title'],
                'index.php?option=com_content&view=article&id=' . $id . ($alias !== '' ? ':' . $alias : '')
                    . '&catid=' . $catid . $this->context->languageSuffix($language),
                Rules::date($row['modified'] ?? null) ?? Rules::date($row['created'] ?? null),
                $reason ?? $noindex,
                $noindex !== null
            );
        }

        return array_values($this->articles);
    }

    /**
     * @param   iterable<array<string, mixed>>  $rows  id, title, metadata, language, created_time, modified_time
     *                                                 (parents and state are taken from the category tree)
     *
     * @return  Candidate[]
     */
    public function categories(iterable $rows): array
    {
        // A category changes when an article listed in it changes.
        $latest = [];

        foreach ($this->articles as $id => $article) {
            if ($article->isIncluded()) {
                $catid          = $this->catids[$id];
                $latest[$catid] = Rules::latest($latest[$catid] ?? null, $article->lastmod);
            }
        }

        $this->categories = [];

        foreach ($rows as $row) {
            $id      = (int) $row['id'];
            $reason  = $this->tree->reason($id);
            $noindex = $reason === null ? Rules::noindex(Rules::robots($row['metadata'] ?? ''), $this->context->siteRobots) : null;
            $own     = Rules::date($row['modified_time'] ?? null) ?? Rules::date($row['created_time'] ?? null);

            $this->categories[$id] = new Candidate(
                Candidate::TYPE_CATEGORY,
                $id,
                (string) $row['title'],
                'index.php?option=com_content&view=category&id=' . $id
                    . $this->context->languageSuffix((string) ($row['language'] ?? '*')),
                Rules::latest($own, $latest[$id] ?? null),
                $reason ?? $noindex,
                $noindex !== null
            );
        }

        return array_values($this->categories);
    }

    /**
     * @param   iterable<array<string, mixed>>  $rows  Published site menu items: id, title, link, type, home,
     *                                                 access, language, params, publish_up, publish_down,
     *                                                 component, component_enabled
     *
     * @return  Candidate[]
     */
    public function menuItems(iterable $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $id       = (int) $row['id'];
            $language = (string) ($row['language'] ?? '*');
            $home     = (int) ($row['home'] ?? 0) === 1;
            $reason   = null;
            $noindex  = null;

            // A home page is always listed.
            if (!$home) {
                $reason = $this->menuType($row)
                    ?? Rules::window($row['publish_up'] ?? null, $row['publish_down'] ?? null, $this->context->now)
                    ?? Rules::access((int) $row['access'], $this->context)
                    ?? Rules::language($language, $this->context)
                    ?? $this->menuTarget((string) $row['link']);
                $noindex = $reason === null ? Rules::noindex(Rules::robots($row['params'] ?? ''), $this->context->siteRobots) : null;
            }

            $items[] = new Candidate(
                Candidate::TYPE_MENU,
                $id,
                (string) $row['title'],
                'index.php?Itemid=' . $id . $this->context->languageSuffix($language),
                null,
                $reason ?? $noindex,
                $noindex !== null,
                $home
            );
        }

        return $items;
    }

    private function menuType(array $row): ?string
    {
        $type = (string) ($row['type'] ?? '');

        if ($type !== 'component') {
            return 'menu item type ' . ($type === '' ? '(none)' : $type);
        }

        if (empty($row['component'])) {
            return 'component not installed';
        }

        return (int) ($row['component_enabled'] ?? 0) === 1 ? null : 'component ' . $row['component'] . ' disabled';
    }

    /**
     * A menu item that opens one article or one category is listed only when that article or category can be
     * listed itself. Noindex targets are not handled here: their URL is blocked when they are routed.
     */
    private function menuTarget(string $link): ?string
    {
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        if (($query['option'] ?? '') !== 'com_content' || !isset($query['id']) || !is_scalar($query['id'])) {
            return null;
        }

        $id = (int) $query['id'];

        switch ($query['view'] ?? '') {
            case 'article':
                $target = $this->articles[$id] ?? null;

                if ($target === null) {
                    return 'article #' . $id . ' not found';
                }

                return $target->isIncluded() || $target->blocks ? null : 'article #' . $id . ' ' . $target->excluded;

            case 'category':
            case 'categories':
                if ($id <= 1 && $query['view'] === 'categories') {
                    // The list of all top-level categories.
                    return null;
                }

                return $this->tree->reason($id);
        }

        return null;
    }
}
