<?php

namespace QrzLite\Tests\Unit\Service;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Event\Application\AfterInitialiseEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use QrzLite\Component\Sitemap\Administrator\Service\RouteBuilder;
use QrzLite\Tests\Support\JoomlaTestCase;

final class RouteBuilderTest extends JoomlaTestCase
{
    public function testPathsAreRelativeToTheSiteRoot(): void
    {
        $this->app(AdministratorApplication::class, ['live_site' => 'https://example.com/sub']);
        $this->routes = [
            'index.php?Itemid=1' => '/news',
            'index.php?Itemid=2' => '/search?q=a%20b#top',
            'index.php?Itemid=3' => '/',
        ];

        $build = new RouteBuilder('https://example.com/sub');

        $this->assertSame('/sub', Uri::root(true));
        $this->assertSame('/news', $build('index.php?Itemid=1'));
        $this->assertSame('/search?q=a%20b#top', $build('index.php?Itemid=2'));
        $this->assertSame('/', $build('index.php?Itemid=3'));
    }

    public function testPathsOutsideTheSiteRootAreKept(): void
    {
        $this->app(AdministratorApplication::class, ['live_site' => 'https://example.com/sub']);

        $router = $this->createMock(SiteRouter::class);
        $router->method('getRules')->willReturn([]);
        $router->method('build')->willReturn(new Uri('/elsewhere/page'));

        $this->assertSame('/elsewhere/page', (new RouteBuilder('https://example.com/sub', $router))('index.php?Itemid=1'));
    }

    public function testTheConsoleGetsTheSitePathAsItsRequest(): void
    {
        $this->app(AdministratorApplication::class);
        $_SERVER['REQUEST_URI'] = '';

        new RouteBuilder('https://example.com/sub/');
        $this->assertSame('/sub/', $_SERVER['REQUEST_URI']);

        $_SERVER['REQUEST_URI'] = '';
        new RouteBuilder('https://example.com');
        $this->assertSame('/', $_SERVER['REQUEST_URI']);

        $_SERVER['REQUEST_URI'] = '/administrator/index.php';
        new RouteBuilder('https://example.com/sub');
        $this->assertSame('/administrator/index.php', $_SERVER['REQUEST_URI']);
    }

    public function testTheLanguageFilterIsStartedOnMultilingualSites(): void
    {
        $this->plugin('system', 'languagefilter');
        $site   = $this->siteApplication(new Registry());
        $filter = new LanguageFilterDouble();
        $app    = $this->app(AdministratorApplication::class);
        $app->expects($this->once())->method('bootPlugin')->with('languagefilter', 'system')->willReturn($filter);
        $this->container->share(SiteApplication::class, $site);

        new RouteBuilder(self::SITE_URL);

        $this->assertNotNull($filter->event);
        $this->assertSame($site, $filter->event->getApplication());
    }

    public function testTheLanguageFilterIsNotStartedTwice(): void
    {
        $this->plugin('system', 'languagefilter');
        $app = $this->app(AdministratorApplication::class);
        $app->expects($this->never())->method('bootPlugin');

        $router = $this->createMock(SiteRouter::class);
        $router->method('getRules')->willReturn(['buildpreprocess' => [[new FakeLanguageFilter(), 'buildRule']]]);

        new RouteBuilder(self::SITE_URL, $router);
    }

    public function testMonolingualSitesNeedNoLanguageFilter(): void
    {
        $app = $this->app(AdministratorApplication::class);
        $app->expects($this->never())->method('bootPlugin');

        new RouteBuilder(self::SITE_URL);
    }

    public function testAPluginWithoutTheHandlerIsIgnored(): void
    {
        $this->plugin('system', 'languagefilter');
        $app = $this->app(AdministratorApplication::class);
        $app->expects($this->once())->method('bootPlugin')->willReturn($this->createMock(CMSPlugin::class));

        new RouteBuilder(self::SITE_URL);
    }
}

/**
 * The language filter plugin, as far as the route builder sees it.
 */
final class LanguageFilterDouble extends CMSPlugin
{
    public ?AfterInitialiseEvent $event = null;

    public function __construct()
    {
    }

    public function onAfterInitialise(AfterInitialiseEvent $event): void
    {
        $this->event = $event;
    }
}

/**
 * Stands for the language filter plugin among the router rules.
 */
final class FakeLanguageFilter
{
    public function buildRule(): void
    {
    }
}
