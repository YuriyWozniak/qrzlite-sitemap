<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;
use QrzLite\Component\Sitemap\Administrator\Storage\LinkRepository;

/**
 * Links saved by the last generation, a page at a time.
 */
class HtmlModel extends ListModel
{
    protected function populateState($ordering = null, $direction = null): void
    {
        $app    = Factory::getApplication();
        $params = $app instanceof SiteApplication ? $app->getParams() : new Registry();

        $this->setState('params', $params);
        $this->setState('list.limit', max(1, (int) $params->get('html_limit', 200)));
        $this->setState('list.start', $app->getInput()->getUint('limitstart', 0));
    }

    protected function getListQuery(): QueryInterface
    {
        return (new LinkRepository($this->getDatabase()))->listQuery();
    }
}
