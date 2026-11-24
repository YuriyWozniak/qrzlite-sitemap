<?php

namespace QrzLite\Tests\Support;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Cache\CacheControllerFactory;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\ComponentRecord;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\HTML\Registry as HtmlRegistry;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageFactory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ContainerAwareToolbarFactory;
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\WebAsset\WebAssetRegistry;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\Sqlite\SqliteDriver;
use Joomla\DI\Container;
use Joomla\Event\Dispatcher;
use Joomla\Event\DispatcherInterface;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Runs the component's Joomla-facing classes against the real Joomla libraries, with an SQLite database in
 * memory, an empty site root (JPATH_ROOT) and test doubles for the application and the site router.
 */
abstract class JoomlaTestCase extends TestCase
{
    protected const SITE_URL = 'https://example.com';

    /** Source folder of the component (Joomla refuses paths with ".."). */
    protected const COMPONENT_PATH = SRC_PATH . '/component';

    /** Where Joomla would install the component; Joomla refuses controller paths outside the site root. */
    protected const INSTALLED_PATH = JPATH_ROOT . '/administrator/components/com_qrzlitesitemap';

    protected SqliteDriver $db;

    protected Container $container;

    protected HtmlDocument $document;

    protected Dispatcher $dispatcher;

    /** @var array<string, mixed>  The application's user state. */
    protected array $userState = [];

    /** @var array<int, array{0: string, 1: string}>  Messages the application was given. */
    protected array $messages = [];

    /** @var array<string, string>  Routes the fake site router knows: route => path */
    protected array $routes = [];

    /** @var array<string, mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        self::resetJoomla();

        $this->db = new SqliteDriver(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'jos_']);

        foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents(__DIR__ . '/schema.sql')))) as $sql) {
            $this->db->setQuery(preg_replace('/^--.*$/m', '', $sql))->execute();
        }

        $this->container = new Container();
        $this->container->share(DatabaseInterface::class, $this->db);
        $this->container->share(SiteRouter::class, $this->router());
        $this->container->alias('SiteRouter', SiteRouter::class);
        $this->container->share(UserFactoryInterface::class, $this->userFactory());
        $this->container->share(LanguageFactoryInterface::class, new LanguageFactory());
        $this->container->share(HtmlRegistry::class, new HtmlRegistry());
        $this->container->share('webassetregistry', new WebAssetRegistry());
        $this->container->share(CacheControllerFactoryInterface::class, new CacheControllerFactory());

        $toolbars = new ContainerAwareToolbarFactory();
        $toolbars->setContainer($this->container);
        $this->container->share(ToolbarFactoryInterface::class, $toolbars);

        Factory::$container = $this->container;
        Factory::$database  = $this->db;
        self::language();

        $this->dispatcher = new Dispatcher();
        $this->container->share(DispatcherInterface::class, $this->dispatcher);

        $this->document = new HtmlDocument();
        self::setStatic(Factory::class, 'document', $this->document);

        self::setStatic(PluginHelper::class, 'plugins', []);
        $this->seedAccess();
        $this->component('com_content');
        $this->component('com_users', ['guest_usergroup' => 1]);
        $this->component('com_languages', ['site' => 'en-GB']);
    }

    protected function tearDown(): void
    {
        self::resetJoomla();
        self::emptyFolder(JPATH_ROOT);
        $_SERVER = $this->server;

        parent::tearDown();
    }

    /**
     * Forgets what Joomla keeps in static properties between requests.
     */
    protected static function resetJoomla(): void
    {
        // Through reflection: Joomla documents these as never null.
        foreach (['application', 'container', 'database', 'language', 'document', 'config', 'session'] as $property) {
            self::setStatic(Factory::class, $property, null);
        }

        Multilanguage::$enabled = false;

        self::setStatic(ComponentHelper::class, 'components', []);
        self::setStatic(PluginHelper::class, 'plugins', null);
        self::setStatic(Route::class, '_router', []);
        self::setStatic(HTMLHelper::class, 'registry', []);
        self::setStatic(HTMLHelper::class, 'serviceRegistry', null);
        Access::clearStatics();
        Uri::reset();
    }

    protected static function setStatic(string $class, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue(null, $value);
    }

