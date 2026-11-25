<?php

namespace QrzLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Candidate;
use QrzLite\Component\Sitemap\Administrator\Sitemap\CandidateBuilder;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Context;

final class CandidateBuilderTest extends TestCase
{
    private const CATEGORIES = [
        [
            'id'            => 2,
            'parent_id'     => 1,
            'published'     => 1,
            'access'        => 1,
            'language'      => '*',
            'title'         => 'News',
            'metadata'      => '{}',
            'created_time'  => '2020-01-01 00:00:00',
            'modified_time' => '2021-01-01 00:00:00',
        ],
        [
            'id'            => 3,
            'parent_id'     => 1,
            'published'     => 0,
            'access'        => 1,
            'language'      => '*',
            'title'         => 'Drafts',
            'metadata'      => '{}',
            'created_time'  => '2020-01-01 00:00:00',
            'modified_time' => '2020-01-01 00:00:00',
        ],
        [
            'id'            => 4,
            'parent_id'     => 2,
            'published'     => 1,
            'access'        => 1,
            'language'      => '*',
            'title'         => 'Hidden',
            'metadata'      => '{"robots":"noindex, follow"}',
            'created_time'  => '2020-01-01 00:00:00',
            'modified_time' => null,
        ],
    ];

    /**
     * @param   ?string[]  $languages
     */
    private function builder(?array $languages = null, bool $archived = false, string $siteRobots = '', string $default = ''): CandidateBuilder
    {
        $context = new Context([1, 5], new \DateTimeImmutable('2026-06-15 12:00:00', new \DateTimeZone('UTC')), $siteRobots, $languages, $archived, $default);

        return new CandidateBuilder($context);
    }

    /**
     * Article candidates, built with the test categories and no menu items.
     *
     * @param   array<int, array<string, mixed>>  $rows
     *
     * @return  Candidate[]
     */
    private static function articles(CandidateBuilder $builder, array $rows): array
    {
        return $builder->build($rows, self::CATEGORIES, [])->articles;
    }

    /**
     * @param   array<string, mixed>  $overrides
     *
     * @return  array<string, mixed>
     */
    private static function article(int $id, array $overrides = []): array
    {
        return $overrides + [
            'id'           => $id,
            'title'        => 'Article ' . $id,
            'alias'        => 'article-' . $id,
            'catid'        => 2,
            'state'        => 1,
            'access'       => 1,
            'publish_up'   => '2020-01-01 00:00:00',
            'publish_down' => null,
            'created'      => '2024-01-01 10:00:00',
            'modified'     => '2025-05-05 10:00:00',
            'metadata'     => '{}',
            'language'     => '*',
        ];
    }

    /**
     * @param   array<string, mixed>  $overrides
     *
     * @return  array<string, mixed>
     */
    private static function menu(int $id, array $overrides = []): array
    {
        return $overrides + [
            'id'                => $id,
            'title'             => 'Menu ' . $id,
            'link'              => 'index.php?option=com_content&view=featured',
            'type'              => 'component',
            'home'              => 0,
            'access'            => 1,
            'language'          => '*',
            'params'            => '{}',
            'publish_up'        => null,
            'publish_down'      => null,
            'component'         => 'com_content',
            'component_enabled' => 1,
        ];
    }

    /**
     * @param   Candidate[]  $candidates
     *
     * @return  array<int, ?string>  id => exclusion reason
     */
    private static function reasons(array $candidates): array
    {
        $reasons = [];

        foreach ($candidates as $candidate) {
            $reasons[$candidate->id] = $candidate->excluded;
        }

        return $reasons;
    }

    public function testArticleRouteLastmodAndTitle(): void
    {
        [$article] = self::articles($this->builder(), [self::article(7)]);

        $this->assertSame(Candidate::TYPE_ARTICLE, $article->type);
        $this->assertSame('index.php?option=com_content&view=article&id=7:article-7&catid=2', $article->route);
        $this->assertSame('2025-05-05 10:00:00', $article->lastmod?->format('Y-m-d H:i:s'));
        $this->assertSame('Article 7', $article->title);
        $this->assertTrue($article->isIncluded());
    }

