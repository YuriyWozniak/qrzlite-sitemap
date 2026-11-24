<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Storage;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use QrzLite\Component\Sitemap\Administrator\Joomla\Queries;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Entry;

/**
 * The links of the HTML sitemap page: saved by each generation, read a page at a time by the site.
 * The only class that knows the table.
 */
final class LinkRepository
{
    public const TABLE = '#__qrzlitesitemap_links';

    /** Rows per INSERT statement. */
    private const BATCH = 200;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Replace the saved links with these, all or nothing.
     *
     * @param   Entry[]  $entries
     *
     * @throws  \Throwable  The old links are kept.
     */
    public function replace(array $entries): void
    {
        $db = $this->db;
        $db->transactionStart();

        try {
            $db->setQuery($this->query()->delete($db->quoteName(self::TABLE)))->execute();

            foreach (array_chunk($entries, self::BATCH) as $chunk) {
                $query = $this->query()
                    ->insert($db->quoteName(self::TABLE))
                    ->columns($db->quoteName(['path', 'title', 'level', 'lastmod']));

                foreach ($chunk as $entry) {
                    $query->values(implode(',', [
                        $db->quote(mb_substr($entry->path, 0, 2048)),
                        $db->quote(mb_substr($entry->title, 0, 400)),
                        $entry->level(),
                        $entry->lastmod === null ? 'NULL' : $db->quote($entry->lastmod->format('Y-m-d H:i:s')),
                    ]));
                }

                $db->setQuery($query)->execute();
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            throw $e;
        }
    }

    public function count(): int
    {
        $query = $this->query()
            ->select('COUNT(*)')
            ->from($this->db->quoteName(self::TABLE));

        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * All links in the order they were saved (by path), for a paged list: path, title, level, lastmod.
     */
    public function listQuery(): QueryInterface
    {
        $db = $this->db;

        return $this->query()
            ->select($db->quoteName(['path', 'title', 'level', 'lastmod']))
            ->from($db->quoteName(self::TABLE))
            ->order($db->quoteName('id'));
    }

    private function query(): QueryInterface
    {
        return Queries::create($this->db);
    }
}
