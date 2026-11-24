<?php

/**
 * Creates test content in a fresh Joomla site: categories, articles and menu items that exercise every rule.
 *
 *   php tests/Integration/setup-content.php /path/to/joomla
 */

if (PHP_SAPI !== 'cli' || $argc < 2 || !is_file($argv[1] . '/configuration.php')) {
    fwrite(STDERR, "Usage: php setup-content.php <joomla root>\n");
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
$db = $container->get(DatabaseInterface::class);

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

$category = static fn (int $parent, string $title, string $alias, int $state = 1, int $access = 1, string $robots = '') => $store(new Category($db), [
    'extension' => 'com_content', 'title' => $title, 'alias' => $alias, 'published' => $state, 'access' => $access,
    'language' => '*', 'params' => '{}', 'metadata' => json_encode(['robots' => $robots]), 'description' => '', 'created_user_id' => 1,
], $parent);

$article = static fn (int $catid, string $title, string $alias, array $extra = []) => $store(new Content($db), $extra + [
    'catid' => $catid, 'title' => $title, 'alias' => $alias, 'state' => 1, 'access' => 1, 'language' => '*',
    'introtext' => '<p>' . $title . '</p>', 'fulltext' => '', 'metadata' => '{"robots":""}', 'attribs' => '{}', 'urls' => '{}',
    'images' => '{}', 'metakey' => '', 'metadesc' => '', 'created_by' => 1, 'publish_up' => gmdate('Y-m-d H:i:s', time() - 86400),
]);

$com = (int) $db->setQuery("SELECT extension_id FROM #__extensions WHERE element = 'com_content' AND type = 'component'")->loadResult();

$menu = static fn (string $title, string $alias, string $link, string $type = 'component') => $store(new Menu($db), [
    'menutype' => 'mainmenu', 'title' => $title, 'alias' => $alias, 'link' => $link, 'type' => $type, 'published' => 1,
    'component_id' => $type === 'component' ? $com : 0, 'access' => 1, 'language' => '*', 'params' => '{}', 'client_id' => 0,
    'img' => '', 'browserNav' => 0,
], 1);

$news    = $category(1, 'News', 'news');
$sub     = $category($news, 'Sub News', 'sub-news');
$hidden  = $category(1, 'Hidden', 'hidden', 0);
$inner   = $category($hidden, 'Inside Hidden', 'inside-hidden');
$private = $category(1, 'Private', 'private', 1, 2);
$noindex = $category(1, 'Noindex Category', 'noindex-category', 1, 1, 'noindex, follow');

$article($news, 'First news', 'first-news');
$article($news, 'Новина друга', 'новина-друга');
$article($news, 'Noindex article', 'noindex-article', ['metadata' => '{"robots":"noindex, nofollow"}']);
$article($news, 'Future article', 'future-article', ['publish_up' => gmdate('Y-m-d H:i:s', time() + 86400 * 30)]);
$draft = $article($news, 'Unpublished article', 'unpublished-article', ['state' => 0]);
$article($sub, 'Sub article', 'sub-article');
$article($inner, 'In hidden parent', 'in-hidden-parent');
$article($private, 'Private article', 'private-article');
$article($noindex, 'In noindex category', 'in-noindex-category');
$about = $article($news, 'About us', 'about-us');

$menu('News', 'news', 'index.php?option=com_content&view=category&layout=blog&id=' . $news);
$menu('About', 'about', 'index.php?option=com_content&view=article&id=' . $about);
$menu('Draft page', 'draft-page', 'index.php?option=com_content&view=article&id=' . $draft);
$menu('External', 'external', 'https://example.org/', 'url');

echo "Test content created\n";
