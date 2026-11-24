<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Service\SitemapFactory;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\QrzLite\\Component\\Sitemap'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\QrzLite\\Component\\Sitemap'));
        $container->registerServiceProvider(new RouterFactory('\\QrzLite\\Component\\Sitemap'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container) {
                $component = new SitemapComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setRouterFactory($container->get(RouterFactoryInterface::class));
                $component->setSitemapFactory(new SitemapFactory(
                    ComponentHelper::getParams(SitemapComponent::NAME),
                    Factory::getApplication(),
                    $container,
                    JPATH_ROOT
                ));

                return $component;
            }
        );
    }
};
