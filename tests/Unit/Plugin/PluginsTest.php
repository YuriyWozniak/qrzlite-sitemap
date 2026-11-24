<?php

namespace QrzLite\Tests\Unit\Plugin;

use Joomla\Application\ApplicationEvents;
use Joomla\Application\Event\ApplicationEvent;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\LogEntry;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Task\Task;
use Joomla\DI\Container;
use Joomla\Event\Event;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Plugin\Console\Sitemap\Command\GenerateCommand;
use QrzLite\Plugin\Console\Sitemap\Extension\SitemapConsole;
use QrzLite\Plugin\Task\Sitemap\Extension\SitemapTask;
use QrzLite\Tests\Support\JoomlaTestCase;
use QrzLite\Tests\Support\SiteContent;

final class PluginsTest extends JoomlaTestCase
{
    use SiteContent;

    /** @var string[] */
    private array $log = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSite();
        $this->plugin('console', 'qrzlitesitemap');
        $this->plugin('task', 'qrzlitesitemap');
    }

    protected function tearDown(): void
    {
        self::setStatic(Log::class, 'instance', null);

        parent::tearDown();
    }

    /**
     * The plugin as Joomla loads it, from its service provider.
     */
    private static function load(string $type): PluginInterface
    {
        $provider  = require SRC_PATH . '/plugins/' . $type . '/services/provider.php';
        $container = new Container();
        $provider->register($container);

        return $container->get(PluginInterface::class);
    }

    public function testTheConsolePluginAddsTheCommand(): void
    {
        $this->component(SitemapComponent::NAME);
        $app    = $this->app(ConsoleApplication::class);
        $plugin = self::load('console');

        $this->assertInstanceOf(SitemapConsole::class, $plugin);
        $this->assertSame([ApplicationEvents::BEFORE_EXECUTE => 'registerCommands'], SitemapConsole::getSubscribedEvents());

        $app->expects($this->once())->method('addCommand')->with($this->isInstanceOf(GenerateCommand::class));
        $plugin->registerCommands(new ApplicationEvent(ApplicationEvents::BEFORE_EXECUTE, $app));
    }

    public function testNoCommandWithoutTheComponent(): void
    {
        $this->component(SitemapComponent::NAME, [], false);
        $app = $this->app(ConsoleApplication::class);

        $plugin = self::load('console');
        $this->assertInstanceOf(SitemapConsole::class, $plugin);

        $app->expects($this->never())->method('addCommand');
        $plugin->registerCommands(new ApplicationEvent(ApplicationEvents::BEFORE_EXECUTE, $app));
    }

    /**
     * Runs the task plugin's routine as the scheduler does.
     *
     * @return  array<string, mixed>  The result snapshot.
     */
    private function runTask(string $routine = 'qrzlitesitemap.generate'): array
    {
        $task = $this->createMock(Task::class);
        $task->method('get')->willReturnCallback(static fn ($path, $default = null) => ['id' => 5, 'type' => $routine][$path] ?? $default);
        $task->logCategory = 'task5';

        Log::addLogger(['logger' => 'callback', 'callback' => function (LogEntry $entry) {
            $this->log[] = $entry->priority . ' ' . $entry->message;
        }], Log::ALL, ['task5']);

        $plugin = self::load('task');
        $this->assertInstanceOf(SitemapTask::class, $plugin);

        $event = new ExecuteTaskEvent('onExecuteTask', ['subject' => $task]);
        $plugin->standardRoutineHandler($event);

        return $event->getResultSnapshot();
    }

    public function testTheTaskGeneratesTheSitemap(): void
    {
        $this->component(SitemapComponent::NAME);
        $this->app(AdministratorApplication::class, ['live_site' => self::SITE_URL]);

        $result = $this->runTask();

        $this->assertSame(Status::OK, $result['status']);
        $this->assertFileExists(JPATH_ROOT . '/sitemap.xml');
        $this->assertCount(1, $this->log);
        $this->assertMatchesRegularExpression('/^' . Log::INFO . ' .*Sitemap: 4 URLs, \d+ left out, written to sitemap\.xml/', $this->log[0]);
    }

    public function testTheTaskFailsWhenTheSitemapCannotBeGenerated(): void
    {
        $this->component(SitemapComponent::NAME, ['site_url' => 'nonsense']);
        $this->app(AdministratorApplication::class);

        $this->assertSame(Status::KNOCKOUT, $this->runTask()['status']);
        $this->assertStringContainsString('The sitemap was not generated: The site URL is not known', $this->log[0]);
        $this->assertStringStartsWith(Log::ERROR . ' ', $this->log[0]);
    }

    public function testTheTaskNeedsTheComponent(): void
    {
        $this->component(SitemapComponent::NAME, [], false);
        $this->app(AdministratorApplication::class, ['live_site' => self::SITE_URL]);

        $this->assertSame(Status::KNOCKOUT, $this->runTask()['status']);
        $this->assertStringContainsString('The QrzLite Sitemap component is not installed or is disabled', $this->log[0]);
        $this->assertFileDoesNotExist(JPATH_ROOT . '/sitemap.xml');
    }

    public function testOtherRoutinesAreNotHandled(): void
    {
        $this->component(SitemapComponent::NAME);
        $this->app(AdministratorApplication::class, ['live_site' => self::SITE_URL]);

        $this->assertSame([], $this->runTask('other.routine'));
        $this->assertFileDoesNotExist(JPATH_ROOT . '/sitemap.xml');
    }

    public function testTheTaskIsOfferedToTheScheduler(): void
    {
        $this->app(AdministratorApplication::class);
        $plugin = self::load('task');

        $this->assertInstanceOf(SitemapTask::class, $plugin);
        $this->assertSame(
            ['onTaskOptionsList' => 'advertiseRoutines', 'onExecuteTask' => 'standardRoutineHandler', 'onContentPrepareForm' => 'enhanceTaskItemForm'],
            SitemapTask::getSubscribedEvents()
        );

        $options = new class () {
            /** @var array<string, string> */
            public array $added = [];

            /**
             * @param   array<string, string>  $options
             */
            public function addOptions(array $options): void
            {
                $this->added += $options;
            }
        };

        $plugin->advertiseRoutines(new Event('onTaskOptionsList', ['subject' => $options]));

        $this->assertSame(['qrzlitesitemap.generate' => 'PLG_TASK_QRZLITESITEMAP_GENERATE'], $options->added);
    }
}
