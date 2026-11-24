<?php

namespace QrzLite\Tests\Unit\Admin;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\DI\Container;
use Joomla\Registry\Registry;
use QrzLite\Component\Sitemap\Administrator\Controller\SitemapController;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Site\Service\Router;
use QrzLite\Tests\Support\JoomlaTestCase;

/**
 * What Joomla loads when it boots the component and installs the package.
 */
final class InstallationTest extends JoomlaTestCase
{
    public function testTheComponentProvidesItsControllersAndRouter(): void
    {
        $app       = $this->app(AdministratorApplication::class);
        $container = new Container($this->container);
        $container->share(FormFactoryInterface::class, $this->createMock(FormFactoryInterface::class));
        $container->share(MailerFactoryInterface::class, $this->createMock(MailerFactoryInterface::class));

        (require self::COMPONENT_PATH . '/administrator/services/provider.php')->register($container);
        $component = $container->get(ComponentInterface::class);

        $this->assertInstanceOf(SitemapComponent::class, $component);

        $controller = $component->getMVCFactory()->createController('Sitemap', 'Administrator', ['base_path' => self::INSTALLED_PATH], $app, $app->getInput());
        $this->assertInstanceOf(SitemapController::class, $controller);

        $site = $this->siteApplication(new Registry());
        $this->assertInstanceOf(Router::class, $component->createRouter($site, $this->createMock(AbstractMenu::class)));
        $this->assertInstanceOf(RouterFactoryInterface::class, $container->get(RouterFactoryInterface::class));
    }

    public function testFirstInstallTurnsThePluginsOn(): void
    {
        $this->insert('#__extensions', [
            ['extension_id' => 50, 'type' => 'plugin', 'folder' => 'console', 'element' => 'qrzlitesitemap', 'enabled' => 0],
            ['extension_id' => 51, 'type' => 'plugin', 'folder' => 'task', 'element' => 'qrzlitesitemap', 'enabled' => 0],
            ['extension_id' => 52, 'type' => 'plugin', 'folder' => 'system', 'element' => 'qrzlitesitemap', 'enabled' => 0],
            ['extension_id' => 53, 'type' => 'plugin', 'folder' => 'task', 'element' => 'other', 'enabled' => 0],
        ]);

        $container = new Container($this->container);
        (require SRC_PATH . '/script.php')->register($container);
        $script  = $container->get(InstallerScriptInterface::class);
        $adapter = $this->createMock(InstallerAdapter::class);
        $enabled = fn () => array_map('intval', array_column($this->rows('#__extensions'), 'enabled', 'extension_id'));

        $this->assertTrue($script->preflight('install', $adapter));
        $this->assertTrue($script->install($adapter));
        $this->assertTrue($script->postflight('update', $adapter));
        $this->assertSame([50 => 0, 51 => 0, 52 => 0, 53 => 0], $enabled());

        $this->assertTrue($script->postflight('install', $adapter));
        $this->assertSame([50 => 1, 51 => 1, 52 => 0, 53 => 0], $enabled());

        $this->assertTrue($script->update($adapter));
        $this->assertTrue($script->uninstall($adapter));
    }

    public function testTheTestSchemaFollowsTheInstallSql(): void
    {
        preg_match_all('/^\s*`(\w+)`/m', (string) file_get_contents(self::COMPONENT_PATH . '/administrator/sql/install.mysql.utf8.sql'), $mysql);

        $this->assertSame($mysql[1], array_keys($this->db->getTableColumns('#__qrzlitesitemap_links')));
    }
}
