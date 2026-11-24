<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * What the rules need to know about the site: guest access, current time, robots default, languages, options.
 */
final class Context
{
    /** @var int[] */
    public readonly array $guestLevels;

    /** @var ?string[] */
    public readonly ?array $languages;

    /**
     * @param   int[]               $guestLevels      View access levels of a guest (not logged in) visitor.
     * @param   \DateTimeImmutable  $now              Current time.
     * @param   string              $siteRobots       Global robots setting of the site ('' = index, follow).
     * @param   ?string[]           $languages        Published content languages (lang codes) of a multilingual
     *                                                site; null when the site is not multilingual.
     * @param   bool                $includeArchived  List archived articles and categories (they stay reachable).
     * @param   string              $defaultLanguage  Default site language of a multilingual site: items for all
     *                                                languages ("*") are routed in it.
     */
    public function __construct(
        array $guestLevels,
        public readonly \DateTimeImmutable $now,
        public readonly string $siteRobots = '',
        ?array $languages = null,
        public readonly bool $includeArchived = false,
        public readonly string $defaultLanguage = ''
    ) {
        $this->guestLevels = array_values(array_map('intval', $guestLevels));
        $this->languages   = $languages === null ? null : array_values(array_map('strval', $languages));
    }

    public function isMultilingual(): bool
    {
        return $this->languages !== null;
    }

    /**
     * Suffix that pins a route to a language on a multilingual site, '' otherwise. Items for all languages get
     * the default language, so their URLs don't depend on the language of whoever runs the generation.
     */
    public function languageSuffix(string $language): string
    {
        if (!$this->isMultilingual()) {
            return '';
        }

        if ($language === '' || $language === '*') {
            $language = $this->defaultLanguage;
        }

        return $language === '' ? '' : '&lang=' . $language;
    }
}
