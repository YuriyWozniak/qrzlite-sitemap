<?php

namespace QrzLite\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Context;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Rules;

final class RulesTest extends TestCase
{
    private function context(?array $languages = null, bool $archived = false): Context
    {
        return new Context([1, 5], new \DateTimeImmutable('2026-06-15 12:00:00', new \DateTimeZone('UTC')), '', $languages, $archived);
    }

    public function testDateParsesUtcAndRejectsEmptyZeroAndInvalid(): void
    {
        $date = Rules::date('2026-01-02 03:04:05');

        $this->assertSame('2026-01-02T03:04:05+00:00', $date?->format(DATE_ATOM));
        $this->assertNull(Rules::date(null));
        $this->assertNull(Rules::date(''));
        $this->assertNull(Rules::date('0000-00-00 00:00:00'));
        $this->assertNull(Rules::date('2026-13-45 99:00:00'));
        $this->assertNull(Rules::date('yesterday'));
    }

    public function testRobotsReadsJsonAndToleratesGarbage(): void
    {
        $this->assertSame('noindex, follow', Rules::robots('{"robots":"noindex, follow","author":""}'));
        $this->assertSame('', Rules::robots('{"robots":""}'));
        $this->assertSame('', Rules::robots(''));
        $this->assertSame('', Rules::robots('not json'));
        $this->assertSame('', Rules::robots('{"robots":["noindex"]}'));
    }

    public static function noindexCases(): array
    {
        return [
            'own noindex'             => ['noindex, follow', '', true],
            'own wins over site'      => ['index, follow', 'noindex, nofollow', false],
            'site applies when empty' => ['', 'noindex, nofollow', true],
            'case-insensitive'        => ['NOINDEX', '', true],
            'word boundary'           => ['noindexing', '', false],
            'nothing set'             => ['', '', false],
        ];
    }

    #[DataProvider('noindexCases')]
    public function testNoindex(string $own, string $site, bool $expected): void
    {
        $this->assertSame($expected, Rules::noindex($own, $site) !== null);
    }

    public function testPublishingWindow(): void
    {
        $now = $this->context()->now;

        $this->assertNull(Rules::window(null, null, $now));
        $this->assertNull(Rules::window('2026-06-15 12:00:00', '0000-00-00 00:00:00', $now));
        $this->assertSame('not published yet', Rules::window('2026-06-15 12:00:01', null, $now));
        $this->assertSame('publishing ended', Rules::window(null, '2026-06-15 12:00:00', $now));
        $this->assertNull(Rules::window('2020-01-01 00:00:00', '2030-01-01 00:00:00', $now));
    }

    public function testAccessUsesGuestLevels(): void
    {
        $this->assertNull(Rules::access(1, $this->context()));
        $this->assertNull(Rules::access(5, $this->context()));
        $this->assertSame('not public (access level 2)', Rules::access(2, $this->context()));
    }

    public function testLanguageOnlyMattersOnMultilingualSites(): void
    {
        $this->assertNull(Rules::language('ru-RU', $this->context()));
        $this->assertNull(Rules::language('*', $this->context(['uk-UA'])));
        $this->assertNull(Rules::language('uk-UA', $this->context(['uk-UA', 'en-GB'])));
        $this->assertSame('language ru-RU not published', Rules::language('ru-RU', $this->context(['uk-UA'])));
    }

    public function testStates(): void
    {
        $this->assertNull(Rules::state(1, $this->context()));
        $this->assertSame('archived', Rules::state(2, $this->context()));
        $this->assertNull(Rules::state(2, $this->context(null, true)));
        $this->assertSame('unpublished', Rules::state(0, $this->context()));
        $this->assertSame('trashed', Rules::state(-2, $this->context(null, true)));
    }

    public function testLatest(): void
    {
        $a = new \DateTimeImmutable('2026-01-01');
        $b = new \DateTimeImmutable('2026-02-01');

        $this->assertSame($b, Rules::latest($a, $b));
        $this->assertSame($b, Rules::latest($b, $a));
        $this->assertSame($a, Rules::latest(null, $a));
        $this->assertSame($a, Rules::latest($a, null));
        $this->assertNull(Rules::latest(null, null));
    }

    public function testLanguageSuffix(): void
    {
        $this->assertSame('', $this->context()->languageSuffix('uk-UA'));
        $this->assertSame('', $this->context(['uk-UA'])->languageSuffix('*'));
        $this->assertSame('&lang=uk-UA', $this->context(['uk-UA'])->languageSuffix('uk-UA'));

        $default = new Context([1], new \DateTimeImmutable(), '', ['uk-UA', 'en-GB'], false, 'uk-UA');
        $this->assertSame('&lang=uk-UA', $default->languageSuffix('*'));
        $this->assertSame('&lang=en-GB', $default->languageSuffix('en-GB'));
    }
}
