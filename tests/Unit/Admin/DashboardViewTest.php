<?php

namespace QrzLite\Tests\Unit\Admin;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Document\Document;
use Joomla\CMS\Document\JsonDocument;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Model\DashboardModel;
use QrzLite\Component\Sitemap\Administrator\Storage\LinkRepository;
use QrzLite\Component\Sitemap\Administrator\View\Dashboard\HtmlView;
use QrzLite\Tests\Support\JoomlaTestCase;

final class DashboardViewTest extends JoomlaTestCase
{
    /**
     * @param   array<string, mixed>  $options
     * @param   string[]              $allowed
     */
    private function render(array $options = [], array $allowed = ['core.manage', 'core.admin'], string $liveSite = self::SITE_URL, ?Document $document = null): string
    {
        $this->component(SitemapComponent::NAME, $options);
        $this->app(AdministratorApplication::class, ['live_site' => $liveSite], $allowed, self::input(['option' => SitemapComponent::NAME]));
        $this->plainRoutes('administrator');

        $view = new HtmlView([
            'base_path'     => self::COMPONENT_PATH . '/administrator',
            'template_path' => self::COMPONENT_PATH . '/administrator/tmpl/dashboard',
        ]);
        $view->setDocument($document ?? $this->document);

        // As the display controller does: the view gets the model of the same name, with the sitemap services.
        $model = new DashboardModel(['dbo' => $this->db]);
        $model->setDatabase($this->db);
        $model->setSitemapFactory($this->sitemapFactory());
        $view->setModel($model, true);

        ob_start();

        try {
            $view->display();
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    public function testBeforeTheFirstGeneration(): void
    {
        $html = $this->render();
        self::saveHtml('dashboard-empty', $html);

        $this->assertStringContainsString('The sitemap has not been generated yet.', $html);
        $this->assertStringContainsString('Links on the HTML sitemap page: 0', $html);
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringContainsString('name="task" value=""', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" name="[0-9a-f]{32}" value="1"/', $html);
        $this->assertStringContainsString('action="index.php?option=com_qrzlitesitemap&amp;view=dashboard"', $html);
    }

    public function testFilesLinksAndWhatWasLeftOut(): void
    {
        file_put_contents(JPATH_ROOT . '/map.xml', str_repeat('x', 2048));
        file_put_contents(JPATH_ROOT . '/map_1.xml', 'x');
        file_put_contents(JPATH_ROOT . '/map_old.xml', 'not a part');
        touch(JPATH_ROOT . '/map.xml', 1767225600);
        $this->insert(LinkRepository::TABLE, [['path' => '/', 'title' => 'Home'], ['path' => '/a', 'title' => 'A']]);
        $this->userState['com_qrzlitesitemap.exclusions'] = [['article #3 <Draft>', 'unpublished']];

        $html = $this->render(['filename' => 'map']);
        self::saveHtml('dashboard', $html);

        $this->assertStringContainsString('<a href="https://example.com/map.xml" target="_blank" rel="noopener">map.xml</a>', $html);
        $this->assertStringContainsString('<a href="https://example.com/map_1.xml"', $html);
        $this->assertStringNotContainsString('map_old.xml', $html);
        $this->assertStringContainsString('<td>2.00 kB</td>', $html);
        $this->assertStringContainsString('<td>2026-01-01 00:00</td>', $html);
        $this->assertStringContainsString('Links on the HTML sitemap page: 2', $html);
        $this->assertStringContainsString('<td>article #3 &lt;Draft&gt;</td>', $html);
        $this->assertStringContainsString('<td>unpublished</td>', $html);
        $this->assertStringContainsString('Left Out in the Last Generation (1)', $html);
    }

    public function testWithoutASiteUrlTheFilesAreNotLinked(): void
    {
        file_put_contents(JPATH_ROOT . '/sitemap.xml', 'x');
        $_SERVER['HTTP_HOST'] = 'joomla.invalid';

        $html = $this->render([], ['core.manage'], '');

        $this->assertStringContainsString('class="alert alert-warning">The site URL is not known', $html);
        $this->assertStringContainsString('<td>' . "\n", $html);
        $this->assertStringNotContainsString('href="https://', $html);
    }

    public function testToolbar(): void
    {
        $this->render();

        $buttons = array_map(static fn ($button) => $button->getName(), $this->document->getToolbar()->getItems());

        $this->assertSame(['refresh', 'options'], $buttons);
        $this->assertSame('QrzLite Sitemap - Test Site - JADMINISTRATION', $this->document->getTitle());
    }

    public function testNoToolbarOutsideAnHtmlDocument(): void
    {
        $this->assertStringContainsString('id="adminForm"', $this->render([], ['core.manage', 'core.admin'], self::SITE_URL, new JsonDocument()));
        $this->assertSame([], $this->document->getToolbar()->getItems());
    }

    public function testOnlyAdministratorsGetTheOptionsButton(): void
    {
        $this->render([], ['core.manage']);

        $buttons = array_map(static fn ($button) => $button->getName(), $this->document->getToolbar()->getItems());

        $this->assertSame(['refresh'], $buttons);
    }
}
