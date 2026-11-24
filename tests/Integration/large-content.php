<?php

/**
 * Grows a Joomla site for measuring generation: a Bench category with a menu item and 20 subcategories, and
 * articles in them up to the given number. Run it again with a larger number to add more.
 *
 *   php tests/Integration/large-content.php /path/to/joomla 50000
 */

if (PHP_SAPI !== 'cli' || $argc < 3 || !is_file($argv[1] . '/configuration.php') || (int) $argv[2] < 1) {
    fwrite(STDERR, "Usage: php large-content.php <joomla root> <articles>\n");
    exit(2);
}

const _JEXEC = 1;

\define('JPATH_BASE', realpath($argv[1]));
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Category;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Table\Menu;
use Joomla\Database\DatabaseInterface;

$container = Factory::getContainer();
$container->alias('session', 'session.cli')->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
Factory::$application = $container->get(\Joomla\Console\Application::class);
$db                   = $container->get(DatabaseInterface::class);
$target               = (int) $argv[2];

$store = static function ($table, array $data, ?int $parent = null): int {
    if ($parent !== null) {
        $table->setLocation($parent, 'last-child');
    }

    if (!$table->bind($data) || !$table->check() || !$table->store()) {
        fwrite(STDERR, \get_class($table) . ': ' . $table->getError() . "\n");
        exit(1);
    }

    if ($parent !== null) {
        $table->rebuildPath($table->id);
    }

    return (int) $table->id;
};

$category = static fn (int $parent, string $title, string $alias) => $store(new Category($db), [
    'extension'       => 'com_content',
    'title'           => $title,
    'alias'           => $alias,
    'published'       => 1,
    'access'          => 1,
    'language'        => '*',
    'params'          => '{}',
    'metadata'        => '{"robots":""}',
    'description'     => '',
    'created_user_id' => 1,
], $parent);

$bench = (int) $db->setQuery("SELECT id FROM #__categories WHERE extension = 'com_content' AND alias = 'bench' AND parent_id = 1")->loadResult();

if ($bench === 0) {
    $bench = $category(1, 'Bench', 'bench');

    for ($i = 1; $i <= 20; $i++) {
        $category($bench, 'Bench ' . $i, 'bench-' . $i);
    }

    $com = (int) $db->setQuery("SELECT extension_id FROM #__extensions WHERE element = 'com_content' AND type = 'component'")->loadResult();
    $store(new Menu($db), [
        'menutype'     => 'mainmenu',
        'title'        => 'Bench',
        'alias'        => 'bench',
        'link'         => 'index.php?option=com_content&view=category&layout=blog&id=' . $bench,
        'type'         => 'component',
        'published'    => 1,
        'component_id' => $com,
        'access'       => 1,
        'language'     => '*',
        'params'       => '{}',
        'client_id'    => 0,
        'img'          => '',
        'browserNav'   => 0,
    ], 1);
}

$subs = array_map('intval', $db->setQuery('SELECT id FROM #__categories WHERE parent_id = ' . $bench . ' ORDER BY lft')->loadColumn());
$have = (int) $db->setQuery('SELECT COUNT(*) FROM #__content WHERE catid IN (' . implode(',', $subs) . ')')->loadResult();

if ($have === 0) {
    // The first article through Joomla, as a template with every column the database needs.
    $store(new Content($db), [
        'catid'      => $subs[0],
        'title'      => 'Bench article 1',
        'alias'      => 'bench-article-1',
        'state'      => 1,
        'access'     => 1,
        'language'   => '*',
        'introtext'  => '<p>Bench article</p>',
        'fulltext'   => '',
        'metadata'   => '{"robots":""}',
        'attribs'    => '{}',
        'urls'       => '{}',
        'images'     => '{}',
        'metakey'    => '',
        'metadesc'   => '',
        'created_by' => 1,
        'publish_up' => '2025-01-01 00:00:00',
    ]);
    $have = 1;
}

$template = $db->setQuery('SELECT * FROM #__content WHERE catid IN (' . implode(',', $subs) . ') ORDER BY id', 0, 1)->loadAssoc();
unset($template['id']);
$template['asset_id'] = 0;
$columns              = array_keys($template);
$base                 = strtotime('2025-01-01 00:00:00 UTC');

// The rest with multi-row INSERTs: storing 100,000 articles through Joomla would take far longer.
foreach (array_chunk(range($have + 1, max($have, $target)), 1000) as $numbers) {
    $rows = [];

    foreach ($numbers as $n) {
        $row = array_merge($template, [
            'catid'      => $subs[$n % \count($subs)],
            'title'      => 'Bench article ' . $n,
            'alias'      => 'bench-article-' . $n,
            'created'    => gmdate('Y-m-d H:i:s', $base + $n * 60),
            'modified'   => gmdate('Y-m-d H:i:s', $base + $n * 60),
            'publish_up' => gmdate('Y-m-d H:i:s', $base + $n * 60),
        ]);
        $rows[] = '(' . implode(',', array_map(static fn ($value) => $value === null ? 'NULL' : $db->quote((string) $value), $row)) . ')';
    }

    $db->setQuery('INSERT INTO #__content (' . implode(',', $db->quoteName($columns)) . ') VALUES ' . implode(',', $rows))->execute();
}

echo 'Bench articles: ' . max($have, $target) . "\n";
