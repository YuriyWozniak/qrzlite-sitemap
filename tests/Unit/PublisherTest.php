<?php

namespace QrzLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Entry;
use QrzLite\Component\Sitemap\Administrator\Sitemap\FileWriter;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Publisher;
use QrzLite\Component\Sitemap\Administrator\Sitemap\SitemapXml;

final class PublisherTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/qrzlite-sitemap-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @chmod($this->dir, 0777);

        foreach (scandir($this->dir) ?: [] as $file) {
            if (is_file($this->dir . '/' . $file)) {
                unlink($this->dir . '/' . $file);
            }
        }

        rmdir($this->dir);
    }

    /**
     * @return  Entry[]
     */
    private static function entries(int $count): array
    {
        $entries = [new Entry('/', 'Home')];

        for ($i = 1; $i < $count; $i++) {
            $entries[] = new Entry('/page-' . $i . '.html', 'Page ' . $i);
        }

        return $entries;
    }

    private function publisher(int $limit = 50000): Publisher
    {
        return new Publisher(new FileWriter(), new SitemapXml('weekly', '0.5'), $limit);
    }

    /**
     * @return  string[]
     */
    private function files(): array
    {
        $files = array_map('basename', glob($this->dir . '/*') ?: []);
        sort($files);

        return $files;
    }

    public function testSingleFile(): void
    {
        $result = $this->publisher()->publish($this->dir, 'sitemap', 'https://example.com/', self::entries(3), new \DateTimeImmutable());

        $this->assertSame(['sitemap.xml'], $this->files());
        $this->assertSame(FileWriter::ATOMIC, $result['mode']);
        $this->assertSame([$this->dir . '/sitemap.xml'], $result['files']);
        $this->assertSame(3, substr_count(file_get_contents($this->dir . '/sitemap.xml'), '<url>'));
        $this->assertStringContainsString('<loc>https://example.com/page-2.html</loc>', file_get_contents($this->dir . '/sitemap.xml'));
    }

    public function testSplitIntoIndexAndParts(): void
    {
        $result = $this->publisher(2)->publish($this->dir, 'map', 'https://example.com', self::entries(5), new \DateTimeImmutable());

        $this->assertSame(['map.xml', 'map_1.xml', 'map_2.xml', 'map_3.xml'], $this->files());
        $this->assertCount(4, $result['files']);
        $this->assertSame($this->dir . '/map.xml', end($result['files']), 'the index is written last');

        $index = simplexml_load_file($this->dir . '/map.xml');
        $this->assertSame('sitemapindex', $index->getName());
        $this->assertSame('https://example.com/map_3.xml', (string) $index->sitemap[2]->loc);
        $this->assertSame(1, substr_count(file_get_contents($this->dir . '/map_3.xml'), '<url>'));
    }

    public function testLeftoverPartsAreDeleted(): void
    {
        $this->publisher(2)->publish($this->dir, 'sitemap', 'https://example.com', self::entries(5), new \DateTimeImmutable());
        file_put_contents($this->dir . '/sitemap_extra.xml', 'not ours');
        file_put_contents($this->dir . '/sitemap_old_9.xml', 'not ours either');

        $result = $this->publisher(2)->publish($this->dir, 'sitemap', 'https://example.com', self::entries(3), new \DateTimeImmutable());
        $this->assertSame(['sitemap.xml', 'sitemap_1.xml', 'sitemap_2.xml', 'sitemap_extra.xml', 'sitemap_old_9.xml'], $this->files());
        $this->assertSame([$this->dir . '/sitemap_3.xml'], $result['deleted']);

        $this->publisher()->publish($this->dir, 'sitemap', 'https://example.com', self::entries(3), new \DateTimeImmutable());
        $this->assertSame(['sitemap.xml', 'sitemap_extra.xml', 'sitemap_old_9.xml'], $this->files());
        $this->assertSame('urlset', simplexml_load_file($this->dir . '/sitemap.xml')->getName());
    }

    public function testEmptySitemapKeepsTheOldFile(): void
    {
        file_put_contents($this->dir . '/sitemap.xml', 'old');

        try {
            $this->publisher()->publish($this->dir, 'sitemap', 'https://example.com', [], new \DateTimeImmutable());
            $this->fail('An empty sitemap must not be written');
        } catch (\RuntimeException) {
            $this->assertSame('old', file_get_contents($this->dir . '/sitemap.xml'));
        }
    }

    public function testInvalidNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->publisher()->publish($this->dir, '../sitemap', 'https://example.com', self::entries(1), new \DateTimeImmutable());
    }

    public function testInvalidBaseUrlIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->publisher()->publish($this->dir, 'sitemap', 'https:///no-host', self::entries(1), new \DateTimeImmutable());
    }

    public function testRelativeBaseUrlIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->publisher()->publish($this->dir, 'sitemap', 'cli/', self::entries(1), new \DateTimeImmutable());
    }

    public function testAtomicWriteKeepsPermissionsAndLeavesNoTemporaryFiles(): void
    {
        $file = $this->dir . '/sitemap.xml';
        file_put_contents($file, 'old');
        chmod($file, 0664);

        $mode = (new FileWriter())->write($file, 'new');

        $this->assertSame(FileWriter::ATOMIC, $mode);
        $this->assertSame('new', file_get_contents($file));
        $this->assertSame(['sitemap.xml'], $this->files());

        if (DIRECTORY_SEPARATOR === '/') {
            clearstatcache();
            $this->assertSame(0664, fileperms($file) & 0777);
        }
    }

    public function testInPlaceWriteReplacesTheWholeContent(): void
    {
        $file = $this->dir . '/sitemap.xml';
        file_put_contents($file, str_repeat('x', 1000));

        $this->assertSame(FileWriter::IN_PLACE, (new FileWriter())->write($file, 'short', FileWriter::IN_PLACE));
        $this->assertSame('short', file_get_contents($file));
    }

    public function testReadOnlyFolderFallsBackToInPlace(): void
    {
        if (DIRECTORY_SEPARATOR !== '/' || (\function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            $this->markTestSkipped('Needs POSIX permissions and a non-root user');
        }

        $file = $this->dir . '/sitemap.xml';
        file_put_contents($file, 'old');
        chmod($this->dir, 0555);

        $writer = new FileWriter();
        $this->assertSame(FileWriter::IN_PLACE, $writer->write($file, 'new'));
        $this->assertSame('new', file_get_contents($file));
    }

    public function testNothingWritable(): void
    {
        if (DIRECTORY_SEPARATOR !== '/' || (\function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            $this->markTestSkipped('Needs POSIX permissions and a non-root user');
        }

        chmod($this->dir, 0555);

        $this->expectException(\RuntimeException::class);
        (new FileWriter())->write($this->dir . '/sitemap.xml', 'new');
    }
}
