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
 * Makes new query objects on Joomla 5 and 6.
 */
final class Queries
{
    /**
     * createQuery() replaces getQuery(true), which is deprecated. The database drivers of Joomla 5 have it, but
     * its DatabaseInterface declares it only from Joomla 6 on.
     */
    public static function create(DatabaseInterface $db): QueryInterface
    {
        return method_exists($db, 'createQuery') ? $db->createQuery() : $db->getQuery(true);
    }
}
