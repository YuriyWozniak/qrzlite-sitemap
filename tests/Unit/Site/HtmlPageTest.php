<?php

namespace QrzLite\Tests\Unit\Site;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Menu\MenuFactoryInterface;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Storage\LinkRepository;
use QrzLite\Component\Sitemap\Site\Controller\DisplayController;
use QrzLite\Component\Sitemap\Site\Service\Router;
use QrzLite\Tests\Support\JoomlaTestCase;

/**
 * The HTML sitemap page, from the controller through the model and the view to the layout. Each test runs
 * in its own process: Joomla's site application keeps the page options in a static variable.
 */
#[RunTestsInSeparateProcesses]
final class HtmlPageTest extends JoomlaTestCase
{
    private SiteApplication $app;

    private AbstractMenu $menu;

    /**
     * @param   array<string, mixed>  $menuParams  Options of the active menu item; null for no menu item.
     * @param   array<string, mixed>  $request
     */
    private function site(?array $menuParams = null, array $request = [], int $limit = 200): void
    {
        $this->component(SitemapComponent::NAME, ['html_limit' => $limit]);
        $this->plainRoutes('site');

        $active = $menuParams === null ? null : new MenuItem([
            'id'        => 7,
            'title'     => 'Site Map',
            'link'      => 'index.php?option=com_qrzlitesitemap&view=html',
            'query'     => ['option' => 'com_qrzlitesitemap', 'view' => 'html'],
            'component' => 'com_qrzlitesitemap',
            'language'  => '*',
            'params'    => new Registry($menuParams),
        ]);

        $this->menu = $this->createMock(AbstractMenu::class);
        $this->menu->method('getActive')->willReturn($active);
        $this->menu->method('getItem')->willReturn($active);
        $this->menu->method('getItems')->willReturn($active ? [$active] : []);

        $menus = $this->createMock(MenuFactoryInterface::class);
        $menus->method('createMenu')->willReturn($this->menu);
        $this->container->share(MenuFactoryInterface::class, $menus);

        $config    = new Registry(['sitename' => 'Test Site', 'sitename_pagetitles' => 0, 'caching' => 0, 'secret' => 'test-secret']);
        $this->app = $this->siteApplication($config, $request + ['option' => SitemapComponent::NAME]);
        $this->app->loadLanguage(Factory::$language);
        $this->app->loadDocument($this->document);
        $this->app->setDispatcher($this->dispatcher);

        // A site template, so that Joomla does not look one up in the database.
        mkdir(JPATH_THEMES . '/cassiopeia', 0777, true);
        touch(JPATH_THEMES . '/cassiopeia/index.php');
        $this->app->setTemplate('cassiopeia', new Registry());
        $this->container->share('config', $config);
        self::setStatic(Factory::class, 'application', $this->app);
    }

    private function render(): string
    {
        $factory = new MVCFactory('\\QrzLite\\Component\\Sitemap');
        $factory->setDatabase($this->db);
        $factory->setDispatcher($this->dispatcher);
        $factory->setCacheControllerFactory($this->container->get(CacheControllerFactoryInterface::class));
        $factory->setFormFactory($this->createMock(FormFactoryInterface::class));
        $factory->setUserFactory($this->container->get(UserFactoryInterface::class));
        $factory->setSiteRouter($this->container->get(SiteRouter::class));

        $controller = new DisplayController(['base_path' => self::INSTALLED_PATH], $factory, $this->app, $this->app->getInput());

        // Joomla checks controller paths against the site root; the layouts are read from the source.
        $basePath = new \ReflectionProperty($controller, 'basePath');
        $basePath->setAccessible(true);
        $basePath->setValue($controller, self::COMPONENT_PATH . '/site');

        ob_start();

        try {
            $controller->display();
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    private function seedLinks(int $count): void
    {
        $rows = [['path' => '/', 'title' => 'Home', 'level' => 1]];

        for ($i = 1; $i < $count; $i++) {
            $rows[] = ['path' => '/news/item-' . $i, 'title' => $i % 2 ? 'Item <' . $i . '>' : '', 'level' => 2];
        }

        $this->insert(LinkRepository::TABLE, $rows);
    }

    public function testTheLinksOfTheLastGenerationIndentedByLevel(): void
    {
        $this->seedLinks(4);
        $this->site(['menu-meta_description' => 'All pages', 'robots' => 'noindex, follow']);

        $html = $this->render();
        self::saveHtml('html-page', $html);

        $this->assertStringContainsString('<h1>Site Map</h1>', $html);
        $this->assertMatchesRegularExpression('#<li>\s*<a href="/">Home</a>#', $html);
        $this->assertStringContainsString('<a href="/news/item-1">Item &lt;1&gt;</a>', $html);
        $this->assertStringContainsString('<li style="padding-inline-start: 1.5em">', $html);
        $this->assertStringContainsString('<a href="/news/item-2">/news/item-2</a>', $html);
        $this->assertStringNotContainsString('com-qrzlitesitemap__pagination', $html);
        $this->assertSame('Site Map', $this->document->getTitle());
        $this->assertSame('All pages', $this->document->getDescription());
        $this->assertSame('noindex, follow', $this->document->getMetaData('robots'));
        $this->assertSame('html', $this->app->getInput()->get('view'));
    }

    public function testPageHeadingAndTitleFromTheMenuItem(): void
    {
        $this->seedLinks(1);
        $this->site(['page_heading' => 'All our pages', 'page_title' => 'Pages']);

        $html = $this->render();

        $this->assertStringContainsString('<h1>All our pages</h1>', $html);
        $this->assertSame('Pages', $this->document->getTitle());
    }

    public function testWithoutAMenuItemTheComponentNamesThePage(): void
    {
        $this->seedLinks(1);
        $this->site(null, ['view' => 'other']);

        $html = $this->render();

        $this->assertStringContainsString('<h1>Sitemap</h1>', $html);
        $this->assertSame('html', $this->app->getInput()->get('view'));
    }

    public function testEmptyBeforeTheFirstGeneration(): void
    {
        $this->site([]);

        $html = $this->render();
        self::saveHtml('html-page-empty', $html);

        $this->assertStringContainsString('<div class="alert alert-info">JGLOBAL_NO_MATCHING_RESULTS</div>', $html);
        $this->assertStringNotContainsString('<ul', $html);
    }

    public function testLongListsArePaged(): void
    {
        $this->seedLinks(25);
        $this->site([], ['limitstart' => 20], 10);

        $html = $this->render();

        $this->assertSame(5, substr_count($html, '<li'));
        $this->assertStringContainsString('<a href="/news/item-20">', $html);
        $this->assertStringContainsString('com-qrzlitesitemap__pagination', $html);
    }

    public function testRouterKnowsTheHtmlView(): void
    {
        $this->site([]);

        $router = new Router($this->app, $this->menu);

        $this->assertArrayHasKey('html', $router->getViews());
        $this->assertSame(
            ['MenuRules', 'StandardRules', 'NomenuRules'],
            array_map(static fn ($rule) => (new \ReflectionClass($rule))->getShortName(), $router->getRules())
        );
    }

    public function testTheLinkOfTheMenuItemIsJustTheMenuItem(): void
    {
        $this->site([]);

        $router = new Router($this->app, $this->menu);
        $query  = ['option' => 'com_qrzlitesitemap', 'view' => 'html', 'Itemid' => 7];

        $this->assertSame([], $router->build($query));
        $this->assertSame(['option' => 'com_qrzlitesitemap', 'Itemid' => 7], $query, 'no "?view=html" after the menu item path');
        $this->assertSame('qrzlitesitemap', $router->getName());
    }
}
