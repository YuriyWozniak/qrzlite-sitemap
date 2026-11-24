<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * A page that may go into the sitemap, before routing: where it comes from, its non-SEF route and,
 * when a rule keeps it out, why.
 */
final class Candidate
{
    public const TYPE_MENU     = 'menu';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_ARTICLE  = 'article';

    /**
     * @param   string               $type      One of the TYPE_* constants.
     * @param   int                  $id        Id of the source record (menu item, category, article).
     * @param   string               $title     Title for the HTML sitemap.
     * @param   string               $route     Non-SEF route, e.g. index.php?Itemid=101.
     * @param   ?\DateTimeImmutable  $lastmod   Last modification (UTC), null when unknown.
     * @param   ?string              $excluded  Reason the page is left out, null when it is included.
     * @param   bool                 $blocks    The page exists but must not be listed (noindex): its URL is
     *                                          removed even when another candidate routes to the same URL.
     * @param   bool                 $home      A home page: never excluded, never filtered.
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $title,
        public readonly string $route,
        public readonly ?\DateTimeImmutable $lastmod = null,
        public readonly ?string $excluded = null,
        public readonly bool $blocks = false,
        public readonly bool $home = false
    ) {
    }

    public function isIncluded(): bool
    {
        return $this->excluded === null;
    }

    /**
     * Short description for diagnostics, e.g. "article #12 Title".
     */
    public function describe(): string
    {
        return $this->type . ' #' . $this->id . ($this->title !== '' ? ' ' . $this->title : '');
    }
}
