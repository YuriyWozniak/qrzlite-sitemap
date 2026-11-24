<?php

namespace QrzLite\Tests\Performance;

use Joomla\CMS\Application\AdministratorApplication;
use PHPUnit\Framework\Attributes\RequiresFunction;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Service\Result;
use QrzLite\Tests\Support\JoomlaTestCase;
use QrzLite\Tests\Support\LargeSite;
use QrzLite\Tests\Support\Usage;

/**
 * CPU time and memory of a whole generation through the component (content queries, rules, routing, XML
 * files, saved links), against the Joomla libraries with an SQLite database. Routing is PathRouter, so the
 * numbers are the component's own cost: Joomla's site router adds its own on a real site.
 *
 * The budgets are per URL and several times what a slow machine needs, so that they catch a change that makes
 * generation slower or hungrier by a factor, not noise.
 */
#[RequiresFunction('memory_reset_peak_usage')]
final class GenerationTest extends JoomlaTestCase
{
    use LargeSite;

    /** Microseconds of CPU time per URL. */
    private const CPU_BUDGET = 400;

    /** Bytes of memory per URL. */
    private const MEMORY_BUDGET = 8192;

    protected function setUp(): void
    {
        parent::setUp();

        $this->component(SitemapComponent::NAME);
        $this->app(AdministratorApplication::class, ['live_site' => self::SITE_URL]);
    }

    /**
     * @return  array{0: Result, 1: Usage}
     */
    private function generate(bool $write): array
    {
        return Usage::of(fn () => $this->sitemapFactory()->createGenerator()->generate($write));
    }

    private static function report(string $name, Usage $usage, int $urls): void
    {
        fwrite(STDERR, \PHP_EOL . $name . ': ' . $usage->describe($urls));
    }

    public function testALargeSiteStaysWithinTheBudget(): void
    {
        $this->seedLargeSite(20000);

        [$result, $usage] = $this->generate(true);
        $urls             = $this->largeSiteUrls();
        self::report('Generation', $usage, $urls);

        $this->assertSame($urls, $result->count());
        $this->assertCount(1, $result->files);
        $this->assertTrue($result->stored);
        $this->assertLessThan(self::CPU_BUDGET * $urls / 1e6, $usage->cpu, 'CPU time over the budget');
        $this->assertLessThan(self::MEMORY_BUDGET * $urls, $usage->memory, 'Memory over the budget');
    }

    public function testTheCostGrowsInProportionToTheSite(): void
    {
        $this->seedLargeSite(2000);
        [$small, $smallUsage] = $this->generate(false);

        $this->addArticles(18000);
        [$large, $largeUsage] = $this->generate(false);

        self::report('Dry run', $smallUsage, $small->count());
        self::report('Dry run', $largeUsage, $large->count());

        $this->assertSame($this->largeSiteUrls(), $large->count());

        // Proportional cost gives about 1; anything that grows with the square of the URLs gives about 10.
        $cpuGrowth    = ($largeUsage->cpu / $large->count()) / max(1e-6, $smallUsage->cpu / $small->count());
        $memoryGrowth = ($largeUsage->memory / $large->count()) / ($smallUsage->memory / $small->count());

        $this->assertLessThan(3, $cpuGrowth, 'CPU time per URL grows with the size of the site');
        $this->assertLessThan(2, $memoryGrowth, 'Memory per URL grows with the size of the site');
    }
}
