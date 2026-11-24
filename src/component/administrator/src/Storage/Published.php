<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Storage;

\defined('_JEXEC') or die;

/**
 * What the publisher wrote and deleted.
 */
final class Published
{
    /**
     * @param   string[]  $files    Written files, the main file last.
     * @param   string    $mode     Write mode of the main file (FileWriter::ATOMIC or IN_PLACE), '' when nothing was written.
     * @param   string[]  $deleted  Leftover split files that were deleted.
     * @param   string[]  $kept     Leftover split files that could not be deleted.
     */
    public function __construct(
        public readonly array $files,
        public readonly string $mode,
        public readonly array $deleted = [],
        public readonly array $kept = []
    ) {
    }

    /**
     * Nothing written: a dry run.
     */
    public static function nothing(): self
    {
        return new self([], '');
    }
}
