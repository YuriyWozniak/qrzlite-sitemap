<?php

namespace QrzLite\Tests\Unit\Admin;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Session\Session;
use QrzLite\Component\Sitemap\Administrator\Controller\DisplayController;
use QrzLite\Component\Sitemap\Administrator\Controller\SitemapController;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Storage\LinkRepository;
use QrzLite\Tests\Support\JoomlaTestCase;
use QrzLite\Tests\Support\Redirected;
use QrzLite\Tests\Support\SiteContent;

final class SitemapControllerTest extends JoomlaTestCase
{
    use SiteContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSite();
        $this->component(SitemapComponent::NAME);
    }

    /**
     * @param   string[]  $allowed
     */
    private function controller(array $allowed = ['core.manage'], bool $token = true): SitemapController
    {
        $app = $this->app(AdministratorApplication::class, ['live_site' => self::SITE_URL], $allowed, self::input(['option' => SitemapComponent::NAME]));
        $this->plainRoutes('administrator');

        if ($token) {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = Session::getFormToken();
        }

        return new SitemapController(['base_path' => self::INSTALLED_PATH], null, $app, $app->getInput());
    }

    public function testGenerateNowWritesTheSitemapAndReports(): void
    {
        $controller = $this->controller();
        $controller->execute('generate');

        $this->assertFileExists(JPATH_ROOT . '/sitemap.xml');
        $this->assertCount(4, $this->rows(LinkRepository::TABLE));
        $this->assertSame('index.php?option=com_qrzlitesitemap&view=dashboard', self::property($controller, 'redirect'));
        $this->assertMatchesRegularExpression('/^Sitemap generated: 4 URLs, \d+ items left out\.$/', self::property($controller, 'message'));
        $this->assertSame('message', self::property($controller, 'messageType'));
        $this->assertContains(['article #3 Draft', 'unpublished'], $this->userState['com_qrzlitesitemap.exclusions']);
    }

    public function testAFailureIsReportedWithoutWriting(): void
    {
        $this->component(SitemapComponent::NAME, ['site_url' => 'not a url']);

        $controller = $this->controller();
        $controller->execute('generate');

        $this->assertFileDoesNotExist(JPATH_ROOT . '/sitemap.xml');
        $this->assertStringStartsWith('The sitemap was not generated: The site URL is not known', self::property($controller, 'message'));
        $this->assertSame('error', self::property($controller, 'messageType'));
    }

    public function testManagersOnly(): void
    {
        $controller = $this->controller([]);

        $this->expectException(NotAllowed::class);
        $this->expectExceptionCode(403);

        $controller->execute('generate');
    }

    public function testTheFormTokenIsRequired(): void
    {
        $controller = $this->controller(['core.manage'], false);

        try {
            $controller->execute('generate');
            $this->fail('No redirect');
        } catch (Redirected) {
            $this->assertFileDoesNotExist(JPATH_ROOT . '/sitemap.xml');
            // Joomla's own language strings are not loaded in the tests: the key stands for the text.
            $this->assertSame([['JINVALID_TOKEN_NOTICE', 'warning']], $this->messages);
        }
    }

    public function testTheDashboardIsTheDefaultView(): void
    {
        $app = $this->app(AdministratorApplication::class);

        $this->assertSame('dashboard', self::property(new DisplayController(['base_path' => self::INSTALLED_PATH], null, $app, $app->getInput()), 'default_view'));
    }
}
