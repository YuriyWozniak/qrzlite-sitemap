<?php

namespace QrzLite\Tests\Performance;

use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Assembler;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Candidate;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Entry;
use QrzLite\Component\Sitemap\Administrator\Sitemap\PathFilter;
use QrzLite\Component\Sitemap\Administrator\Sitemap\SitemapXml;
use QrzLite\Component\Sitemap\Administrator\Storage\FileWriter;
use QrzLite\Component\Sitemap\Administrator\Storage\Publisher;
use QrzLite\Tests\Support\Usage;

/**
 * CPU time and memory of the sitemap core without Joomla: merging and filtering the routed candidates, then
 * writing the XML files, for a site above the 50,000 URLs one file may hold.
 */
#[RequiresFunction('memory_reset_peak_usage')]
final class CoreTest extends TestCase
{
    private const URLS = 120000;

    /** Microseconds of CPU time per URL. */
    private const CPU_BUDGET = 100;

    /** Bytes of memory per URL. */
    private const MEMORY_BUDGET = 4096;

    private string $folder;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/qrzlite-sitemap-core-' . getmypid();
        @mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->folder . '/*') ?: []);
        @rmdir($this->folder);
    }

    /**
     * @return  \Generator<Candidate>
     */
    private static function candidates(): \Generator
    {
        $lastmod = new \DateTimeImmutable('2025-01-01 00:00:00', new \DateTimeZone('UTC'));

        for ($id = 1; $id < self::URLS; $id++) {
            yield new Candidate(Candidate::TYPE_ARTICLE, $id, 'Article ' . $id, 'article-' . $id, $lastmod->modify('+' . $id . ' minutes'));
        }

        // A filtered one.
        yield new Candidate(Candidate::TYPE_ARTICLE, self::URLS, 'Hidden', 'hidden/article');
    }

    public function testAssemblingAndWritingStayWithinTheBudget(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        [$published, $usage] = Usage::of(function () use ($now) {
            $assembly = (new Assembler(static fn (string $route) => '/news/' . $route, PathFilter::fromText('/news/hidden/'), 'Site'))
                ->assemble(self::candidates());

            return (new Publisher(new FileWriter(), new SitemapXml('weekly', '0.5'), Publisher::MAX_URLS))
                ->publish($this->folder, 'sitemap', 'https://example.com', $assembly->entries, $now);
        });

        fwrite(STDERR, \PHP_EOL . 'Core: ' . $usage->describe(self::URLS));

        $this->assertSame(['sitemap_1.xml', 'sitemap_2.xml', 'sitemap_3.xml', 'sitemap.xml'], array_map('basename', $published->files));
        $this->assertLessThan(self::CPU_BUDGET * self::URLS / 1e6, $usage->cpu, 'CPU time over the budget');
        $this->assertLessThan(self::MEMORY_BUDGET * self::URLS, $usage->memory, 'Memory over the budget');
    }

    public function testWritingAFileTakesTimeInProportionToItsSize(): void
    {
        $entries = [];

        for ($id = 1; $id <= Publisher::MAX_URLS; $id++) {
            $entries[] = new Entry('/news/article-' . $id, 'Article ' . $id, new \DateTimeImmutable('2025-01-01 00:00:00'));
        }

        $xml   = new SitemapXml('weekly', '0.5');
        $time  = static fn (array $entries): float => Usage::of(static fn () => $xml->urlset('https://example.com', $entries))[1]->cpu;
        $small = min($time(\array_slice($entries, 0, 5000)), $time(\array_slice($entries, 0, 5000)));
        $large = $time($entries);

        fwrite(STDERR, \sprintf('%sXML file: 5,000 URLs %.2f s, 50,000 URLs %.2f s', \PHP_EOL, $small, $large));

        // Ten times the URLs: about ten times the time. Time that grows with the square of the size gives about 100.
        $this->assertLessThan(30, $large / max(0.016, $small), 'Writing a file slows down as it grows');
    }
}
