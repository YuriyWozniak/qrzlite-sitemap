<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Model\DashboardModel;

class DisplayController extends BaseController
{
    /**
     * @var  string
     */
    protected $default_view = 'dashboard';

    /**
     * The dashboard model also gets the sitemap services.
     *
     * @param   string                $name
     * @param   string                $prefix
     * @param   array<string, mixed>  $config
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel|bool
     */
    public function getModel($name = '', $prefix = '', $config = [])
    {
        $model = parent::getModel($name, $prefix, $config);

        if ($model instanceof DashboardModel) {
            $model->setSitemapFactory(SitemapComponent::sitemapFactory($this->app));
        }

        return $model;
    }
}