    public function testArticleLastmodFallsBackToCreated(): void
    {
        [$article] = self::articles($this->builder(), [self::article(7, ['modified' => '0000-00-00 00:00:00'])]);

        $this->assertSame('2024-01-01 10:00:00', $article->lastmod?->format('Y-m-d H:i:s'));
    }

    public function testArticleRules(): void
    {
        $reasons = self::reasons(self::articles($this->builder(), [
            self::article(1),
            self::article(2, ['state' => 0]),
            self::article(3, ['state' => 2]),
            self::article(4, ['publish_up' => '2027-01-01 00:00:00']),
            self::article(5, ['publish_down' => '2026-01-01 00:00:00']),
            self::article(6, ['access' => 3]),
            self::article(8, ['catid' => 3]),
            self::article(9, ['catid' => 99]),
            self::article(10, ['state' => -2]),
        ]));

        $this->assertSame([
            1  => null,
            2  => 'unpublished',
            3  => 'archived',
            4  => 'not published yet',
            5  => 'publishing ended',
            6  => 'not public (access level 3)',
            8  => 'category unpublished',
            9  => 'category #99 not found',
            10 => 'trashed',
        ], $reasons);
    }

    public function testArchivedArticlesCanBeIncluded(): void
    {
        [$article] = self::articles($this->builder(null, true), [self::article(3, ['state' => 2])]);

        $this->assertTrue($article->isIncluded());
    }

    public function testNoindexArticleBlocksItsUrl(): void
    {
        [$own, $site] = self::articles($this->builder(), [self::article(1, ['metadata' => '{"robots":"noindex, nofollow"}']), self::article(2)]);

        $this->assertSame('noindex', $own->excluded);
        $this->assertTrue($own->blocks);
        $this->assertTrue($site->isIncluded());

        [$fromSite] = self::articles($this->builder(null, false, 'noindex, follow'), [self::article(2)]);
        $this->assertTrue($fromSite->blocks);
    }

    public function testUnpublishedArticleDoesNotBlock(): void
    {
        [$article] = self::articles($this->builder(), [self::article(1, ['state' => 0, 'metadata' => '{"robots":"noindex"}'])]);

        $this->assertSame('unpublished', $article->excluded);
        $this->assertFalse($article->blocks);
    }

    public function testMultilingualRoutesCarryTheLanguage(): void
    {
        [$uk, $all, $ru] = self::articles($this->builder(['uk-UA', 'en-GB']), [
            self::article(1, ['language' => 'uk-UA']),
            self::article(2),
            self::article(3, ['language' => 'ru-RU']),
        ]);

        $this->assertStringEndsWith('&catid=2&lang=uk-UA', $uk->route);
        $this->assertStringEndsWith('&catid=2', $all->route);
        $this->assertSame('language ru-RU not published', $ru->excluded);
    }

    public function testCategoryLastmodIsTheLatestOfItselfAndItsListedArticles(): void
    {
        $candidates = $this->builder()->build([
            self::article(1, ['modified' => '2026-03-03 03:03:03']),
            self::article(2, ['modified' => '2026-05-05 05:05:05', 'state' => 0]),
            self::article(3, ['catid' => 4, 'modified' => '2019-01-01 00:00:00']),
        ], self::CATEGORIES, []);

        $categories = [];

        foreach ($candidates->categories as $category) {
            $categories[$category->id] = $category;
        }

        $this->assertSame('index.php?option=com_content&view=category&id=2', $categories[2]->route);
        $this->assertSame('2026-03-03 03:03:03', $categories[2]->lastmod?->format('Y-m-d H:i:s'));
        $this->assertSame('category unpublished', $categories[3]->excluded);
        $this->assertSame('noindex', $categories[4]->excluded);
        $this->assertTrue($categories[4]->blocks);
        $this->assertSame('2020-01-01 00:00:00', $categories[4]->lastmod?->format('Y-m-d H:i:s'));
    }

