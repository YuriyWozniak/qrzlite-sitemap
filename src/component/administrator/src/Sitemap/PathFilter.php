<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * Leaves out URLs whose path contains one of the given texts (case-insensitive), e.g. "/component/".
 */
final class PathFilter
{
    /** @var string[] */
    private array $patterns;

    /**
     * @param   string[]  $patterns
     */
    public function __construct(array $patterns)
    {
        $unique = [];

        // Matching ignores case, so do duplicates.
        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);
            $key     = mb_strtolower($pattern, 'UTF-8');

            if ($pattern !== '' && !isset($unique[$key])) {
                $unique[$key] = $pattern;
            }
        }

        $this->patterns = array_values($unique);
    }

    /**
     * One pattern per line.
     */
    public static function fromText(string $text): self
    {
        return new self(preg_split('/\R/', $text) ?: []);
    }

    /**
     * @return  string[]
     */
    public function patterns(): array
    {
        return $this->patterns;
    }

    /**
     * The first pattern the path contains, or null.
     */
    public function match(string $path): ?string
    {
        foreach ($this->patterns as $pattern) {
            if (mb_stripos($path, $pattern, 0, 'UTF-8') !== false) {
                return $pattern;
            }
        }

        return null;
    }
}
