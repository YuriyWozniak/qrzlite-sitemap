<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Joomla;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

/**
 * Reads menu items, article categories, articles and content languages from the site database.
 */
final class ContentSource
{
    /** Articles read with one query. */
    public const PAGE_SIZE = 1000;

    /**
     * @param   int  $pageSize  Articles read with one query.
     */
    public function __construct(private readonly DatabaseInterface $db, private readonly int $pageSize = self::PAGE_SIZE)
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('The page size must be at least 1.');
        }
    }

    /**
     * Published site menu items with the state of the component they open.
     *
     * @return  array<int, array<string, mixed>>
     */
    public function menuItems(): array
    {
        $db    = $this->db;
        $query = $this->query()
            ->select($db->quoteName(
                ['m.id', 'm.title', 'm.link', 'm.type', 'm.home', 'm.access', 'm.language', 'm.params',
                 'm.publish_up', 'm.publish_down', 'e.element', 'e.enabled'],
                [null, null, null, null, null, null, null, null, null, null, 'component', 'component_enabled']
            ))
            ->from($db->quoteName('#__menu', 'm'))
            ->join('LEFT', $db->quoteName('#__extensions', 'e'), $db->quoteName('e.extension_id') . ' = ' . $db->quoteName('m.component_id'))
            ->where($db->quoteName('m.client_id') . ' = 0')
            ->where($db->quoteName('m.published') . ' = 1')
            ->where($db->quoteName('m.id') . ' > 1')
            ->order($db->quoteName('m.lft'));

        return $db->setQuery($query)->loadAssocList();
    }

    /**
     * All article categories (any state: the parents decide whether a category is reachable).
     *
     * @return  array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        $db    = $this->db;
        $query = $this->query()
            ->select($db->quoteName(['id', 'parent_id', 'title', 'published', 'access', 'language', 'metadata', 'created_time', 'modified_time']))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->order($db->quoteName('lft'));

        return $db->setQuery($query)->loadAssocList();
    }

    /**
     * All articles except trashed ones, by id.
     *
     * They are read a page at a time, so that only one page is in memory: the database client keeps the whole
     * result of a query, and a large site has more articles than fit in a usual memory limit.
     *
     * @return  \Generator<int, array<string, mixed>>
     */
    public function articles(): \Generator
    {
        $db   = $this->db;
        $last = 0;

        do {
            $query = $this->query()
                ->select($db->quoteName(
                    ['id', 'title', 'alias', 'catid', 'state', 'access', 'publish_up', 'publish_down', 'created', 'modified', 'metadata', 'language']
                ))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('state') . ' <> -2')
                ->where($db->quoteName('id') . ' > ' . $last)
                ->order($db->quoteName('id'))
                ->setLimit($this->pageSize);

            $rows  = $db->setQuery($query)->loadAssocList();
            $count = \count($rows);

            foreach ($rows as $row) {
                $last = (int) $row['id'];

                yield $row;
            }

            unset($rows);
        } while ($count === $this->pageSize);
    }

    /**
     * Lang codes of the published content languages a guest may see.
     *
     * @param   int[]  $guestLevels
     *
     * @return  string[]
     */
    public function languages(array $guestLevels): array
    {
        $db     = $this->db;
        $levels = array_map('intval', array_merge([0], $guestLevels));
        $query  = $this->query()
            ->select($db->quoteName('lang_code'))
            ->from($db->quoteName('#__languages'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . implode(',', $levels) . ')');

        return $db->setQuery($query)->loadColumn();
    }

    private function query(): QueryInterface
    {
        return Queries::create($this->db);
    }
}
