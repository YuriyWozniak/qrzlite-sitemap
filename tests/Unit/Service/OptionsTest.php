<?php

namespace QrzLite\Tests\Unit\Service;

use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;
use QrzLite\Component\Sitemap\Administrator\Service\Options;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Publisher;

final class OptionsTest extends TestCase
{
    public function testDefaultsUntilTheOptionsAreSaved(): void
    {
        $this->assertEquals(new Options(), Options::fromRegistry(new Registry()));

        $options = new Options();
        $this->assertSame('sitemap', $options->filename);
        $this->assertSame('', $options->siteUrl);
        $this->assertTrue($options->includeMenu && $options->includeCategories && $options->includeArticles);
        $this->assertFalse($options->includeArchived);
        $this->assertSame("/component/\n?view=", $options->exclude);
        $this->assertSame('weekly', $options->changefreq);
        $this->assertSame('0.5', $options->priority);
        $this->assertTrue($options->stylesheet);
        $this->assertSame(Publisher::MAX_URLS, $options->splitLimit);
    }

    public function testSavedOptions(): void
    {
        $options = Options::fromRegistry(new Registry([
            'filename'           => 'map',
            'site_url'           => ' https://example.com/ ',
            'include_menu'       => '0',
            'include_categories' => '0',
            'include_articles'   => '0',
            'include_archived'   => '1',
            'exclude'            => " /a/\n/b/ ",
            'changefreq'         => 'daily',
            'priority'           => '0.8',
            'xsl'                => '0',
            'split_limit'        => '1000',
        ]));

        $this->assertEquals(
            new Options('map', 'https://example.com/', false, false, false, true, "/a/\n/b/", 'daily', '0.8', false, 1000),
            $options
        );
    }

    public function testTextOptionsSavedEmptyStayEmpty(): void
    {
        $options = Options::fromRegistry(new Registry(['filename' => '', 'exclude' => '', 'changefreq' => '', 'priority' => ' ']));

        $this->assertSame('sitemap', $options->filename);
        $this->assertSame('', $options->exclude);
        $this->assertSame('', $options->changefreq);
        $this->assertSame('', $options->priority);
    }

    public function testOverridesReplaceTheSavedOptionsEvenWhenEmpty(): void
    {
        $saved   = new Registry(['filename' => 'map', 'changefreq' => 'daily', 'include_articles' => 1]);
        $options = Options::fromRegistry($saved, new Registry(['changefreq' => '', 'include_articles' => 0]));

        $this->assertSame('map', $options->filename);
        $this->assertSame('', $options->changefreq);
        $this->assertFalse($options->includeArticles);
        $this->assertSame('daily', $saved->get('changefreq'), 'The saved options are not changed');
    }
}
