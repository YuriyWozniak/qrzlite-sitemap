<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Service;

\defined('_JEXEC') or die;

use QrzLite\Component\Sitemap\Administrator\Sitemap\Entry;

/**
 * What a generation produced.
 */
final class Result
{
    /**
     * @param   string                $siteUrl     Site URL used for the addresses.
     * @param   Entry[]               $entries     Listed URLs.
     * @param   array<int, string[]>  $exclusions  Pairs of [what, reason].
     * @param   string[]              $files       Written files (empty for a dry run).
     * @param   string                $mode        Write mode of the main file ('' for a dry run).
     * @param   string[]              $deleted     Deleted leftover split files.
     * @param   string[]              $kept        Leftover split files that could not be deleted.
     * @param   bool                  $stored      Links saved for the HTML sitemap.
     * @param   float                 $seconds     Duration.
     */
    public function __construct(
        public readonly string $siteUrl,
        public readonly array $entries,
        public readonly array $exclusions,
        public readonly array $files,
        public readonly string $mode,
        public readonly array $deleted,
        public readonly array $kept,
        public readonly bool $stored,
        public readonly float $seconds
    ) {
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    /**
     * One line for logs and messages.
     */
    public function summary(): string
    {
        $text = \sprintf('%d URLs, %d left out', $this->count(), \count($this->exclusions));

        if ($this->files) {
            $text .= ', written to ' . implode(', ', array_map('basename', $this->files)) . ' (' . $this->mode . ')';
        }

        if ($this->kept) {
            $text .= ', could not delete ' . implode(', ', array_map('basename', $this->kept));
        }

        return $text . \sprintf(' in %.1f s', $this->seconds);
    }
}
