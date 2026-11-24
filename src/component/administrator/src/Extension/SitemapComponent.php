<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\ExtensionManagerInterface;
use Joomla\CMS\Extension\MVCComponent;
use QrzLite\Component\Sitemap\Administrator\Service\SitemapFactory;

/**
 * The sitemap component.
 */
class SitemapComponent extends MVCComponent implements RouterServiceInterface
{
    use RouterServiceTrait;

    /** The component's name: the option in URLs, the asset of its permissions, the key of its options. */
    public const NAME = 'com_qrzlitesitemap';

    private SitemapFactory $sitemapFactory;

    /**
     * The sitemap services of the component that an application boots: how controllers and plugins get them.
     *
     * @throws  \RuntimeException  When there is no application or the component is not installed.
     */
    public static function sitemapFactory(?ExtensionManagerInterface $app): SitemapFactory
    {
        $component = $app?->bootComponent(self::NAME);

        if (!$component instanceof self) {
            throw new \RuntimeException('The QrzLite Sitemap component is not installed');
        }

        return $component->getSitemapFactory();
    }

    public function getSitemapFactory(): SitemapFactory
    {
        return $this->sitemapFactory;
    }

    public function setSitemapFactory(SitemapFactory $factory): void
    {
        $this->sitemapFactory = $factory;
    }
}
