<?php

/**
 * Builds the installable package: dist/pkg_qrzlitesitemap-<version>.zip
 *
 *   php build/build.php
 *
 * The version comes from src/pkg_qrzlitesitemap.xml; the build stops when another manifest has a different one.
 */

$root    = \dirname(__DIR__);
$src     = $root . '/src';
$dist    = $root . '/dist';
$version = (string) simplexml_load_file($src . '/pkg_qrzlitesitemap.xml')->version;

$parts = [
    'com_qrzlitesitemap'         => $src . '/component',
    'plg_console_qrzlitesitemap' => $src . '/plugins/console',
    'plg_task_qrzlitesitemap'    => $src . '/plugins/task',
];

foreach ($parts as $name => $folder) {
    $manifest = $folder . '/qrzlitesitemap.xml';
    $xml      = simplexml_load_file($manifest);

    if ($xml === false || (string) $xml->version !== $version) {
        fwrite(STDERR, "Version of $manifest is not $version\n");
        exit(1);
    }
}

/**
 * Files of a folder, relative paths sorted, so that builds are repeatable.
 */
$files = static function (string $folder): array {
    $list     = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $list[] = str_replace('\\', '/', substr($file->getPathname(), \strlen($folder) + 1));
        }
    }

    sort($list);

    return $list;
};

$zip = static function (string $target, array $entries): void {
    $archive = new ZipArchive();

    if ($archive->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "Cannot create $target\n");
        exit(1);
    }

    foreach ($entries as $name => $path) {
        $archive->addFile($path, $name);
        $archive->setMtimeName($name, 1767225600); // 2026-01-01, for repeatable builds
    }

    $archive->close();
};

if (!is_dir($dist) && !mkdir($dist, 0755, true)) {
    fwrite(STDERR, "Cannot create $dist\n");
    exit(1);
}

$temp     = sys_get_temp_dir() . '/qrzlitesitemap-build-' . getmypid();
$packages = [];
@mkdir($temp);

foreach ($parts as $name => $folder) {
    $entries = [];

    foreach ($files($folder) as $file) {
        $entries[$file] = $folder . '/' . $file;
    }

    $zip($temp . '/' . $name . '.zip', $entries);
    $packages['packages/' . $name . '.zip'] = $temp . '/' . $name . '.zip';
}

$target = $dist . '/pkg_qrzlitesitemap-' . $version . '.zip';
$zip($target, [
    'pkg_qrzlitesitemap.xml' => $src . '/pkg_qrzlitesitemap.xml',
    'script.php'             => $src . '/script.php',
    'LICENSE'                => $root . '/LICENSE',
] + $packages);

foreach ($packages as $file) {
    unlink($file);
}

rmdir($temp);

echo $target, ' (', filesize($target), " bytes)\n";
