<?php

namespace QrzLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Assembler;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Candidate;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Entry;
use QrzLite\Component\Sitemap\Administrator\Sitemap\PathFilter;

final class AssemblerTest extends TestCase
{
    /** Fake router: route => path */
    private const ROUTES = [
        'index.php?Itemid=101' => '/',
        'index.php?Itemid=102' => '/news.html',
        'index.php?Itemid=103' => '/about.html',
        'index.php?Itemid=104' => '/en/',
        'article-1'            => '/news/first.html',
        'article-2'            => '/news/second.html',
        'article-3'            => '/component/content/article/third?Itemid=101',
        'article-4'            => '/about.html',
        'article-home'         => '/',
        'category-2'           => '/news.html',
        'external'             => 'https://example.org/x',
        'relative'             => 'no-slash.html',
    ];

    private function assembler(string $filter = "/component/\n?view="): Assembler
    {
        $router = static function (string $route): ?string {
            if ($route === 'throws') {
                throw new \RuntimeException('broken router');
            }

            return self::ROUTES[$route] ?? null;
        };

        return new Assembler($router, PathFilter::fromText($filter), 'My Site');
    }

    private static function date(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    /**
     * @param   Entry[]  $entries
     *
     * @return  string[]
     */
    private static function paths(array $entries): array
    {
        return array_map(static fn (Entry $entry): string => $entry->path, $entries);
    }

    public function testRootIsAlwaysListedWithTheSiteName(): void
    {
        $result = $this->assembler()->assemble([]);

        $this->assertCount(1, $result->entries);
        $this->assertSame('/', $result->entries[0]->path);
        $this->assertSame('My Site', $result->entries[0]->title);
        $this->assertNull($result->entries[0]->lastmod);
    }

    public function testMergesSameUrlAndSortsByPath(): void
    {
        $result = $this->assembler()->assemble([
            new Candidate(Candidate::TYPE_ARTICLE, 2, 'Second', 'article-2', self::date('2026-02-02')),
            new Candidate(Candidate::TYPE_CATEGORY, 2, 'News category', 'category-2', self::date('2026-03-03')),
            new Candidate(Candidate::TYPE_MENU, 102, 'News', 'index.php?Itemid=102'),
            new Candidate(Candidate::TYPE_ARTICLE, 1, 'First', 'article-1', self::date('2026-01-01')),
            new Candidate(Candidate::TYPE_MENU, 101, 'Home', 'index.php?Itemid=101', null, null, false, true),
            new Candidate(Candidate::TYPE_ARTICLE, 99, 'Home article', 'article-home', self::date('2025-12-12')),
        ]);

        $this->assertSame(['/', '/news.html', '/news/first.html', '/news/second.html'], self::paths($result->entries));
        $this->assertSame('Home', $result->entries[0]->title);
        $this->assertSame('2025-12-12', $result->entries[0]->lastmod?->format('Y-m-d'));
        $this->assertSame('News', $result->entries[1]->title, 'the menu item title wins');
        $this->assertSame('2026-03-03', $result->entries[1]->lastmod?->format('Y-m-d'), 'the latest change wins');
        $this->assertSame([], $result->exclusions);
    }

    public function testFilterLeavesOutMatchingPathsButNeverHomePages(): void
    {
        $result = $this->assembler()->assemble([
            new Candidate(Candidate::TYPE_ARTICLE, 3, 'Third', 'article-3'),
            new Candidate(Candidate::TYPE_MENU, 104, 'Home EN', 'index.php?Itemid=104', null, null, false, true),
        ]);

        $this->assertSame(['/', '/en/'], self::paths($result->entries));
        $this->assertSame([['article #3 Third /component/content/article/third?Itemid=101', 'filter "/component/"']], $result->exclusions);

        $none = $this->assembler('/en/')->assemble([
            new Candidate(Candidate::TYPE_MENU, 104, 'Home EN', 'index.php?Itemid=104', null, null, false, true),
        ]);
        $this->assertSame(['/', '/en/'], self::paths($none->entries));
    }

    public function testNoindexBlocksUrlEvenWhenAnotherCandidateRoutesThere(): void
    {
        $result = $this->assembler()->assemble([
            new Candidate(Candidate::TYPE_MENU, 103, 'About', 'index.php?Itemid=103'),
            new Candidate(Candidate::TYPE_ARTICLE, 4, 'About article', 'article-4', null, 'noindex', true),
        ]);

        $this->assertSame(['/'], self::paths($result->entries));
        $this->assertSame([['article #4 About article /about.html', 'noindex']], $result->exclusions);
    }

    public function testNoindexNeverRemovesTheRoot(): void
    {
        $result = $this->assembler()->assemble([
            new Candidate(Candidate::TYPE_ARTICLE, 99, 'Home article', 'article-home', null, 'noindex', true),
        ]);

        $this->assertSame(['/'], self::paths($result->entries));
    }

    public function testExcludedCandidatesAreNotRouted(): void
    {
        $result = $this->assembler()->assemble([
            new Candidate(Candidate::TYPE_ARTICLE, 5, 'Draft', 'throws', null, 'unpublished'),
        ]);

        $this->assertSame([['article #5 Draft', 'unpublished']], $result->exclusions);
    }

    public function testRoutingProblemsAreReported(): void
    {
        $result = $this->assembler()->assemble([
            new Candidate(Candidate::TYPE_ARTICLE, 6, 'Broken', 'throws'),
            new Candidate(Candidate::TYPE_ARTICLE, 7, 'Unknown', 'unknown-route'),
            new Candidate(Candidate::TYPE_MENU, 8, 'External', 'external'),
            new Candidate(Candidate::TYPE_MENU, 9, 'Relative', 'relative'),
        ]);

        $this->assertSame(['/', '/no-slash.html'], self::paths($result->entries));
        $this->assertSame([
            ['article #6 Broken', 'routing failed: broken router'],
            ['article #7 Unknown', 'no URL'],
            ['menu #8 External', 'no URL'],
        ], $result->exclusions);
    }

    public function testEntryLevel(): void
    {
        $this->assertSame(1, (new Entry('/', ''))->level());
        $this->assertSame(1, (new Entry('/news.html', ''))->level());
        $this->assertSame(1, (new Entry('/en/', ''))->level());
        $this->assertSame(2, (new Entry('/news/first.html', ''))->level());
        $this->assertSame(3, (new Entry('/a/b/c?x=1/2', ''))->level());
    }

    public function testPathFilterParsesLinesAndIgnoresCase(): void
    {
        $filter = PathFilter::fromText("  /Component/ \r\n\r\n?view=\n/component/\n");

        $this->assertSame(['/Component/', '?view='], $filter->patterns());
        $this->assertSame('/Component/', $filter->match('/component/content'));
        $this->assertSame('?view=', $filter->match('/x?view=article'));
        $this->assertNull($filter->match('/news.html'));
        $this->assertSame('/Новини', (new PathFilter(['/Новини']))->match('/новини/x'));
    }
}
