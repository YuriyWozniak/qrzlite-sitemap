<?php

namespace QrzLite\Tests\Support;

use Joomla\CMS\Router\SiteRouter;

/**
 * A large site for performance tests: a home page, menu items for the top-level categories, categories two
 * levels deep and many articles. Routes come from PathRouter. Use in a JoomlaTestCase.
 */
trait LargeSite
{
    /** Top-level categories, each with a menu item; the other categories are below them. */
    private static int $topCategories = 10;

    private int $categoryCount = 0;

    private int $articleCount = 0;

    /**
     * URLs the generator lists: the home page, a menu item per top-level category, the categories and the articles.
     */
    protected function largeSiteUrls(): int
    {
        return 1 + min(self::$topCategories, $this->categoryCount) + $this->categoryCount + $this->articleCount;
    }

    protected function seedLargeSite(int $articles, int $categories = 100): void
    {
        $this->container->share(SiteRouter::class, new PathRouter());

        $this->insert('#__extensions', [
            ['extension_id' => 22, 'type' => 'component', 'element' => 'com_content', 'enabled' => 1],
        ]);

        $rows = [['id' => 1, 'extension' => 'system', 'title' => 'ROOT', 'lft' => 0]];
        $menu = [
            ['id' => 1, 'title' => 'Menu_Item_Root', 'lft' => 0, 'link' => ''],
            ['id' => PathRouter::HOME_ITEM, 'title' => 'Home', 'lft' => 1, 'home' => 1, 'component_id' => 22, 'link' => 'index.php?option=com_content&view=featured'],
        ];

        for ($id = 2; $id < $categories + 2; $id++) {
            $top    = $id - 2 < self::$topCategories;
            $rows[] = [
                'id'            => $id,
                'parent_id'     => $top ? 1 : 2 + ($id - 2) % self::$topCategories,
                'title'         => 'Category ' . $id,
                'alias'         => 'category-' . $id,
                'lft'           => $top ? $id : 1000 + $id,
                'modified_time' => '2024-01-01 00:00:00',
            ];

            if ($top) {
                $menu[] = [
                    'id'           => 200 + $id,
                    'title'        => 'Menu ' . $id,
                    'lft'          => 1 + $id,
                    'component_id' => 22,
                    'link'         => 'index.php?option=com_content&view=category&layout=blog&id=' . $id,
                ];
            }
        }

        $this->db->transactionStart();
        $this->insert('#__categories', $rows);
        $this->insert('#__menu', $menu);
        $this->db->transactionCommit();

        $this->categoryCount = $categories;
        $this->addArticles($articles);
    }

    /**
     * Adds published articles, spread over the categories, each modified at another minute.
     */
    protected function addArticles(int $count): void
    {
        $db    = $this->db;
        $first = $this->articleCount + 1;
        $last  = $this->articleCount + $count;
        $base  = (int) strtotime('2025-01-01 00:00:00 UTC');
        $cats  = max(1, $this->categoryCount);

        $db->transactionStart();

        // Multi-row INSERTs: inserting rows one by one would take most of the test's time.
        foreach (array_chunk(range($first, $last), 400) as $ids) {
            $values = array_map(
                static fn (int $id): string => '(' . implode(',', [
                    $id,
                    $db->quote('Article ' . $id),
                    $db->quote('article-' . $id),
                    2 + $id % $cats,
                    $db->quote(gmdate('Y-m-d H:i:s', $base + $id * 60)),
                ]) . ')',
                $ids
            );

            $db->setQuery(
                'INSERT INTO ' . $db->quoteName('#__content') . ' (' . implode(',', $db->quoteName(['id', 'title', 'alias', 'catid', 'modified'])) . ')'
                . ' VALUES ' . implode(',', $values)
            )->execute();
        }

        $db->transactionCommit();
        $this->articleCount = $last;
    }
}
