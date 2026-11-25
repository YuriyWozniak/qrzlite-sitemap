<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Service;

\defined('_JEXEC') or die;

use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Joomla\ContentSource;
use QrzLite\Component\Sitemap\Administrator\Joomla\RouteBuilder;
use QrzLite\Component\Sitemap\Administrator\Joomla\SiteEnvironment;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Assembler;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Candidate;
use QrzLite\Component\Sitemap\Administrator\Sitemap\CandidateBuilder;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Context;
use QrzLite\Component\Sitemap\Administrator\Sitemap\PathFilter;
use QrzLite\Component\Sitemap\Administrator\Sitemap\SitemapXml;
use QrzLite\Component\Sitemap\Administrator\Storage\FileWriter;
use QrzLite\Component\Sitemap\Administrator\Storage\LinkRepository;
use QrzLite\Component\Sitemap\Administrator\Storage\Published;
use QrzLite\Component\Sitemap\Administrator\Storage\Publisher;

/**
 * Generates the sitemap of the current site: reads the content, routes it, writes the XML files into the
 * site root and saves the links for the HTML sitemap page. Used by the administrator button, the console
 * command and the scheduled task, which get it from SitemapFactory.
 */
final class Generator
{
    public function __construct(
        private readonly Options $options,
        private readonly SiteEnvironment $site,
        private readonly ContentSource $source,
        private readonly RouteBuilder $routes,
        private readonly LinkRepository $links,
        private readonly FileWriter $writer
    ) {
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
        $siteUrl = $this->site->siteUrl($this->options->siteUrl);
        $levels  = $this->site->guestLevels();
        $multi   = $this->site->isMultilingual();
        $default = $this->site->defaultLanguage();

        $context = new Context(
            $levels,
            $now,
            $this->site->robots(),
            $multi ? $this->source->languages($levels) : null,
            $this->options->includeArchived,
            $multi ? $default : ''
        );

        $this->routes->prepare($siteUrl);

        // Articles are read, routed and dropped one page at a time.
        $candidates = (new CandidateBuilder($context))->candidates(
            $this->source->articles(),
            $this->source->categories(),
            $this->source->menuItems()
        );

        $assembly = (new Assembler($this->routes, PathFilter::fromText($this->options->exclude), $this->site->siteName()))
            ->assemble($this->listed($candidates));

        $published = Published::nothing();
        $stored    = false;

        if ($write) {
            $xml = new SitemapXml(
                $this->options->changefreq,
                $this->options->priority,
                $this->options->stylesheet ? $this->stylesheet($siteUrl, $default) : null,
                $now
            );

            $published = (new Publisher($this->writer, $xml, $this->options->splitLimit))
                ->publish($folder ?? $this->site->root, $this->options->filename, $siteUrl, $assembly->entries, $now);

            if ($folder === null) {
                $this->links->replace($assembly->entries);
                $stored = true;
            }
        }

        return new Result(
            $siteUrl,
            $assembly->entries,
            $assembly->exclusions,
            $published->files,
            $published->mode,
            $published->deleted,
            $published->kept,
            $stored,
            microtime(true) - $start
        );
    }

    /**
     * The candidates of the kinds the options list. All of them are still built: categories and menu items
     * depend on the articles.
     *
     * @param   iterable<Candidate>  $candidates
     *
     * @return  \Generator<int, Candidate>
     */
    private function listed(iterable $candidates): \Generator
    {
        $kinds = array_filter([
            Candidate::TYPE_MENU     => $this->options->includeMenu,
            Candidate::TYPE_CATEGORY => $this->options->includeCategories,
            Candidate::TYPE_ARTICLE  => $this->options->includeArticles,
        ]);

        foreach ($candidates as $candidate) {
            if (isset($kinds[$candidate->type])) {
                yield $candidate;
            }
        }
    }

    /**
     * URL of the XSL stylesheet for the site language, English when there is none for it.
     */
    private function stylesheet(string $siteUrl, string $language): string
    {
        $folder = '/media/' . SitemapComponent::NAME . '/xsl/';
        $file   = is_file($this->site->root . $folder . 'sitemap-' . $language . '.xsl') ? 'sitemap-' . $language . '.xsl' : 'sitemap-en-GB.xsl';

        return $siteUrl . $folder . $file;
    }
}
