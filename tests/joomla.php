<?php

/**
 * Loads the Joomla classes for the unit tests and the static analysis, from the files that
 * build/joomla.php fetches (or from JOOMLA_SOURCE: a folder with libraries/ of a Joomla site).
 *
 * The JPATH_* constants point at an empty temporary site root.
 */

if (!\defined('_JEXEC')) {
    \define('_JEXEC', 1);
}

$joomla = getenv('JOOMLA_SOURCE') ?: '';

if ($joomla === '') {
    $version = getenv('JOOMLA_VERSION') ?: '';

    if ($version === '') {
        $found   = glob(__DIR__ . '/../.cache/joomla/*/.complete') ?: [];
        $version = $found ? basename(\dirname(end($found))) : '';
    }

    $joomla = __DIR__ . '/../.cache/joomla/' . $version;
}

if (!is_file($joomla . '/libraries/vendor/autoload.php')) {
    fwrite(STDERR, "The Joomla files are missing: run \"php build/joomla.php\" (or set JOOMLA_SOURCE)\n");
    exit(1);
}

$joomla = realpath($joomla);
$site   = sys_get_temp_dir() . '/qrzlite-sitemap-site-' . getmypid();

if (!is_dir($site)) {
    mkdir($site, 0777, true);
}

register_shutdown_function(static function () use ($site): void {
    if (!is_dir($site)) {
        return;
    }

    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($items as $item) {
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }

    @rmdir($site);
});

foreach (
    [
        'JPATH_ROOT'          => $site,
        'JPATH_BASE'          => $site,
        'JPATH_SITE'          => $site,
        'JPATH_PUBLIC'        => $site,
        'JPATH_CONFIGURATION' => $site,
        'JPATH_ADMINISTRATOR' => $site . '/administrator',
        'JPATH_API'           => $site . '/api',
        'JPATH_CLI'           => $site . '/cli',
        'JPATH_CACHE'         => $site . '/cache',
        'JPATH_MANIFESTS'     => $site . '/administrator/manifests',
        'JPATH_PLUGINS'       => $site . '/plugins',
        'JPATH_THEMES'        => $site . '/templates',
        'JPATH_INSTALLATION'  => $site . '/installation',
        'JPATH_LIBRARIES'     => $joomla . '/libraries',
        'JPATH_PLATFORM'      => $joomla . '/libraries',
        'JOOMLA_SOURCE_PATH'  => $joomla,
        'SRC_PATH'            => str_replace('\\', '/', (string) realpath(__DIR__ . '/../src')),
    ] as $name => $value
) {
    if (!\defined($name)) {
        \define($name, $value);
    }
}

require_once $joomla . '/libraries/vendor/autoload.php';

// Classes of Joomla's own extensions that the plugins use.
spl_autoload_register(static function (string $class) use ($joomla): void {
    $prefix = 'Joomla\\Component\\Scheduler\\Administrator\\';

    if (str_starts_with($class, $prefix)) {
        $file = $joomla . '/administrator/components/com_scheduler/src/' . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
});

return $site;
