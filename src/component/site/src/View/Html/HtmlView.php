<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Site\View\Html;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Registry\Registry;

/**
 * The HTML sitemap page.
 */
class HtmlView extends BaseHtmlView
{
    /** @var  object[] */
    protected array $items = [];

    protected ?Pagination $pagination = null;

    protected ?Registry $params = null;

    protected string $heading = '';

    public function display($tpl = null): void
    {
        /** @var \QrzLite\Component\Sitemap\Site\Model\HtmlModel $model */
        $model            = $this->getModel();
        $this->items      = $model->getItems() ?: [];
        $this->pagination = $model->getPagination();
        $this->params     = $model->getState('params');

        $app     = Factory::getApplication();
        $menu    = $app instanceof SiteApplication ? $app->getMenu()->getActive() : null;
        $current = $menu && ($menu->query['option'] ?? '') === 'com_qrzlitesitemap';
        $title   = $current ? $menu->title : Text::_('COM_QRZLITESITEMAP_SITEMAP');

        $this->heading = (string) ($this->params->get('page_heading') ?: $title);
        $this->setDocumentTitle((string) ($this->params->get('page_title') ?: $title));

        if ($this->params->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('robots')) {
            $this->getDocument()->setMetaData('robots', $this->params->get('robots'));
        }

        parent::display($tpl);
    }
}
