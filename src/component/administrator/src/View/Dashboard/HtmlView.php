<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Model\DashboardModel;

/**
 * Status of the sitemap: files, number of links, what the last generation left out.
 */
class HtmlView extends BaseHtmlView
{
    protected string $siteUrl = '';

    /** @var  string  Why the site URL is not known. */
    protected string $siteUrlError = '';

    /** @var  array<int, array{name: string, url: string, size: int, time: int}> */
    protected array $files = [];

    protected int $links = 0;

    /** @var  array<int, string[]> */
    protected array $exclusions = [];

    public function display($tpl = null): void
    {
        /** @var DashboardModel $model */
        $model = $this->getModel();

        $this->siteUrl      = $model->getSiteUrl();
        $this->siteUrlError = $model->getSiteUrlError();
        $this->files        = $model->getFiles();
        $this->links        = $model->getLinks();
        $this->exclusions   = $model->getExclusions();

        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_QRZLITESITEMAP'), 'sitemap');

        $document = $this->getDocument();

        if (!$document instanceof HtmlDocument) {
            return;
        }

        $toolbar = $document->getToolbar();
        $toolbar->standardButton('refresh', 'COM_QRZLITESITEMAP_GENERATE', 'sitemap.generate')->listCheck(false);

        if ($this->getCurrentUser()->authorise('core.admin', SitemapComponent::NAME)) {
            $toolbar->preferences(SitemapComponent::NAME);
        }
    }
}
