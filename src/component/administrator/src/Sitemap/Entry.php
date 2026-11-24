<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * One URL of the sitemap.
 */
final class Entry
{
    /**
     * @param   string               $path     Path (with query, if any) relative to the site root, starting with "/".
     * @param   string               $title    Title for the HTML sitemap.
     * @param   ?\DateTimeImmutable  $lastmod  Last modification, null when unknown.
     */
    public function __construct(
        public readonly string $path,
        public readonly string $title,
        public readonly ?\DateTimeImmutable $lastmod = null
    ) {
    }

    /**
     * Depth of the path: "/" and "/news.html" are 1, "/news/item.html" is 2.
     */
    public function level(): int
    {
        $path = (string) strtok($this->path, '?#');

        return max(1, substr_count(rtrim($path, '/'), '/'));
    }
}
