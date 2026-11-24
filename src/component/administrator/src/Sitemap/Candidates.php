<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * The candidates of a site, by kind.
 */
final class Candidates
{
    /**
     * @param   Candidate[]  $menuItems
     * @param   Candidate[]  $categories
     * @param   Candidate[]  $articles
     */
    public function __construct(
        public readonly array $menuItems,
        public readonly array $categories,
        public readonly array $articles
    ) {
    }
}
