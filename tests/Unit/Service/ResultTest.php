<?php

namespace QrzLite\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use QrzLite\Component\Sitemap\Administrator\Service\Result;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Entry;

final class ResultTest extends TestCase
{
    public function testSummaryOfADryRun(): void
    {
        $result = new Result('https://example.com', [new Entry('/', 'Home', null)], [['a', 'b'], ['c', 'd']], [], '', [], [], false, 0.04);

        $this->assertSame(1, $result->count());
        $this->assertSame('1 URLs, 2 left out in 0.0 s', $result->summary());
    }

    public function testSummaryNamesTheFilesAndWhatCouldNotBeDeleted(): void
    {
        $result = new Result(
            'https://example.com',
            [],
            [],
            ['/site/sitemap_1.xml', '/site/sitemap.xml'],
            'in place',
            ['/site/sitemap_3.xml'],
            ['/site/sitemap_4.xml'],
            true,
            1.26
        );

        $this->assertSame(
            '0 URLs, 0 left out, written to sitemap_1.xml, sitemap.xml (in place), could not delete sitemap_4.xml in 1.3 s',
            $result->summary()
        );
    }
}