    protected static function language(): Language
    {
        $language          = new Language('en-GB');
        Factory::$language = $language;

        foreach (
            [
                self::COMPONENT_PATH . '/administrator' => 'com_qrzlitesitemap',
                self::COMPONENT_PATH . '/site'          => 'com_qrzlitesitemap',
                SRC_PATH . '/plugins/task'              => 'plg_task_qrzlitesitemap',
            ] as $path => $extension
        ) {
            $language->load($extension, $path, 'en-GB', true, false);
        }

        // The few strings of Joomla's own language files that formats depend on.
        $strings = new \ReflectionProperty(Language::class, 'strings');
        $strings->setAccessible(true);
        $strings->setValue($language, $strings->getValue($language) + [
            'DECIMALS_SEPARATOR'  => '.',
            'THOUSANDS_SEPARATOR' => ',',
            'DATE_FORMAT_LC5'     => 'Y-m-d H:i',
        ]);

        return $language;
    }

    /**
     * Registers an installed component.
     *
     * @param   array<string, mixed>  $params
     */
    protected function component(string $option, array $params = [], bool $enabled = true): void
    {
        $components = (new \ReflectionProperty(ComponentHelper::class, 'components'))->getValue();

        $components[$option] = new ComponentRecord([
            'id'      => \count($components) + 10,
            'option'  => $option,
            'enabled' => (int) $enabled,
            'params'  => json_encode((object) $params),
        ]);

        self::setStatic(ComponentHelper::class, 'components', $components);
    }

    /**
     * Registers an enabled plugin.
     *
     * @param   array<string, mixed>  $params
     */
    protected function plugin(string $type, string $name, array $params = []): void
    {
        $plugins   = (array) (new \ReflectionProperty(PluginHelper::class, 'plugins'))->getValue();
        $plugins[] = (object) ['id' => \count($plugins) + 100, 'type' => $type, 'name' => $name, 'params' => json_encode((object) $params)];

        self::setStatic(PluginHelper::class, 'plugins', $plugins);
    }

    /**
     * Inserts rows into a table.
     *
     * @param   array<int, array<string, mixed>>  $rows
     */
    protected function insert(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $object = (object) $row;
            $this->db->insertObject($table, $object);
        }
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    protected function rows(string $table): array
    {
        return $this->db->setQuery('SELECT * FROM ' . $this->db->quoteName($table) . ' ORDER BY 1')->loadAssocList();
    }

    /**
     * Guests are in group 1 (Public) and see the Public (1) and Guest (5) levels; Registered (2) needs group 2.
     */
    private function seedAccess(): void
    {
        $this->insert('#__usergroups', [
            ['id' => 1, 'parent_id' => 0, 'lft' => 1, 'rgt' => 4, 'title' => 'Public'],
            ['id' => 2, 'parent_id' => 1, 'lft' => 2, 'rgt' => 3, 'title' => 'Registered'],
        ]);
        $this->insert('#__viewlevels', [
            ['id' => 1, 'title' => 'Public', 'rules' => '[1]'],
            ['id' => 2, 'title' => 'Registered', 'rules' => '[2]'],
            ['id' => 5, 'title' => 'Guest', 'rules' => '[1]'],
        ]);
    }

    /**
     * A site router that knows $this->routes; other routes give /unknown/<route>.
     */
    private function router(): SiteRouter
    {
        $router = $this->createMock(SiteRouter::class);
        $router->method('build')->willReturnCallback(fn ($route) => new Uri(Uri::root(true) . ($this->routes[$route] ?? '/unknown/' . rawurlencode($route))));
        $router->method('getRules')->willReturn(['buildpreprocess' => []]);

        return $router;
    }

    private function userFactory(): UserFactoryInterface
    {
        $factory = $this->createMock(UserFactoryInterface::class);
        $factory->method('loadUserById')->willReturnCallback(static fn ($id) => new User());

        return $factory;
    }

