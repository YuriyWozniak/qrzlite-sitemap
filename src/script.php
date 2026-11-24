<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * Package installer script: turns the console and task plugins on after the first install.
 */
return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class ($container->get(DatabaseInterface::class)) implements InstallerScriptInterface {
                public function __construct(private readonly DatabaseInterface $db)
                {
                }

                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                // The package manifest asks Joomla for PHP 8.1 (php_minimum) before this runs.
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type !== 'install') {
                        return true;
                    }

                    $db    = $this->db;
                    $query = (method_exists($db, 'createQuery') ? $db->createQuery() : $db->getQuery(true))
                        ->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('enabled') . ' = 1')
                        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('qrzlitesitemap'))
                        ->where($db->quoteName('folder') . ' IN (' . $db->quote('console') . ',' . $db->quote('task') . ')');

                    $db->setQuery($query)->execute();

                    return true;
                }
            }
        );
    }
};
