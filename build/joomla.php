<?php

/**
 * Fetches the Joomla files that the unit tests and the static analysis load: .cache/joomla/<version>
 *
 *   php build/joomla.php            # the version in JOOMLA_VERSION, else the default below
 *   php build/joomla.php 6.2.1
 *
 * Prints the folder. Only the libraries and the scheduler component are extracted from the full package.
 */

const DEFAULT_VERSION = '5.4.11';
const PARTS           = ['libraries/', 'administrator/components/com_scheduler/'];

$version = $argv[1] ?? (getenv('JOOMLA_VERSION') ?: DEFAULT_VERSION);

if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    fwrite(STDERR, "Not a Joomla version: $version\n");
    exit(1);
}

$cache  = \dirname(__DIR__) . '/.cache/joomla';
$folder = $cache . '/' . $version;

if (is_file($folder . '/.complete')) {
    echo $folder, "\n";
    exit(0);
}

if (!is_dir($cache) && !mkdir($cache, 0755, true)) {
    fwrite(STDERR, "Cannot create $cache\n");
    exit(1);
}

$zipFile = $cache . '/Joomla_' . $version . '.zip';

if (!is_file($zipFile)) {
    $url = 'https://github.com/joomla/joomla-cms/releases/download/' . $version . '/Joomla_' . $version . '-Stable-Full_Package.zip';
    fwrite(STDERR, "Downloading $url\n");
    $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'qrzlite-sitemap-tests']]));

    if ($data === false || !str_starts_with($data, 'PK')) {
        fwrite(STDERR, "Cannot download $url\n");
        exit(1);
    }

    file_put_contents($zipFile . '.part', $data);
    rename($zipFile . '.part', $zipFile);
}

$zip = new ZipArchive();

if ($zip->open($zipFile) !== true) {
    fwrite(STDERR, "Cannot open $zipFile\n");
    exit(1);
}

$names = [];

for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string) $zip->getNameIndex($i);

    foreach (PARTS as $part) {
        if (str_starts_with($name, $part)) {
            $names[] = $name;
            break;
        }
    }
}

if (!$names || !$zip->extractTo($folder, $names)) {
    fwrite(STDERR, "Cannot extract $zipFile\n");
    exit(1);
}

$zip->close();
touch($folder . '/.complete');
unlink($zipFile);

echo $folder, "\n";
