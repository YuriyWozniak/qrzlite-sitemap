<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * Article categories with their parents. A category is reachable only when it and every parent category is
 * published, public and in a published language.
 */
final class CategoryTree
{
    /** @var array<int, array{parent: int, state: int, access: int, language: string}> */
    private array $nodes = [];

    /** @var array<int, ?string> */
    private array $reasons = [];

    /**
     * @param   iterable<array<string, mixed>>  $rows  Rows with id, parent_id, published, access, language.
     */
    public function __construct(iterable $rows, private readonly Context $context)
    {
        foreach ($rows as $row) {
            $this->nodes[(int) $row['id']] = [
                'parent'   => (int) ($row['parent_id'] ?? 0),
                'state'    => (int) ($row['published'] ?? 0),
                'access'   => (int) ($row['access'] ?? 0),
                'language' => (string) ($row['language'] ?? '*'),
            ];
        }
    }

    public function has(int $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /**
     * Why the category (or one of its parents) is not reachable; null when it is.
     */
    public function reason(int $id): ?string
    {
        if (\array_key_exists($id, $this->reasons)) {
            return $this->reasons[$id];
        }

        if (!isset($this->nodes[$id])) {
            return $this->reasons[$id] = 'category #' . $id . ' not found';
        }

        $reason  = null;
        $visited = [];
        $current = $id;

        // Walk up to the root; the root category is not in the list, so the walk stops there.
        while (isset($this->nodes[$current]) && !isset($visited[$current])) {
            $visited[$current] = true;
            $node              = $this->nodes[$current];
            $own               = Rules::state($node['state'], $this->context)
                ?? Rules::access($node['access'], $this->context)
                ?? Rules::language($node['language'], $this->context);

            if ($own !== null) {
                $reason = ($current === $id ? 'category ' : 'parent category #' . $current . ' ') . $own;
                break;
            }

            $current = $node['parent'];
        }

        return $this->reasons[$id] = $reason;
    }
}
