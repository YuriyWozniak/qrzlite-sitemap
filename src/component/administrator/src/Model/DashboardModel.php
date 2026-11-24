<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Service\Generator;
use QrzLite\Component\Sitemap\Administrator\Storage\LinkRepository;
use QrzLite\Component\Sitemap\Administrator\Storage\SitemapFiles;

/**
 * Status of the sitemap for the dashboard: the files, the number of saved links, what the last generation
 * left out.
 */
class DashboardModel extends BaseDatabaseModel
{
    /** @var  array{0: string, 1: string}|null  Site URL and the reason it is not known. */
    private ?array $siteUrl = null;

    private ?Generator $generator = null;

    protected function populateState(): void
    {
        $app = Factory::getApplication();

        $this->setState(
            'exclusions',
            $app instanceof CMSWebApplicationInterface ? (array) $app->getUserState(SitemapComponent::NAME . '.exclusions', []) : []
        );
    }

    /**
     * The site URL, '' when it is not known.
     */
    public function getSiteUrl(): string
    {
        return $this->resolveSiteUrl()[0];
    }

    /**
     * Why the site URL is not known, '' when it is.
     */
    public function getSiteUrlError(): string
    {
        return $this->resolveSiteUrl()[1];
    }

    /**
     * The sitemap files in the site root, the main file first; url is '' when the site URL is not known.
     *
     * @return  array<int, array{name: string, url: string, size: int, time: int}>
     */
    public function getFiles(): array
    {
        $siteUrl = $this->getSiteUrl();
        $files   = [];

        foreach ((new SitemapFiles(JPATH_ROOT, $this->generator()->filename()))->existing() as $path) {
            $files[] = [
                'name' => basename($path),
                'url'  => $siteUrl !== '' ? $siteUrl . '/' . basename($path) : '',
                'size' => (int) filesize($path),
                'time' => (int) filemtime($path),
            ];
        }

        return $files;
    }

    /**
     * Number of links on the HTML sitemap page.
     */
    public function getLinks(): int
    {
        return (new LinkRepository($this->getDatabase()))->count();
    }

    /**
     * What the last generation from the dashboard left out: pairs of [what, reason].
     *
     * @return  array<int, string[]>
     */
    public function getExclusions(): array
    {
        return (array) $this->getState('exclusions', []);
    }

    /**
     * @return  array{0: string, 1: string}
     */
    private function resolveSiteUrl(): array
    {
        if ($this->siteUrl === null) {
            try {
                $this->siteUrl = [$this->generator()->siteUrl(), ''];
            } catch (\RuntimeException $e) {
                $this->siteUrl = ['', $e->getMessage()];
            }
        }

        return $this->siteUrl;
    }

    private function generator(): Generator
    {
        return $this->generator ??= Generator::create();
    }
}
