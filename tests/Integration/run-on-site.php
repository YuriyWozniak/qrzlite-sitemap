<?php

/**
 * Runs the qrzlite-sitemap:generate console command on a real Joomla site from this source tree, without
 * installing anything there. Use it to compare the output with a site's current sitemap before switching.
 *
 *   php tests/Integration/run-on-site.php /path/to/joomla --options=opts.json --output=/tmp/out -v
 *
 * Without --output or --dry-run the command writes into the site root and saves links into the component
 * table, which exists only when the component is installed; so on a site without the component always pass
 * --dry-run or --output. Run it as a user that can read the site's configuration.php.
 */

if (PHP_SAPI !== 'cli' || $argc < 2 || !is_file($argv[1] . '/configuration.php')) {
    fwrite(STDERR, "Usage: php run-on-site.php <joomla root> [command options]\n");
    exit(2);
}

$repo = \dirname(__DIR__, 2);
$site = realpath($argv[1]);

const _JEXEC = 1;

\define('JPATH_BASE', $site);
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

// The command line the console application will read.
$_SERVER['argv'] = array_merge(['joomla.php', 'qrzlite-sitemap:generate'], \array_slice($argv, 2));
$_SERVER['argc'] = \count($_SERVER['argv']);

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require JPATH_LIBRARIES . '/vendor/autoload.php';
$loader->addPsr4('QrzLite\\Component\\Sitemap\\Administrator\\', $repo . '/src/component/administrator/src', true);
$loader->addPsr4('QrzLite\\Component\\Sitemap\\Site\\', $repo . '/src/component/site/src', true);
$loader->addPsr4('QrzLite\\Plugin\\Console\\Sitemap\\', $repo . '/src/plugins/console/src', true);

$container = \Joomla\CMS\Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

$app                              = $container->get(\Joomla\Console\Application::class);
\Joomla\CMS\Factory::$application = $app;

$app->addCommand(new \QrzLite\Plugin\Console\Sitemap\Command\GenerateCommand());
$app->execute();
