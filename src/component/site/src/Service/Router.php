<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

/**
 * Router of the HTML sitemap page.
 */
class Router extends RouterView
{
    public function __construct(?SiteApplication $app = null, ?AbstractMenu $menu = null)
    {
        // Joomla takes the component name from the namespace, which would give "sitemap": the menu rules would then
        // not match the menu items of com_qrzlitesitemap, and their links would keep "?view=html".
        $this->name = 'qrzlitesitemap';

        $this->registerView(new RouterViewConfiguration('html'));

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }
}