    /**
     * A test double of a Joomla application class with the global configuration, user state, messages, input
     * and identity that the component uses.
     *
     * @template T of object
     *
     * @param   class-string<T>       $class
     * @param   array<string, mixed>  $config  Global configuration.
     * @param   string[]              $allowed Actions the current user may do (authorise()).
     *
     * @return  T&MockObject
     */
    protected function app(string $class, array $config = [], array $allowed = ['core.manage', 'core.admin'], ?Input $input = null): object
    {
        $config += ['sitename' => 'Test Site', 'robots' => '', 'live_site' => '', 'secret' => 'test-secret'];

        $registry = new Registry($config);
        $this->container->share('config', $registry);
        self::setStatic(Factory::class, 'config', $registry);

        $user = $this->createMock(User::class);
        $user->method('authorise')->willReturnCallback(static fn ($action) => \in_array($action, $allowed, true));
        $user->method('get')->willReturnCallback(static fn ($key, $default = null) => $key === 'id' ? 42 : $default);
        $user->method('getTimezone')->willReturn(new \DateTimeZone('UTC'));
        $user->method('getParam')->willReturnCallback(static fn ($key, $default = null) => $default);
        $user->id = 42;

        $session = $this->createMock(Session::class);
        $session->method('getToken')->willReturn('session-token');
        $session->method('isNew')->willReturn(false);
        $session->method('get')->willReturnCallback(static fn ($key, $default = null) => $key === 'user' ? $user : $default);

        $app     = $this->createMock($class);
        $input ??= self::input();
        $stubs   = [
            'get'           => fn ($key, $default = null) => $registry->get($key, $default),
            'getIdentity'   => fn () => $user,
            'getInput'      => fn () => $input,
            'getLanguage'   => fn () => Factory::$language,
            'getSession'    => fn () => $session,
            'getLogger'     => static fn () => new NullLogger(),
            'getDispatcher' => fn () => $this->dispatcher,
            'getDocument'   => fn () => $this->document,
            'getTemplate'   => static fn ($params = false) => $params ? (object) ['template' => 'atum', 'params' => new Registry()] : 'atum',
            'getName'       => static fn () => strtolower(str_replace('Application', '', (new \ReflectionClass($class))->getShortName())),
            'redirect'      => static function ($url) {
                throw new Redirected((string) $url);
            },
            'getUserState' => fn ($key, $default = null) => $this->userState[$key] ?? $default,
            'setUserState' => function ($key, $value) {
                $this->userState[$key] = $value;

                return $value;
            },
            'enqueueMessage' => function ($message, $type = 'message') {
                $this->messages[] = [(string) $message, (string) $type];
            },
            'isClient' => static fn ($name) => str_contains(strtolower($class), strtolower($name)),
        ];

        foreach ($stubs as $method => $callback) {
            if (method_exists($class, $method)) {
                $app->method($method)->willReturnCallback($callback);
            }
        }

        self::setStatic(Factory::class, 'application', $app);

        return $app;
    }

    /**
     * A real site application (Joomla declares it final, so it can't be a test double).
     *
     * @param   array<string, mixed>  $request
     */
    protected function siteApplication(Registry $config, array $request = []): SiteApplication
    {
        // Joomla 5 types the input as its own subclass, which input() gives there.
        // @phpstan-ignore argument.type
        return new SiteApplication(self::input($request), $config, null, $this->container);
    }

    /**
     * Route::_() for a client: a router that returns the route unchanged.
     */
    protected function plainRoutes(string $client): void
    {
        $router = $this->createMock(Router::class);
        $router->method('build')->willReturnCallback(static fn ($url) => new Uri((string) $url));

        self::setStatic(Route::class, '_router', [$client => $router]);
    }

    /**
     * Keeps rendered HTML for the HTML linter (see HtmlSamples).
     */
    protected static function saveHtml(string $name, string $html): void
    {
        HtmlSamples::save($name, $html);
    }

    /**
     * Value of a protected property.
     */
    protected static function property(object $object, string $name): mixed
    {
        $reflection = new \ReflectionProperty($object, $name);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }

    /**
     * Request input: Joomla 5 still types it as its own subclass, Joomla 6 as the framework class.
     *
     * @param   array<string, mixed>  $data
     */
    protected static function input(array $data = []): Input
    {
        /** @var class-string<Input> $class */
        $class = class_exists('Joomla\\CMS\\Input\\Input') ? 'Joomla\\CMS\\Input\\Input' : Input::class;

        return new $class($data);
    }

    protected static function emptyFolder(string $folder): void
    {
        if (!is_dir($folder)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }
}
