<?php

namespace QrzLite\Tests\Unit\Service;

use Joomla\CMS\Application\AdministratorApplication;
use PHPUnit\Framework\Attributes\DataProvider;
use QrzLite\Component\Sitemap\Administrator\Service\SiteEnvironment;
use QrzLite\Tests\Support\JoomlaTestCase;

final class SiteEnvironmentTest extends JoomlaTestCase
{
    /**
     * @param   array<string, mixed>  $config  Global configuration.
     */
    private function site(array $config = []): SiteEnvironment
    {
        return new SiteEnvironment($this->app(AdministratorApplication::class, $config), JPATH_ROOT);
    }

    public function testWhatTheSiteSays(): void
    {
        $site = $this->site(['robots' => 'noindex, follow', 'sitename' => 'My Site']);

        $this->assertSame(JPATH_ROOT, $site->root);
        $this->assertSame([1, 5], $site->guestLevels());
        $this->assertSame('noindex, follow', $site->robots());
        $this->assertSame('My Site', $site->siteName());
        $this->assertSame('en-GB', $site->defaultLanguage());
        $this->assertFalse($site->isMultilingual());

        $this->plugin('system', 'languagefilter');
        $this->component('com_languages', ['site' => 'uk-UA']);

        $this->assertTrue($site->isMultilingual());
        $this->assertSame('uk-UA', $site->defaultLanguage());
    }

    public function testSiteUrlComesFromTheOptionThenLiveSiteThenTheRequest(): void
    {
        $site = $this->site(['live_site' => self::SITE_URL . '/']);

        $this->assertSame('https://www.example.org/sub', $site->siteUrl(' https://www.example.org/sub/ '));
        $this->assertSame(self::SITE_URL, $site->siteUrl());

        $site                   = $this->site();
        $_SERVER['HTTPS']       = 'on';
        $_SERVER['HTTP_HOST']   = 'host.test';
        $_SERVER['REQUEST_URI'] = '/site/';
        $_SERVER['PHP_SELF']    = '/site/index.php';
        $_SERVER['SCRIPT_NAME'] = '/site/index.php';

        $this->assertSame('https://host.test/site', $site->siteUrl());
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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The site URL is not known');

        $this->site()->siteUrl($url);
    }
}
