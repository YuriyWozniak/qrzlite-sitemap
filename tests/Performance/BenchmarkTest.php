<?php

namespace QrzLite\Tests\Performance;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Version;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Tests\Support\JoomlaTestCase;
use QrzLite\Tests\Support\LargeSite;
use QrzLite\Tests\Support\Usage;

/**
 * Measures a whole generation at several site sizes and reports a table (composer benchmark). Runs only when
 * QRZLITE_BENCHMARK is set: to the numbers of articles, e.g. "1000,10000,50000", or to 1 for the default sizes.
 * The table goes to the error output and to .cache/benchmark.md.
 */
#[RequiresFunction('memory_reset_peak_usage')]
final class BenchmarkTest extends JoomlaTestCase
{
    use LargeSite;

    private const SIZES = [1000, 10000, 50000, 100000];

    /** @var array<int, string> */
    private static array $rows = [];

    /**
     * @return  array<string, array{int}>
     */
    public static function sizes(): array
    {
        $setting = (string) getenv('QRZLITE_BENCHMARK');
        $sizes   = $setting === '' || $setting === '1' ? self::SIZES : array_map('intval', explode(',', $setting));

        return array_combine(
            array_map(static fn (int $size): string => number_format($size) . ' articles', $sizes),
            array_map(static fn (int $size): array => [$size], $sizes)
        );
    }

    protected function setUp(): void
    {
        // First: tearDown() restores what this saves, also after a skip.
        parent::setUp();

        if ((string) getenv('QRZLITE_BENCHMARK') === '') {
            $this->markTestSkipped('Set QRZLITE_BENCHMARK to run the benchmark (composer benchmark)');
        }

        $this->component(SitemapComponent::NAME);
        $this->app(AdministratorApplication::class, ['live_site' => self::SITE_URL]);
    }

    #[DataProvider('sizes')]
    public function testGeneration(int $articles): void
    {
        $this->seedLargeSite($articles);

        // The services are made before measuring: Joomla boots the component once per request anyway.
        $generator        = $this->sitemapFactory()->createGenerator();
        [$result, $usage] = Usage::of(static fn () => $generator->generate());
        $urls             = $result->count();

        $this->assertSame($this->largeSiteUrls(), $urls);

        self::$rows[] = \sprintf(
            '| %s | %s | %d | %.2f s | %.2f s | %d µs | %.1f MB | %.1f KB |',
            number_format($articles),
            number_format($urls),
            \count($result->files),
            $usage->wall,
            $usage->cpu,
            (int) round($usage->cpu / $urls * 1e6),
            $usage->memory / 1048576,
            $usage->memory / $urls / 1024
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$rows === []) {
            return;
        }

        $table = implode(\PHP_EOL, array_merge(
            [
                \sprintf('Generation on PHP %s, Joomla %s libraries, SQLite in memory, routes from PathRouter', \PHP_VERSION, (new Version())->getShortVersion()),
                '',
                '| Articles | URLs | Files | Wall | CPU | CPU per URL | Peak memory | Memory per URL |',
                '|---:|---:|---:|---:|---:|---:|---:|---:|',
            ],
            self::$rows
        )) . \PHP_EOL;

        $folder = \dirname(__DIR__, 2) . '/.cache';
        @mkdir($folder);
        file_put_contents($folder . '/benchmark.md', $table);
        fwrite(STDERR, \PHP_EOL . \PHP_EOL . $table);
    }
}
