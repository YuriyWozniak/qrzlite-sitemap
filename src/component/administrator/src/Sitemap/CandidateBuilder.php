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
 * Articles are turned into candidates one at a time, as they are read, so that a large site never has all of
 * them in memory. Only what categories and menu items need of them is kept: the last change of each category
 * and the articles that menu items open.
 */
final class CandidateBuilder
{
    private CategoryTree $tree;

    /** @var array<int, true> Ids of the articles that menu items open. */
    private array $opened = [];

    /** @var array<int, Candidate> Candidates of the articles that menu items open. */
    private array $targets = [];

    /** @var array<int, \DateTimeImmutable> Last change of the listed articles of each category. */
    private array $latest = [];

    public function __construct(private readonly Context $context)
    {
    }

    /**
     * All candidates: articles first, as they come, then categories, then menu items. Categories take their
     * last change from their articles, and menu items that open an article or a category check that the target
     * is listed, so they can only follow the articles.
     *
     * @param   iterable<array<string, mixed>>    $articles    id, title, alias, catid, state, access, publish_up,
     *                                                         publish_down, created, modified, metadata, language
     * @param   array<int, array<string, mixed>>  $categories  All article categories: id, parent_id, title,
     *                                                         published, access, language, metadata,
     *                                                         created_time, modified_time
     * @param   array<int, array<string, mixed>>  $menuItems   Published site menu items: id, title, link, type,
     *                                                         home, access, language, params, publish_up,
     *                                                         publish_down, component, component_enabled
     *
     * @return  \Generator<int, Candidate>
     */
    public function candidates(iterable $articles, array $categories, array $menuItems): \Generator
    {
        $this->tree    = new CategoryTree($categories, $this->context);
        $this->opened  = [];
        $this->targets = [];
        $this->latest  = [];

        foreach ($menuItems as $row) {
            $query = self::linkQuery((string) ($row['link'] ?? ''));

            if ($query !== null && ($query['view'] ?? '') === 'article') {
                $this->opened[(int) $query['id']] = true;
            }
        }

        foreach ($articles as $row) {
            yield $this->article($row);
        }

        foreach ($categories as $row) {
            yield $this->category($row);
        }

        foreach ($menuItems as $row) {
            yield $this->menuItem($row);
        }
    }

    /**
     * All candidates at once, by kind.
     *
     * @param   iterable<array<string, mixed>>    $articles
     * @param   array<int, array<string, mixed>>  $categories
     * @param   array<int, array<string, mixed>>  $menuItems
     *
     * @see     candidates()
     */
    public function build(iterable $articles, array $categories, array $menuItems): Candidates
    {
        $kinds = [Candidate::TYPE_MENU => [], Candidate::TYPE_CATEGORY => [], Candidate::TYPE_ARTICLE => []];

        foreach ($this->candidates($articles, $categories, $menuItems) as $candidate) {
            $kinds[$candidate->type][] = $candidate;
        }

        return new Candidates($kinds[Candidate::TYPE_MENU], $kinds[Candidate::TYPE_CATEGORY], $kinds[Candidate::TYPE_ARTICLE]);
    }

    /**
     * @param   array<string, mixed>  $row
     */
    private function article(array $row): Candidate
    {
        $id       = (int) $row['id'];
        $catid    = (int) $row['catid'];
        $alias    = (string) ($row['alias'] ?? '');
        $language = (string) ($row['language'] ?? '*');

        $reason = Rules::state((int) $row['state'], $this->context)
            ?? Rules::window($row['publish_up'] ?? null, $row['publish_down'] ?? null, $this->context->now)
            ?? Rules::access((int) $row['access'], $this->context)
            ?? Rules::language($language, $this->context)
            ?? $this->tree->reason($catid);
        $noindex = $reason === null ? Rules::noindex(Rules::robots($row['metadata'] ?? ''), $this->context->siteRobots) : null;

        $candidate = new Candidate(
            Candidate::TYPE_ARTICLE,
            $id,
            (string) $row['title'],
            'index.php?option=com_content&view=article&id=' . $id . ($alias !== '' ? ':' . $alias : '')
                . '&catid=' . $catid . $this->context->languageSuffix($language),
            Rules::date($row['modified'] ?? null) ?? Rules::date($row['created'] ?? null),
            $reason ?? $noindex,
            $noindex !== null
        );

        // A category changes when an article listed in it changes.
        if ($candidate->isIncluded() && $candidate->lastmod !== null) {
            $this->latest[$catid] = Rules::latest($this->latest[$catid] ?? null, $candidate->lastmod);
        }

        if (isset($this->opened[$id])) {
            $this->targets[$id] = $candidate;
        }

        return $candidate;
    }

    /**
     * Parents and state of the categories are taken from the category tree.
     *
     * @param   array<string, mixed>  $row
     */
    private function category(array $row): Candidate
    {
        $id      = (int) $row['id'];
        $reason  = $this->tree->reason($id);
        $noindex = $reason === null ? Rules::noindex(Rules::robots($row['metadata'] ?? ''), $this->context->siteRobots) : null;
        $own     = Rules::date($row['modified_time'] ?? null) ?? Rules::date($row['created_time'] ?? null);

        return new Candidate(
            Candidate::TYPE_CATEGORY,
            $id,
            (string) $row['title'],
            'index.php?option=com_content&view=category&id=' . $id
                . $this->context->languageSuffix((string) ($row['language'] ?? '*')),
            Rules::latest($own, $this->latest[$id] ?? null),
            $reason ?? $noindex,
            $noindex !== null
        );
    }

    /**
     * @param   array<string, mixed>  $row
     */
    private function menuItem(array $row): Candidate
    {
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

        return new Candidate(
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

    /**
     * @param   array<string, mixed>  $row
     */
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
        $query = self::linkQuery($link);

        if ($query === null) {
            return null;
        }

        $id = (int) $query['id'];

        switch ($query['view'] ?? '') {
            case 'article':
                $target = $this->targets[$id] ?? null;

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

    /**
     * Query of a menu item link that opens something of com_content by id; null for any other link.
     *
     * @return  ?array<string, mixed>
     */
    private static function linkQuery(string $link): ?array
    {
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        if (($query['option'] ?? '') !== 'com_content' || !isset($query['id']) || !\is_scalar($query['id'])) {
            return null;
        }

        return $query;
    }
}
