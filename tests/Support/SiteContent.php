<?php

namespace QrzLite\Tests\Support;

/**
 * A small site: a home page, a News category with a menu item and two articles, a hidden category and
 * articles that are left out. Use in a JoomlaTestCase.
 */
trait SiteContent
{
    /**
     * URLs the generator lists for this content, as paths. (Not a constant: PHP 8.1 has none in traits.)
     *
     * @return  string[]
     */
    protected static function sitePaths(): array
    {
        return ['/', '/news', '/news/first', '/news/second'];
    }

    protected function seedSite(): void
    {
        $this->insert('#__extensions', [
            ['extension_id' => 22, 'type' => 'component', 'element' => 'com_content', 'enabled' => 1],
        ]);
        $this->insert('#__categories', [
            ['id' => 1, 'extension' => 'system', 'title' => 'ROOT', 'lft' => 0],
            ['id' => 2, 'parent_id' => 1, 'title' => 'News', 'alias' => 'news', 'lft' => 1, 'modified_time' => '2024-01-01 00:00:00'],
            ['id' => 3, 'parent_id' => 1, 'title' => 'Hidden', 'alias' => 'hidden', 'lft' => 2, 'published' => 0],
        ]);
        $this->insert('#__content', [
            ['id' => 1, 'title' => 'First', 'alias' => 'first', 'catid' => 2, 'modified' => '2025-01-01 10:00:00'],
            ['id' => 2, 'title' => 'Second', 'alias' => 'second', 'catid' => 2, 'modified' => '2025-02-01 10:00:00'],
            ['id' => 3, 'title' => 'Draft', 'alias' => 'draft', 'catid' => 2, 'state' => 0],
            ['id' => 4, 'title' => 'Trashed', 'alias' => 'trashed', 'catid' => 2, 'state' => -2],
            ['id' => 5, 'title' => 'In hidden', 'alias' => 'in-hidden', 'catid' => 3],
            ['id' => 6, 'title' => 'Private', 'alias' => 'private', 'catid' => 2, 'access' => 2],
        ]);
        $this->insert('#__menu', [
            ['id' => 1, 'title' => 'Menu_Item_Root', 'lft' => 0, 'link' => ''],
            ['id' => 101, 'title' => 'Home', 'lft' => 1, 'home' => 1, 'component_id' => 22, 'link' => 'index.php?option=com_content&view=featured'],
            ['id' => 102, 'title' => 'News', 'lft' => 2, 'component_id' => 22, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'],
        ]);

        $this->routes = [
            'index.php?Itemid=101'                                          => '/',
            'index.php?Itemid=102'                                          => '/news',
            'index.php?option=com_content&view=category&id=2'               => '/news',
            'index.php?option=com_content&view=article&id=1:first&catid=2'  => '/news/first',
            'index.php?option=com_content&view=article&id=2:second&catid=2' => '/news/second',
        ];
    }
}
