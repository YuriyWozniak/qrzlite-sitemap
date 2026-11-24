<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use QrzLite\Component\Sitemap\Administrator\Service\Generator;

/**
 * Status of the sitemap: files, number of links, what the last generation left out.
 */
class HtmlView extends BaseHtmlView
{
    /** @var  string  Site URL, or the reason it is not known. */
    protected string $siteUrl = '';

    protected string $siteUrlError = '';

    /** @var  array<int, array{name: string, url: string, size: int, time: int}> */
    protected array $files = [];

    protected int $links = 0;

    /** @var  array<int, string[]> */
    protected array $exclusions = [];

    public function display($tpl = null): void
    {
        $generator = Generator::create();

        try {
            $this->siteUrl = $generator->siteUrl();
        } catch (\RuntimeException $e) {
            $this->siteUrlError = $e->getMessage();
        }

        $name  = $generator->filename();
        $paths = array_merge([JPATH_ROOT . '/' . $name . '.xml'], glob(JPATH_ROOT . '/' . $name . '_*.xml') ?: []);

        foreach ($paths as $path) {
            if (is_file($path)) {
                $this->files[] = [
                    'name' => basename($path),
                    'url'  => $this->siteUrl !== '' ? $this->siteUrl . '/' . basename($path) : '',
                    'size' => (int) filesize($path),
                    'time' => (int) filemtime($path),
                ];
            }
        }

        $db          = Factory::getContainer()->get(DatabaseInterface::class);
        $this->links = (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName(Generator::TABLE))->loadResult();

        $app = Factory::getApplication();

        if ($app instanceof CMSWebApplicationInterface) {
            $this->exclusions = (array) $app->getUserState(Generator::COMPONENT . '.exclusions', []);
        }

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

        if ($this->getCurrentUser()->authorise('core.admin', Generator::COMPONENT)) {
            $toolbar->preferences(Generator::COMPONENT);
        }
    }
}
