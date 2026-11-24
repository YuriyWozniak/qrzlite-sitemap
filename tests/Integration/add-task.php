<?php

/**
 * Adds an enabled "Generate the sitemap" scheduled task to a Joomla site and prints its id.
 *
 *   php tests/Integration/add-task.php /path/to/joomla
 */

require $argv[1] . '/configuration.php';

$config = new JConfig();
$db     = new mysqli($config->host, $config->user, $config->password, $config->db);
$now    = gmdate('Y-m-d H:i:s');
$table  = $config->dbprefix . 'scheduler_tasks';
$rules  = $db->real_escape_string('{"rule-type":"interval-hours","interval-hours":"24","exec-day":"1","exec-time":"03:00"}');
$cron   = $db->real_escape_string('{"type":"interval","exp":"PT24H"}');

$db->query("INSERT INTO `$table` (title, type, execution_rules, cron_rules, state, next_execution, params, created, created_by)
    VALUES ('Sitemap', 'qrzlitesitemap.generate', '$rules', '$cron', 1, '$now', '{}', '$now', 0)") or exit(1);

echo $db->insert_id, "\n";
