<?php

namespace QrzLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Entry;
use QrzLite\Component\Sitemap\Administrator\Sitemap\SitemapXml;

final class SitemapXmlTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/';

    private static function validate(string $xml, string $schema): void
    {
        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml), 'well-formed XML');

        libxml_use_internal_errors(true);
        $valid  = $dom->schemaValidate(self::FIXTURES . $schema);
        $errors = array_map(static fn ($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        self::assertTrue($valid, implode("\n", $errors));
    }

    /**
     * @return  Entry[]
     */
    private static function entries(): array
    {
        return [
            new Entry('/', 'Home', new \DateTimeImmutable('2026-01-02 03:04:05', new \DateTimeZone('UTC'))),
            new Entry('/news.html', 'News'),
            new Entry('/новини/стаття.html', 'Cyrillic', new \DateTimeImmutable('2026-01-02T05:04:05+02:00')),
            new Entry('/search?q=a&b=c d', 'Query'),
        ];
    }

    public function testUrlsetIsValidAgainstTheOfficialSchema(): void
    {
        $xml = (new SitemapXml('weekly', '0.5', 'https://example.com/media/x.xsl?a=1&b=2', new \DateTimeImmutable('2026-06-01', new \DateTimeZone('UTC'))))
            ->urlset('https://example.com/', self::entries());

        self::validate($xml, 'sitemap.xsd');

        $this->assertStringContainsString('<?xml-stylesheet type="text/xsl" href="https://example.com/media/x.xsl?a=1&amp;b=2"?>', $xml);
        $this->assertStringContainsString('<!-- QrzLite Sitemap 2026-06-01', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
    }

    public function testUrlElements(): void
    {
        $xml  = (new SitemapXml('weekly', '0.5'))->urlset('https://example.com', self::entries());
        $urls = simplexml_load_string($xml)->url;

        $this->assertCount(4, $urls);
        $this->assertSame('https://example.com/', (string) $urls[0]->loc);
        $this->assertSame('2026-01-02T03:04:05+00:00', (string) $urls[0]->lastmod);
        $this->assertSame('weekly', (string) $urls[0]->changefreq);
        $this->assertSame('0.5', (string) $urls[0]->priority);
        $this->assertSame(['loc', 'lastmod', 'changefreq', 'priority'], array_keys((array) $urls[0]));
        $this->assertFalse(isset($urls[1]->lastmod));
        $this->assertSame(
            'https://example.com/%D0%BD%D0%BE%D0%B2%D0%B8%D0%BD%D0%B8/%D1%81%D1%82%D0%B0%D1%82%D1%82%D1%8F.html',
            (string) $urls[2]->loc
        );
        $this->assertSame('2026-01-02T03:04:05+00:00', (string) $urls[2]->lastmod, 'lastmod is written in UTC');
        $this->assertSame('https://example.com/search?q=a&b=c%20d', (string) $urls[3]->loc);
        $this->assertStringContainsString('<loc>https://example.com/search?q=a&amp;b=c%20d</loc>', $xml);
    }

    public function testOptionalElementsAndCommentsCanBeLeftOut(): void
    {
        $xml = (new SitemapXml())->urlset('https://example.com', [new Entry('/', 'Home')]);

        self::validate($xml, 'sitemap.xsd');
        $this->assertStringNotContainsString('changefreq', $xml);
        $this->assertStringNotContainsString('priority', $xml);
        $this->assertStringNotContainsString('xml-stylesheet', $xml);
        $this->assertStringNotContainsString('<!--', $xml);
    }

    public function testPriorityIsNormalised(): void
    {
        $xml = (new SitemapXml('', '1'))->urlset('https://example.com', [new Entry('/', '')]);

        $this->assertStringContainsString('<priority>1.0</priority>', $xml);
    }

    public function testInvalidOptionsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SitemapXml('sometimes');
    }

    public function testInvalidPriorityIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SitemapXml('', '1.5');
    }

    public function testIndexIsValidAgainstTheOfficialSchema(): void
    {
        $xml = (new SitemapXml('daily', '0.8', 'https://example.com/s.xsl'))
            ->index(['https://example.com/sitemap_1.xml', 'https://example.com/sitemap_2.xml'], new \DateTimeImmutable('2026-06-01 10:00:00', new \DateTimeZone('UTC')));

        self::validate($xml, 'siteindex.xsd');
        $this->assertSame(2, substr_count($xml, '<lastmod>2026-06-01T10:00:00+00:00</lastmod>'));
    }

    public function testLocKeepsValidEscapesAndEncodesTheRest(): void
    {
        $this->assertSame('https://example.com/a%20b/100%25/%2F', SitemapXml::loc('https://example.com/', '/a b/100%/%2F'));
        $this->assertSame('https://example.com/sub/x.html', SitemapXml::loc('https://example.com/sub', '/x.html'));
        $this->assertSame('https://example.com/%22%3C%3E%5C%5E%60%7B%7C%7D', SitemapXml::loc('https://example.com', '/"<>\\^`{|}'));
    }
}
