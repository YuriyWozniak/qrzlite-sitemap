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
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Assembler;
use QrzLite\Component\Sitemap\Administrator\Sitemap\CandidateBuilder;
use QrzLite\Component\Sitemap\Administrator\Sitemap\CategoryTree;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Context;
use QrzLite\Component\Sitemap\Administrator\Sitemap\FileWriter;
use QrzLite\Component\Sitemap\Administrator\Sitemap\PathFilter;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Publisher;
use QrzLite\Component\Sitemap\Administrator\Sitemap\SitemapXml;
use QrzLite\Component\Sitemap\Administrator\Storage\LinkRepository;

/**
 * Generates the sitemap of the current site: reads the content, routes it, writes the XML files into the
 * site root and saves the links for the HTML sitemap page. Used by the administrator button, the console
 * command and the scheduled task.
 */
final class Generator
{
    /** Default of the URL filter (as in config.xml; the options are empty until they are saved once). */
    public const DEFAULT_EXCLUDE = "/component/\n?view=";

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly Registry $params,
        private readonly CMSApplicationInterface $app,
        private readonly string $root
    ) {
    }

    /**
     * @param   ?Registry  $overrides  Options that replace the component options for this run.
     */
    public static function create(?Registry $overrides = null): self
    {
        $params = clone ComponentHelper::getParams(SitemapComponent::NAME);

        // Not Registry::merge(), which skips empty values: an override "" turns an option off.
        foreach ($overrides?->toArray() ?? [] as $name => $value) {
            $params->set($name, $value);
        }

        return new self(Factory::getContainer()->get(DatabaseInterface::class), $params, Factory::getApplication(), JPATH_ROOT);
    }

    /**
     * @param   bool     $write   Write the files and save the links; false for a dry run.
     * @param   ?string  $folder  Write the files into this folder instead of the site root, without saving links.
     *
     * @throws  \RuntimeException
     */
    public function generate(bool $write = true, ?string $folder = null): Result
    {
        $start   = microtime(true);
        $now     = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $siteUrl = $this->siteUrl();
        $source  = new ContentSource($this->db);
        $levels  = Access::getAuthorisedViewLevels(0);
        $multi   = PluginHelper::isEnabled('system', 'languagefilter');
        $default = (string) ComponentHelper::getParams('com_languages')->get('site', 'en-GB');

        $context = new Context(
            $levels,
            $now,
            (string) $this->app->get('robots', ''),
            $multi ? $source->languages($levels) : null,
            (bool) $this->params->get('include_archived', 0),
            $multi ? $default : ''
        );

        $articleRows  = $source->articles();
        $categoryRows = $source->categories();
        $builder      = new CandidateBuilder($context, new CategoryTree($categoryRows, $context));
        $articles     = $builder->articles($articleRows);
        $categories   = $builder->categories($categoryRows);
        $menuItems    = $builder->menuItems($source->menuItems());

        $candidates = array_merge(
            $this->params->get('include_menu', 1) ? $menuItems : [],
            $this->params->get('include_categories', 1) ? $categories : [],
            $this->params->get('include_articles', 1) ? $articles : []
        );

        $assembly = (new Assembler(
            new RouteBuilder($siteUrl),
            PathFilter::fromText($this->option('exclude', self::DEFAULT_EXCLUDE)),
            (string) $this->app->get('sitename', '')
        ))->assemble($candidates);

        $published = ['files' => [], 'mode' => '', 'deleted' => [], 'kept' => []];
        $stored    = false;

        if ($write) {
            $xml = new SitemapXml(
                $this->option('changefreq', 'weekly'),
                $this->option('priority', '0.5'),
                $this->params->get('xsl', 1) ? $this->stylesheet($siteUrl, $default) : null,
                $now
            );

            $published = (new Publisher(new FileWriter(), $xml, (int) $this->params->get('split_limit', Publisher::MAX_URLS)))
                ->publish($folder ?? $this->root, $this->filename(), $siteUrl, $assembly->entries, $now);

            if ($folder === null) {
                (new LinkRepository($this->db))->replace($assembly->entries);
                $stored = true;
            }
        }

        return new Result(
            $siteUrl,
            $assembly->entries,
            $assembly->exclusions,
            $published['files'],
            $published['mode'],
            $published['deleted'],
            $published['kept'],
            $stored,
            microtime(true) - $start
        );
    }

    /**
     * A text option; empty when it was saved empty ("None", an empty filter), the default only when it was
     * never saved. Registry::get() would give the default for an empty value too.
     */
    private function option(string $name, string $default): string
    {
        return $this->params->exists($name) ? trim((string) $this->params->get($name, '')) : $default;
    }

    public function filename(): string
    {
        return (string) $this->params->get('filename', 'sitemap') ?: 'sitemap';
    }

    /**
     * The site URL: the option, else the live_site setting, else the URL of the current request.
     *
     * @throws  \RuntimeException  When none is known (console without live_site or --live-site).
     */
    public function siteUrl(): string
    {
        $url = trim((string) $this->params->get('site_url', ''));

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

    /**
     * URL of the XSL stylesheet for the site language, English when there is none for it.
     */
    private function stylesheet(string $siteUrl, string $language): string
    {
        $folder = '/media/' . SitemapComponent::NAME . '/xsl/';
        $file   = is_file($this->root . $folder . 'sitemap-' . $language . '.xsl') ? 'sitemap-' . $language . '.xsl' : 'sitemap-en-GB.xsl';

        return $siteUrl . $folder . $file;
    }
}
