<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Router\SiteRouter;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\Registry\Registry;
use QrzLite\Component\Sitemap\Administrator\Joomla\ContentSource;
use QrzLite\Component\Sitemap\Administrator\Joomla\RouteBuilder;
use QrzLite\Component\Sitemap\Administrator\Joomla\SiteEnvironment;
use QrzLite\Component\Sitemap\Administrator\Storage\FileWriter;
use QrzLite\Component\Sitemap\Administrator\Storage\LinkRepository;

/**
 * Puts the sitemap services together; the component makes it in its service provider. The site router and the
 * site application come from the container only when a generator needs them.
 */
final class SitemapFactory
{
    /**
     * @param   Registry  $params  The saved component options.
     * @param   string    $root    Folder of the site root.
     */
    public function __construct(
        private readonly Registry $params,
        private readonly CMSApplicationInterface $app,
        private readonly Container $container,
        private readonly string $root
    ) {
    }

    /**
     * @param   ?Registry  $overrides  Options that replace the saved ones.
     */
    public function createOptions(?Registry $overrides = null): Options
    {
        return Options::fromRegistry($this->params, $overrides);
    }

    public function createSite(): SiteEnvironment
    {
        return new SiteEnvironment($this->app, $this->root);
    }

    /**
     * @param   ?Registry  $overrides  Options that replace the saved ones for this generator.
     */
    public function createGenerator(?Registry $overrides = null): Generator
    {
        $db = $this->container->get(DatabaseInterface::class);

        return new Generator(
            $this->createOptions($overrides),
            $this->createSite(),
            new ContentSource($db),
            new RouteBuilder(
                $this->container->get(SiteRouter::class),
                $this->app,
                fn (): SiteApplication => $this->container->get(SiteApplication::class)
            ),
            new LinkRepository($db),
            new FileWriter()
        );
    }
}
