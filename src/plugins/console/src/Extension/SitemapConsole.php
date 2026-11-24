<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Plugin\Console\Sitemap\Extension;

\defined('_JEXEC') or die;

use Joomla\Application\ApplicationEvents;
use Joomla\Application\Event\ApplicationEvent;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Console\Application;
use Joomla\Event\SubscriberInterface;
use QrzLite\Plugin\Console\Sitemap\Command\GenerateCommand;

/**
 * Adds the qrzlite-sitemap:generate command to the Joomla console (cli/joomla.php).
 */
final class SitemapConsole extends CMSPlugin implements SubscriberInterface
{
    /**
     * @return  array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [ApplicationEvents::BEFORE_EXECUTE => 'registerCommands'];
    }

    public function registerCommands(ApplicationEvent $event): void
    {
        $app = $event->getApplication();

        if ($app instanceof Application && ComponentHelper::isEnabled('com_qrzlitesitemap')) {
            $app->addCommand(new GenerateCommand());
        }
    }
}
