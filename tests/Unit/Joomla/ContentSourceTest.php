<?php

namespace QrzLite\Tests\Unit\Joomla;

use QrzLite\Component\Sitemap\Administrator\Joomla\ContentSource;
use QrzLite\Tests\Support\JoomlaTestCase;

final class ContentSourceTest extends JoomlaTestCase
{
    public function testMenuItemsArePublishedSiteItemsInTreeOrderWithTheirComponent(): void
    {
        $this->insert('#__extensions', [
            ['extension_id' => 22, 'type' => 'component', 'element' => 'com_content', 'enabled' => 1],
            ['extension_id' => 23, 'type' => 'component', 'element' => 'com_contact', 'enabled' => 0],
        ]);
        $this->insert('#__menu', [
            ['id' => 1, 'title' => 'Menu_Item_Root', 'lft' => 0],
            ['id' => 101, 'title' => 'Home', 'lft' => 3, 'home' => 1, 'component_id' => 22, 'link' => 'index.php?option=com_content&view=featured'],
            ['id' => 102, 'title' => 'Contact', 'lft' => 1, 'component_id' => 23],
            ['id' => 103, 'title' => 'External', 'lft' => 2, 'type' => 'url', 'link' => 'https://example.org/'],
            ['id' => 104, 'title' => 'Unpublished', 'lft' => 4, 'published' => 0],
            ['id' => 105, 'title' => 'Trashed', 'lft' => 5, 'published' => -2],
            ['id' => 106, 'title' => 'Administrator', 'lft' => 6, 'client_id' => 1],
        ]);

        $rows = (new ContentSource($this->db))->menuItems();

        $this->assertSame([102, 103, 101], array_map('intval', array_column($rows, 'id')));
        $this->assertSame(['com_contact', null, 'com_content'], array_column($rows, 'component'));
        $this->assertSame([0, null, 1], array_map(static fn ($v) => $v === null ? null : (int) $v, array_column($rows, 'component_enabled')));
        $this->assertSame('index.php?option=com_content&view=featured', $rows[2]['link']);

        foreach (['title', 'type', 'home', 'access', 'language', 'params', 'publish_up', 'publish_down'] as $column) {
            $this->assertArrayHasKey($column, $rows[0]);
        }
    }

    public function testCategoriesAreArticleCategoriesOfAnyStateInTreeOrder(): void
    {
        $this->insert('#__categories', [
            ['id' => 1, 'extension' => 'system', 'title' => 'ROOT', 'lft' => 0],
            ['id' => 2, 'title' => 'News', 'lft' => 3],
            ['id' => 3, 'title' => 'Drafts', 'lft' => 1, 'published' => 0],
            ['id' => 4, 'title' => 'Contacts', 'lft' => 2, 'extension' => 'com_contact'],
        ]);

        $rows = (new ContentSource($this->db))->categories();

        $this->assertSame([3, 2], array_map('intval', array_column($rows, 'id')));
        $this->assertSame(
            ['id', 'parent_id', 'title', 'published', 'access', 'language', 'metadata', 'created_time', 'modified_time'],
            array_keys($rows[0])
        );
    }

    public function testArticlesAreAllButTrashedOnesByID(): void
    {
        $this->insert('#__content', [
            ['id' => 3, 'title' => 'Published'],
            ['id' => 1, 'title' => 'Unpublished', 'state' => 0],
            ['id' => 2, 'title' => 'Trashed', 'state' => -2],
            ['id' => 4, 'title' => 'Archived', 'state' => 2],
        ]);

        $rows = (new ContentSource($this->db))->articles();

        $this->assertSame([1, 3, 4], array_map('intval', array_column($rows, 'id')));
        $this->assertSame(
            ['id', 'title', 'alias', 'catid', 'state', 'access', 'publish_up', 'publish_down', 'created', 'modified', 'metadata', 'language'],
            array_keys($rows[0])
        );
    }

    public function testLanguagesArePublishedOnesAGuestMaySee(): void
    {
        $this->insert('#__languages', [
            ['lang_code' => 'en-GB', 'access' => 1],
            ['lang_code' => 'uk-UA', 'access' => 0],
            ['lang_code' => 'de-DE', 'access' => 2],
            ['lang_code' => 'fr-FR', 'published' => 0],
            ['lang_code' => 'pl-PL', 'access' => 5],
        ]);

        $this->assertSame(['en-GB', 'uk-UA', 'pl-PL'], (new ContentSource($this->db))->languages([1, 5]));
        $this->assertSame(['uk-UA'], (new ContentSource($this->db))->languages([]));
    }
}
