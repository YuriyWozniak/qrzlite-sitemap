<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;

/**
 * What the sitemap needs to know about the Joomla site: who a guest is, its languages, its global settings
 * and its URL.
 */
final class SiteEnvironment
{
    /**
     * @param   string  $root  Folder of the site root, where the sitemap files go.
     */
    public function __construct(private readonly CMSApplicationInterface $app, public readonly string $root)
    {
    }

    /**
     * Access levels a guest may see.
     *
     * @return  int[]
     */
    public function guestLevels(): array
    {
        return array_map('intval', Access::getAuthorisedViewLevels(0));
    }

    /**
     * Whether the site has a language prefix in its URLs (the language filter plugin is on).
     */
    public function isMultilingual(): bool
    {
        return PluginHelper::isEnabled('system', 'languagefilter');
    }

    /**
     * Default language of the site.
     */
    public function defaultLanguage(): string
    {
        return (string) ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
    }

    /**
     * The site-wide robots setting ("noindex, follow"), '' when there is none.
     */
    public function robots(): string
    {
        return (string) $this->app->get('robots', '');
    }

    public function siteName(): string
    {
        return (string) $this->app->get('sitename', '');
    }

    /**
     * The site URL: the given one, else the live_site setting, else the URL of the current request.
     *
     * @throws  \RuntimeException  When none is known (console without live_site or --live-site).
     */
    public function siteUrl(string $configured = ''): string
    {
        $url = trim($configured);

        if ($url === '') {
            $url = trim((string) $this->app->get('live_site', ''));
        }

        if ($url === '') {
            $url = Uri::root();
        }

        $url = rtrim($url, '/');

        if (!preg_match('#^https?://[^/?\#\s]+(/[^?\#\s]*)?$#i', $url) || str_contains($url, 'joomla.invalid')) {
            throw new \RuntimeException(
                'The site URL is not known: set "Site URL" in the sitemap options or live_site in the global '
                . 'configuration, or run the command with --live-site=https://example.com'
            );
        }

        return $url;
    }
}
