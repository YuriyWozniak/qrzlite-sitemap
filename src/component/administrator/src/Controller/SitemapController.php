<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Service\Generator;

/**
 * The "Generate now" button of the dashboard.
 */
class SitemapController extends BaseController
{
    public function generate(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.manage', SitemapComponent::NAME)) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try {
            $result = Generator::create()->generate();

            if ($this->app instanceof CMSWebApplicationInterface) {
                $this->app->setUserState(SitemapComponent::NAME . '.exclusions', $result->exclusions);
            }

            $this->setMessage(Text::sprintf('COM_QRZLITESITEMAP_GENERATED', $result->count(), \count($result->exclusions)));
        } catch (\Throwable $e) {
            $this->setMessage(Text::sprintf('COM_QRZLITESITEMAP_GENERATION_FAILED', $e->getMessage()), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=' . SitemapComponent::NAME . '&view=dashboard', false));
    }
}
