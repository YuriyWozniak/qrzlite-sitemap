<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Registry\Registry;
use QrzLite\Component\Sitemap\Administrator\Storage\Publisher;

/**
 * The sitemap options (config.xml), typed. The defaults apply until the options are saved once.
 */
final class Options
{
    /** Default of the URL filter. */
    public const DEFAULT_EXCLUDE = "/component/\n?view=";

    /**
     * @param   string  $filename    Name of the sitemap file without ".xml".
     * @param   string  $siteUrl     Site URL of the sitemap, '' for live_site or the current request.
     * @param   string  $exclude     URL filter: one path pattern per line.
     * @param   string  $changefreq  '' leaves the element out.
     * @param   string  $priority    '' leaves the element out.
     * @param   bool    $stylesheet  Link the XSL stylesheet that shows the sitemap in a browser.
     * @param   int     $splitLimit  Most URLs in one file.
     */
    public function __construct(
        public readonly string $filename = 'sitemap',
        public readonly string $siteUrl = '',
        public readonly bool $includeMenu = true,
        public readonly bool $includeCategories = true,
        public readonly bool $includeArticles = true,
        public readonly bool $includeArchived = false,
        public readonly string $exclude = self::DEFAULT_EXCLUDE,
        public readonly string $changefreq = 'weekly',
        public readonly string $priority = '0.5',
        public readonly bool $stylesheet = true,
        public readonly int $splitLimit = Publisher::MAX_URLS
    ) {
    }

    /**
     * @param   Registry   $params     The saved component options.
     * @param   ?Registry  $overrides  Options that replace the saved ones for one run.
     */
    public static function fromRegistry(Registry $params, ?Registry $overrides = null): self
    {
        $params = clone $params;

        // Not Registry::merge(), which skips empty values: an override "" turns an option off.
        foreach ($overrides?->toArray() ?? [] as $name => $value) {
            $params->set($name, $value);
        }

        // A text option saved empty ("None", an empty filter) stays empty; Registry::get() would give the default.
        $text = static fn (string $name, string $default): string => $params->exists($name) ? trim((string) $params->get($name, '')) : $default;

        return new self(
            filename: (string) $params->get('filename', 'sitemap') ?: 'sitemap',
            siteUrl: trim((string) $params->get('site_url', '')),
            includeMenu: (bool) $params->get('include_menu', 1),
            includeCategories: (bool) $params->get('include_categories', 1),
            includeArticles: (bool) $params->get('include_articles', 1),
            includeArchived: (bool) $params->get('include_archived', 0),
            exclude: $text('exclude', self::DEFAULT_EXCLUDE),
            changefreq: $text('changefreq', 'weekly'),
            priority: $text('priority', '0.5'),
            stylesheet: (bool) $params->get('xsl', 1),
            splitLimit: (int) $params->get('split_limit', Publisher::MAX_URLS)
        );
    }
}