    public function testMenuItemRules(): void
    {
        $articles = [self::article(1), self::article(2, ['state' => 0]), self::article(3, ['metadata' => '{"robots":"noindex"}'])];

        $items   = $this->builder()->build($articles, self::CATEGORIES, [
            self::menu(101, ['home' => 1, 'access' => 3, 'params' => '{"robots":"noindex"}']),
            self::menu(102),
            self::menu(103, ['type' => 'url', 'link' => 'https://example.org']),
            self::menu(104, ['type' => 'separator', 'link' => '']),
            self::menu(105, ['component_enabled' => 0, 'component' => 'com_contact']),
            self::menu(106, ['component' => null, 'component_enabled' => null]),
            self::menu(107, ['access' => 2]),
            self::menu(108, ['publish_down' => '2026-01-01 00:00:00']),
            self::menu(109, ['params' => '{"robots":"noindex, follow"}']),
            self::menu(110, ['link' => 'index.php?option=com_content&view=article&id=1']),
            self::menu(111, ['link' => 'index.php?option=com_content&view=article&id=2']),
            self::menu(112, ['link' => 'index.php?option=com_content&view=article&id=404']),
            self::menu(113, ['link' => 'index.php?option=com_content&view=article&id=3']),
            self::menu(114, ['link' => 'index.php?option=com_content&view=category&layout=blog&id=3']),
            self::menu(115, ['link' => 'index.php?option=com_content&view=categories&id=0']),
            self::menu(116, ['language' => 'en-GB']),
            self::menu(117, ['link' => 'index.php?option=com_content&view=form&layout=edit&id=9']),
        ])->menuItems;
        $reasons = self::reasons($items);

        $this->assertSame([
            101 => null,
            102 => null,
            103 => 'menu item type url',
            104 => 'menu item type separator',
            105 => 'component com_contact disabled',
            106 => 'component not installed',
            107 => 'not public (access level 2)',
            108 => 'publishing ended',
            109 => 'noindex',
            110 => null,
            111 => 'article #2 unpublished',
            112 => 'article #404 not found',
            113 => null,
            114 => 'category unpublished',
            115 => null,
            116 => null,
            117 => null,
        ], $reasons);

        $this->assertTrue($items[0]->home);
        $this->assertSame('index.php?Itemid=101', $items[0]->route);
        $this->assertTrue($items[8]->blocks);
    }

    public function testMultilingualMenuItems(): void
    {
        $items = $this->builder(['uk-UA', 'en-GB'], false, '', 'uk-UA')->build([], self::CATEGORIES, [
            self::menu(101, ['home' => 1, 'language' => 'uk-UA']),
            self::menu(102, ['language' => 'en-GB']),
            self::menu(103, ['language' => 'ru-RU']),
            self::menu(104),
        ])->menuItems;

        $this->assertSame('index.php?Itemid=101&lang=uk-UA', $items[0]->route);
        $this->assertSame('index.php?Itemid=102&lang=en-GB', $items[1]->route);
        $this->assertSame('language ru-RU not published', $items[2]->excluded);
        $this->assertSame('index.php?Itemid=104&lang=uk-UA', $items[3]->route, 'all-language items use the default language');
    }

    public function testArticlesAreTakenOneAtATimeBeforeCategoriesAndMenuItems(): void
    {
        $read = 0;
        $rows = (static function () use (&$read): \Generator {
            foreach ([self::article(1), self::article(2, ['state' => 0])] as $row) {
                ++$read;

                yield $row;
            }
        })();

        $candidates = $this->builder()->candidates($rows, self::CATEGORIES, [
            self::menu(110, ['link' => 'index.php?option=com_content&view=article&id=2']),
        ]);

        $this->assertSame(0, $read, 'nothing is read before it is asked for');
        $this->assertSame('article #1 Article 1', $candidates->current()->describe());
        $this->assertSame(1, $read, 'articles are read one at a time');

        $all = iterator_to_array($candidates, false);

        $this->assertMatchesRegularExpression('/^(article )+(category )+menu $/', implode('', array_map(static fn (Candidate $c) => $c->type . ' ', $all)));
        $this->assertSame('article #2 unpublished', $all[\count($all) - 1]->excluded, 'menu items still see the articles they open');
    }
}
