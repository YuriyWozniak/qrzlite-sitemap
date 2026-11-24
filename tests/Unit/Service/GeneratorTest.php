<?php

namespace QrzLite\Tests\Unit\Service;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Service\Generator;
use QrzLite\Component\Sitemap\Administrator\Storage\LinkRepository;
use QrzLite\Tests\Support\JoomlaTestCase;
use QrzLite\Tests\Support\SiteContent;

final class GeneratorTest extends JoomlaTestCase
{
    use SiteContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSite();
        $this->component(SitemapComponent::NAME);
        $this->app(AdministratorApplication::class, ['live_site' => self::SITE_URL]);
    }

    /**
     * @return  string[]  Paths of the URLs in an urlset file.
     */
    private static function paths(string $file): array
    {
        $xml = simplexml_load_file($file);
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return array_map(static fn ($loc) => substr((string) $loc, \strlen(self::SITE_URL)), $xml->xpath('//s:loc'));
    }

    /**
     * @param   array<string, mixed>  $options
     */
    private function options(array $options): void
    {
        $this->component(SitemapComponent::NAME, $options);
    }

    public function testGenerateWritesTheSitemapAndSavesTheLinks(): void
    {
        $result = Generator::create()->generate();
        $file   = JPATH_ROOT . '/sitemap.xml';

        $this->assertSame(self::SITE_URL, $result->siteUrl);
        $this->assertCount(1, $result->files);
        $this->assertSame(realpath($file), realpath($result->files[0]));
        $this->assertSame('atomic', $result->mode);
        $this->assertTrue($result->stored);
        $this->assertSame(4, $result->count());
        $this->assertSame(self::sitePaths(), self::paths($file));

        $xml = (string) file_get_contents($file);
        $this->assertStringContainsString('<?xml-stylesheet type="text/xsl" href="' . self::SITE_URL . '/media/com_qrzlitesitemap/xsl/sitemap-en-GB.xsl"?>', $xml);
        $this->assertStringContainsString('<changefreq>weekly</changefreq>', $xml);
        $this->assertStringContainsString('<priority>0.5</priority>', $xml);
        $this->assertMatchesRegularExpression('#<loc>' . self::SITE_URL . '/news/second</loc>\s*<lastmod>2025-02-01T10:00:00\+00:00</lastmod>#', $xml);

        $this->assertSame(
            [
                ['/', 'Home', 1, null],
                ['/news', 'News', 1, '2025-02-01 10:00:00'],
                ['/news/first', 'First', 2, '2025-01-01 10:00:00'],
                ['/news/second', 'Second', 2, '2025-02-01 10:00:00'],
            ],
            array_map(static fn ($row) => [$row['path'], $row['title'], (int) $row['level'], $row['lastmod']], $this->rows(LinkRepository::TABLE))
        );

        $this->assertContains(['article #3 Draft', 'unpublished'], $result->exclusions);
        $this->assertContains(['article #6 Private', 'not public (access level 2)'], $result->exclusions);
        $this->assertNotContains('article #4 Trashed', array_column($result->exclusions, 0));
        $this->assertMatchesRegularExpression('#^4 URLs, \d+ left out, written to sitemap\.xml \(atomic\) in \d+\.\d s$#', $result->summary());
    }

    public function testDryRunWritesAndSavesNothing(): void
    {
        $this->insert(LinkRepository::TABLE, [['path' => '/old', 'title' => 'Old']]);

        $result = Generator::create()->generate(false);

        $this->assertSame(4, $result->count());
        $this->assertSame([], $result->files);
        $this->assertSame('', $result->mode);
        $this->assertFalse($result->stored);
        $this->assertFileDoesNotExist(JPATH_ROOT . '/sitemap.xml');
        $this->assertSame('/old', $this->rows(LinkRepository::TABLE)[0]['path']);
    }

    public function testAnotherFolderGetsTheFilesButTheLinksAreNotSaved(): void
    {
        $folder = JPATH_ROOT . '/out';
        mkdir($folder);

        $result = Generator::create()->generate(true, $folder);

        $this->assertFileExists($folder . '/sitemap.xml');
        $this->assertFileDoesNotExist(JPATH_ROOT . '/sitemap.xml');
        $this->assertFalse($result->stored);
        $this->assertSame([], $this->rows(LinkRepository::TABLE));
    }

    public function testOptionsAndOverrides(): void
    {
        $this->options(['filename' => 'map', 'changefreq' => 'daily', 'include_articles' => 0]);

        $result = Generator::create(new Registry(['include_categories' => 0, 'changefreq' => '', 'priority' => '', 'xsl' => 0]))->generate();
        $xml    = (string) file_get_contents(JPATH_ROOT . '/map.xml');

        $this->assertSame(['/', '/news'], self::paths(JPATH_ROOT . '/map.xml'));
        $this->assertSame('map', basename($result->files[0], '.xml'));
        $this->assertStringNotContainsString('xml-stylesheet', $xml);
        $this->assertStringNotContainsString('<changefreq>', $xml);
        $this->assertStringNotContainsString('<priority>', $xml);
    }

    public function testWithoutMenuItemsTheCategoryGivesItsTitle(): void
    {
        $this->options(['include_menu' => 0]);

        Generator::create()->generate();

        $this->assertSame(self::sitePaths(), self::paths(JPATH_ROOT . '/sitemap.xml'));
        $this->assertSame(['Test Site', 'News'], \array_slice(array_column($this->rows(LinkRepository::TABLE), 'title'), 0, 2));
    }

    public function testTheUrlFilterLeavesOutMatchingPaths(): void
    {
        $this->options(['exclude' => "/news/\n"]);

        $result = Generator::create()->generate(false);

        $this->assertSame(['/', '/news'], array_column($result->entries, 'path'));
        $this->assertContains(['article #1 First /news/first', 'filter "/news/"'], $result->exclusions);
    }

    public function testTheDefaultUrlFilterAppliesBeforeTheOptionsAreSaved(): void
    {
        $this->routes['index.php?option=com_content&view=article&id=1:first&catid=2'] = '/component/content/article/first';

        $this->assertSame(['/', '/news', '/news/second'], array_column(Generator::create()->generate(false)->entries, 'path'));

        // Saved empty: no filter at all.
        $this->options(['exclude' => '', 'changefreq' => '', 'priority' => '']);
        $result = Generator::create()->generate();
        $xml    = (string) file_get_contents($result->files[0]);

        $this->assertSame(['/', '/component/content/article/first', '/news', '/news/second'], array_column($result->entries, 'path'));
        $this->assertStringNotContainsString('<changefreq>', $xml);
        $this->assertStringNotContainsString('<priority>', $xml);
    }

    public function testArchivedArticlesWhenEnabled(): void
    {
        $this->insert('#__content', [['id' => 7, 'title' => 'Archived', 'alias' => 'archived', 'catid' => 2, 'state' => 2]]);
        $this->routes['index.php?option=com_content&view=article&id=7:archived&catid=2'] = '/news/archived';

        $this->assertNotContains('/news/archived', array_column(Generator::create()->generate(false)->entries, 'path'));

        $this->options(['include_archived' => 1]);
        $this->assertContains('/news/archived', array_column(Generator::create()->generate(false)->entries, 'path'));
    }

    public function testSiteWideNoindexLeavesOnlyTheHomePage(): void
    {
        $this->app(AdministratorApplication::class, ['live_site' => self::SITE_URL, 'robots' => 'noindex, nofollow']);

        $this->assertSame(['/'], array_column(Generator::create()->generate(false)->entries, 'path'));
    }

    public function testLargeSitemapsAreSplitAndSavedInBatches(): void
    {
        $rows = [];

        for ($id = 100; $id < 350; $id++) {
            $rows[]                                                                                        = ['id' => $id, 'title' => 'A' . $id, 'alias' => 'a' . $id, 'catid' => 2];
            $this->routes['index.php?option=com_content&view=article&id=' . $id . ':a' . $id . '&catid=2'] = '/news/a' . $id;
        }

        $this->insert('#__content', $rows);
        $this->options(['split_limit' => 200]);

        $result = Generator::create()->generate();

        $this->assertSame(254, $result->count());
        $this->assertSame(['sitemap_1.xml', 'sitemap_2.xml', 'sitemap.xml'], array_map('basename', $result->files));
        $this->assertCount(254, $this->rows(LinkRepository::TABLE));
        $this->assertStringContainsString('<sitemapindex', (string) file_get_contents(JPATH_ROOT . '/sitemap.xml'));
    }

    public function testFailedSavingKeepsThePreviousLinks(): void
    {
        $this->insert(LinkRepository::TABLE, [['path' => '/old', 'title' => 'Old']]);
        $this->db->setQuery(
            'CREATE TRIGGER no_insert BEFORE INSERT ON jos_qrzlitesitemap_links BEGIN SELECT RAISE(ABORT, \'no insert\'); END'
        )->execute();

        try {
            Generator::create()->generate();
            $this->fail('No exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no insert', $e->getMessage());
        }

        $this->assertSame(['/old'], array_column($this->rows(LinkRepository::TABLE), 'path'));
    }

    public function testStylesheetFollowsTheSiteLanguage(): void
    {
        $this->component('com_languages', ['site' => 'uk-UA']);
        $generated = static fn () => (string) file_get_contents(Generator::create()->generate()->files[0]);

        $this->assertStringContainsString('/xsl/sitemap-en-GB.xsl', $generated());

        mkdir(JPATH_ROOT . '/media/com_qrzlitesitemap/xsl', 0777, true);
        touch(JPATH_ROOT . '/media/com_qrzlitesitemap/xsl/sitemap-uk-UA.xsl');

        $this->assertStringContainsString('/xsl/sitemap-uk-UA.xsl', $generated());
    }

    public function testMultilingualSitesRouteItemsInTheirLanguage(): void
    {
        $this->plugin('system', 'languagefilter');
        $this->insert('#__languages', [['lang_code' => 'en-GB'], ['lang_code' => 'uk-UA']]);
        $this->insert('#__content', [
            ['id' => 8, 'title' => 'Ukrainian', 'alias' => 'uk', 'catid' => 2, 'language' => 'uk-UA'],
            ['id' => 9, 'title' => 'German', 'alias' => 'de', 'catid' => 2, 'language' => 'de-DE'],
        ]);
        $this->routes = [
            'index.php?Itemid=101&lang=en-GB'                                         => '/en/',
            'index.php?option=com_content&view=article&id=1:first&catid=2&lang=en-GB' => '/en/news/first',
            'index.php?option=com_content&view=article&id=8:uk&catid=2&lang=uk-UA'    => '/uk/news/uk',
        ];
        $this->options(['include_categories' => 0]);

        $result = Generator::create()->generate(false);
        $paths  = array_column($result->entries, 'path');

        $this->assertContains('/en/news/first', $paths);
        $this->assertContains('/uk/news/uk', $paths);
        $this->assertContains(['article #9 German', 'language de-DE not published'], $result->exclusions);
    }

    public function testFilenameDefaultsToSitemap(): void
    {
        $this->options(['filename' => '']);

        $this->assertSame('sitemap', Generator::create()->filename());
    }

    public function testSiteUrlComesFromTheOptionThenLiveSiteThenTheRequest(): void
    {
        $this->options(['site_url' => ' https://www.example.org/sub/ ']);
        $this->assertSame('https://www.example.org/sub', Generator::create()->siteUrl());

        $this->options([]);
        $this->assertSame(self::SITE_URL, Generator::create()->siteUrl());

        $this->app(AdministratorApplication::class);
        $_SERVER['HTTPS']       = 'on';
        $_SERVER['HTTP_HOST']   = 'host.test';
        $_SERVER['REQUEST_URI'] = '/site/';
        $_SERVER['PHP_SELF']    = '/site/index.php';
        $_SERVER['SCRIPT_NAME'] = '/site/index.php';

        $this->assertSame('https://host.test/site', Generator::create()->siteUrl());
    }

    /**
     * @return  array<string, array{string}>
     */
    public static function invalidSiteUrls(): array
    {
        return [
            'no scheme'      => ['example.com'],
            'other scheme'   => ['ftp://example.com'],
            'query'          => ['https://example.com/?a=1'],
            'space'          => ['https://exa mple.com'],
            'joomla.invalid' => ['https://joomla.invalid'],
        ];
    }

    #[DataProvider('invalidSiteUrls')]
    public function testInvalidSiteUrlsAreRefused(string $url): void
    {
        $this->options(['site_url' => $url]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The site URL is not known');

        Generator::create()->generate(false);
    }
}
