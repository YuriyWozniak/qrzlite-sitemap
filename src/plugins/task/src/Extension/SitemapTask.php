<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Plugin\Task\Sitemap\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Service\Generator;

/**
 * "Generate the sitemap" task for the Joomla Scheduler (System - Scheduled Tasks). It runs from the lazy
 * scheduler on site requests, from a web cron URL or from "php cli/joomla.php scheduler:run".
 */
final class SitemapTask extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    protected const TASKS_MAP = [
        'qrzlitesitemap.generate' => [
            'langConstPrefix' => 'PLG_TASK_QRZLITESITEMAP_GENERATE',
            'method'          => 'generate',
        ],
    ];

    /**
     * @var  bool
     */
    protected $autoloadLanguage = true;

    /**
     * @return  array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    protected function generate(ExecuteTaskEvent $event): int
    {
        if (!ComponentHelper::isEnabled(SitemapComponent::NAME)) {
            $this->logTask('The QrzLite Sitemap component is not installed or is disabled', 'warning');

            return Status::KNOCKOUT;
        }

        try {
            $result = Generator::create()->generate();
        } catch (\Throwable $e) {
            $this->logTask('The sitemap was not generated: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        $this->logTask('Sitemap: ' . $result->summary());

        return Status::OK;
    }
}
