<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * Result of the assembly: the URLs to list and what was left out.
 */
final class Assembly
{
    /**
     * @param   Entry[]                 $entries     URLs sorted by path.
     * @param   array<int, string[]>    $exclusions  Pairs of [what, reason].
     */
    public function __construct(public readonly array $entries, public readonly array $exclusions)
    {
    }
}
