<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Event\Application\AfterInitialiseEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;

/**
 * Builds SEF paths of the site frontend from any application: site, administrator or console (CLI, scheduler).
 *
 * The result is a path relative to the site root ("/news/item.html"); the site URL is added by the generator.
 * Joomla's site router puts the base path of the current request in front of every path; outside a site
 * request that base is wrong (the console gives "cli"), so it is taken off here.
 */
final class RouteBuilder
{
    private readonly SiteRouter $router;

    /**
     * @param   string       $siteUrl  Site URL, e.g. https://example.com
     * @param   ?SiteRouter  $router   The site router, null for Joomla's.
     */
    public function __construct(string $siteUrl, ?SiteRouter $router = null)
    {
        // The console (cli/joomla.php, scheduler:run) has no request URI, so Joomla makes the current URL from the
        // script name: "https://example.comcli/joomla.php", which can't be parsed. Give it the site path instead.
        if (\PHP_SAPI === 'cli' && empty($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = rtrim((string) parse_url($siteUrl, PHP_URL_PATH), '/') . '/';
            Uri::reset();
        }

        $this->router = $router ?? Factory::getContainer()->get(SiteRouter::class);

        $this->attachLanguageFilter();
    }

    public function __invoke(string $route): ?string
    {
        $path   = $this->router->build($route)->toString(['path', 'query', 'fragment']);
        $prefix = Uri::root(true) . '/';

        if (str_starts_with($path, $prefix)) {
            $path = substr($path, \strlen($prefix));
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * On a multilingual site the language filter plugin adds the language prefix (/en/) when it initialises the
     * site or administrator application. The console doesn't initialise it, so do it here.
     */
    private function attachLanguageFilter(): void
    {
        if (!PluginHelper::isEnabled('system', 'languagefilter')) {
            return;
        }

        foreach ($this->router->getRules()['buildpreprocess'] ?? [] as $rule) {
            if (\is_array($rule) && \is_object($rule[0] ?? null) && str_contains(\get_class($rule[0]), 'LanguageFilter')) {
                return;
            }
        }

        $plugin = Factory::getApplication()->bootPlugin('languagefilter', 'system');

        if (method_exists($plugin, 'onAfterInitialise')) {
            $plugin->onAfterInitialise(new AfterInitialiseEvent('onAfterInitialise', [
                'subject' => Factory::getContainer()->get(SiteApplication::class),
            ]));
        }
    }
}
