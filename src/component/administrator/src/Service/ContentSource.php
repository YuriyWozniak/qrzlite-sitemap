<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

/**
 * Reads menu items, article categories, articles and content languages from the site database.
 */
final class ContentSource
{
    public function __construct(private readonly DatabaseInterface $db)
    {
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
     * All articles except trashed ones.
     *
     * @return  array<int, array<string, mixed>>
     */
    public function articles(): array
    {
        $db    = $this->db;
        $query = $this->query()
            ->select($db->quoteName(
                ['id', 'title', 'alias', 'catid', 'state', 'access', 'publish_up', 'publish_down', 'created', 'modified', 'metadata', 'language']
            ))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' <> -2')
            ->order($db->quoteName('id'));

        return $db->setQuery($query)->loadAssocList();
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
        // createQuery() replaces getQuery(true) since Joomla 5.
        return method_exists($this->db, 'createQuery') ? $this->db->createQuery() : $this->db->getQuery(true);
    }
}
