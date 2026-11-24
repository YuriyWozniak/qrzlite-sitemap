<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * Small checks shared by the menu, category and article rules. Every check returns the reason an item is
 * left out, or null when it passes.
 */
final class Rules
{
    /**
     * Turn a database datetime (UTC, 'Y-m-d H:i:s') into a date; null for empty, zero or invalid values.
     */
    public static function date(?string $value): ?\DateTimeImmutable
    {
        $value = trim((string) $value);

        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        $date   = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();

        // PHP 8.1 returns an array of zero counts when there is no error, later versions return false.
        if ($date === false || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }

        return $date;
    }

    /**
     * Read the robots value from a JSON params or metadata column.
     */
    public static function robots(?string $json): string
    {
        $data = json_decode((string) $json, true);

        return \is_array($data) && isset($data['robots']) && \is_string($data['robots']) ? trim($data['robots']) : '';
    }

    /**
     * The item's own robots value wins; when it is empty the site's global value applies.
     */
    public static function noindex(string $ownRobots, string $siteRobots): ?string
    {
        $robots = trim($ownRobots) !== '' ? $ownRobots : $siteRobots;

        return preg_match('/\bnoindex\b/i', $robots) ? 'noindex' : null;
    }

    public static function window(?string $publishUp, ?string $publishDown, \DateTimeImmutable $now): ?string
    {
        $up = self::date($publishUp);

        if ($up !== null && $up > $now) {
            return 'not published yet';
        }

        $down = self::date($publishDown);

        if ($down !== null && $down <= $now) {
            return 'publishing ended';
        }

        return null;
    }

    public static function access(int $access, Context $context): ?string
    {
        return \in_array($access, $context->guestLevels, true) ? null : 'not public (access level ' . $access . ')';
    }

    public static function language(string $language, Context $context): ?string
    {
        if (!$context->isMultilingual() || $language === '' || $language === '*') {
            return null;
        }

        return \in_array($language, $context->languages, true) ? null : 'language ' . $language . ' not published';
    }

    /**
     * Joomla publish states: 1 published, 0 unpublished, 2 archived, -2 trashed.
     */
    public static function state(int $state, Context $context): ?string
    {
        return match (true) {
            $state === 1                              => null,
            $state === 2 && $context->includeArchived => null,
            $state === 2                              => 'archived',
            $state === -2                             => 'trashed',
            default                                   => 'unpublished',
        };
    }

    /**
     * The later of two dates, ignoring nulls.
     */
    public static function latest(?\DateTimeImmutable $a, ?\DateTimeImmutable $b): ?\DateTimeImmutable
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return $a >= $b ? $a : $b;
    }
}
