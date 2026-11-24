<?php

namespace QrzLite\Tests\Support;

use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;

/**
 * A site router for performance tests: makes a path from the route itself, without menus or component routers,
 * and remembers nothing. A PHPUnit test double records every call, which would add to the memory measured.
 *
 *   index.php?Itemid=101                                   /           (the home menu item)
 *   index.php?Itemid=7                                     /menu-7
 *   index.php?option=com_content&view=category&id=3        /category-3
 *   index.php?option=com_content&view=article&id=9:a&catid=3  /category-3/a
 */
final class PathRouter extends SiteRouter
{
    public const HOME_ITEM = 101;

    /**
     * Needs none of what Joomla's site router needs (application, menu).
     */
    public function __construct()
    {
    }

    /**
     * @param   string|array<string, mixed>  $url  A route, or its query as an array.
     */
    public function build(mixed $url): Uri
    {
        return new Uri(Uri::root(true) . self::path(\is_array($url) ? 'index.php?' . http_build_query($url) : (string) $url));
    }

    /**
     * @return  array<string, mixed>
     */
    public function getRules(): array
    {
        return ['buildpreprocess' => []];
    }

    public static function path(string $route): string
    {
        parse_str((string) parse_url($route, PHP_URL_QUERY), $query);

        $id = (string) ($query['id'] ?? '');

        return match (true) {
            isset($query['Itemid'])               => (int) $query['Itemid'] === self::HOME_ITEM ? '/' : '/menu-' . $query['Itemid'],
            ($query['view'] ?? '') === 'category' => '/category-' . $id,
            ($query['view'] ?? '') === 'article'  => '/category-' . ($query['catid'] ?? 0) . '/' . (explode(':', $id, 2)[1] ?? $id),
            default                               => '/unknown',
        };
    }
}
