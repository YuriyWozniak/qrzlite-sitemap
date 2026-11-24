<?php

/**
 * The sitemap core (Sitemap/) runs without Joomla; the rest of the component is tested against the Joomla
 * libraries that build/joomla.php fetches.
 */

require \dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/joomla.php';

$_SERVER['HTTP_HOST'] ??= 'example.com';
