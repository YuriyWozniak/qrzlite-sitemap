-- The part of the Joomla schema that the component reads, for SQLite. The table of the component itself
-- follows sql/install.mysql.utf8.sql (SchemaTest checks that the columns match).

CREATE TABLE `#__extensions` (
    `extension_id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` TEXT NOT NULL DEFAULT '',
    `type` TEXT NOT NULL DEFAULT '',
    `element` TEXT NOT NULL DEFAULT '',
    `folder` TEXT NOT NULL DEFAULT '',
    `client_id` INTEGER NOT NULL DEFAULT 0,
    `enabled` INTEGER NOT NULL DEFAULT 1,
    `params` TEXT NOT NULL DEFAULT '{}'
);

CREATE TABLE `#__menu` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `menutype` TEXT NOT NULL DEFAULT 'mainmenu',
    `title` TEXT NOT NULL DEFAULT '',
    `alias` TEXT NOT NULL DEFAULT '',
    `link` TEXT NOT NULL DEFAULT '',
    `type` TEXT NOT NULL DEFAULT 'component',
    `published` INTEGER NOT NULL DEFAULT 1,
    `parent_id` INTEGER NOT NULL DEFAULT 1,
    `level` INTEGER NOT NULL DEFAULT 1,
    `component_id` INTEGER NOT NULL DEFAULT 0,
    `access` INTEGER NOT NULL DEFAULT 1,
    `params` TEXT NOT NULL DEFAULT '{}',
    `lft` INTEGER NOT NULL DEFAULT 0,
    `home` INTEGER NOT NULL DEFAULT 0,
    `language` TEXT NOT NULL DEFAULT '*',
    `client_id` INTEGER NOT NULL DEFAULT 0,
    `publish_up` TEXT NULL,
    `publish_down` TEXT NULL
);

CREATE TABLE `#__categories` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `parent_id` INTEGER NOT NULL DEFAULT 0,
    `lft` INTEGER NOT NULL DEFAULT 0,
    `extension` TEXT NOT NULL DEFAULT 'com_content',
    `title` TEXT NOT NULL DEFAULT '',
    `alias` TEXT NOT NULL DEFAULT '',
    `published` INTEGER NOT NULL DEFAULT 1,
    `access` INTEGER NOT NULL DEFAULT 1,
    `metadata` TEXT NOT NULL DEFAULT '{}',
    `created_time` TEXT NOT NULL DEFAULT '2020-01-01 00:00:00',
    `modified_time` TEXT NOT NULL DEFAULT '2020-01-01 00:00:00',
    `language` TEXT NOT NULL DEFAULT '*'
);

CREATE TABLE `#__content` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `title` TEXT NOT NULL DEFAULT '',
    `alias` TEXT NOT NULL DEFAULT '',
    `state` INTEGER NOT NULL DEFAULT 1,
    `catid` INTEGER NOT NULL DEFAULT 0,
    `created` TEXT NOT NULL DEFAULT '2020-01-01 00:00:00',
    `modified` TEXT NOT NULL DEFAULT '2020-01-01 00:00:00',
    `publish_up` TEXT NULL,
    `publish_down` TEXT NULL,
    `metadata` TEXT NOT NULL DEFAULT '{}',
    `access` INTEGER NOT NULL DEFAULT 1,
    `language` TEXT NOT NULL DEFAULT '*'
);

CREATE TABLE `#__languages` (
    `lang_id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `lang_code` TEXT NOT NULL,
    `title` TEXT NOT NULL DEFAULT '',
    `title_native` TEXT NOT NULL DEFAULT '',
    `sef` TEXT NOT NULL DEFAULT '',
    `image` TEXT NOT NULL DEFAULT '',
    `description` TEXT NOT NULL DEFAULT '',
    `metakey` TEXT NOT NULL DEFAULT '',
    `metadesc` TEXT NOT NULL DEFAULT '',
    `sitename` TEXT NOT NULL DEFAULT '',
    `published` INTEGER NOT NULL DEFAULT 1,
    `access` INTEGER NOT NULL DEFAULT 1,
    `ordering` INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE `#__viewlevels` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `title` TEXT NOT NULL DEFAULT '',
    `ordering` INTEGER NOT NULL DEFAULT 0,
    `rules` TEXT NOT NULL DEFAULT '[]'
);

CREATE TABLE `#__usergroups` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `parent_id` INTEGER NOT NULL DEFAULT 0,
    `lft` INTEGER NOT NULL DEFAULT 0,
    `rgt` INTEGER NOT NULL DEFAULT 0,
    `title` TEXT NOT NULL DEFAULT ''
);

CREATE TABLE `#__user_usergroup_map` (
    `user_id` INTEGER NOT NULL,
    `group_id` INTEGER NOT NULL
);

CREATE TABLE `#__qrzlitesitemap_links` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `path` TEXT NOT NULL,
    `title` TEXT NOT NULL DEFAULT '',
    `level` INTEGER NOT NULL DEFAULT 1,
    `lastmod` TEXT NULL
);
