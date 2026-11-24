<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \QrzLite\Component\Sitemap\Administrator\View\Dashboard\HtmlView $this */
?>
<!-- [html-validate-disable-next wcag/h32: the toolbar buttons submit the form] -->
<form action="<?php echo Route::_('index.php?option=com_qrzlitesitemap&view=dashboard'); ?>" method="post" name="adminForm" id="adminForm">
    <?php if ($this->siteUrlError !== '') : ?>
        <div class="alert alert-warning"><?php echo $this->escape($this->siteUrlError); ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="card-title h4"><?php echo Text::_('COM_QRZLITESITEMAP_FILES'); ?></h2>
            <?php if (!$this->files) : ?>
                <p><?php echo Text::_('COM_QRZLITESITEMAP_NO_FILES'); ?></p>
            <?php else : ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_QRZLITESITEMAP_FILE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_QRZLITESITEMAP_SIZE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_QRZLITESITEMAP_WRITTEN'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->files as $file) : ?>
                            <tr>
                                <td>
                                    <?php if ($file['url'] !== '') : ?>
                                        <a href="<?php echo $this->escape($file['url']); ?>" target="_blank" rel="noopener"><?php echo $this->escape($file['name']); ?></a>
                                    <?php else : ?>
                                        <?php echo $this->escape($file['name']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo HTMLHelper::_('number.bytes', $file['size']); ?></td>
                                <td><?php echo HTMLHelper::_('date', '@' . $file['time'], Text::_('DATE_FORMAT_LC5')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p class="mb-0"><?php echo Text::sprintf('COM_QRZLITESITEMAP_LINKS', $this->links); ?></p>
        </div>
    </div>

    <?php if ($this->exclusions) : ?>
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="card-title h4"><?php echo Text::sprintf('COM_QRZLITESITEMAP_EXCLUSIONS', \count($this->exclusions)); ?></h2>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_QRZLITESITEMAP_ITEM'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_QRZLITESITEMAP_REASON'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->exclusions as [$what, $reason]) : ?>
                            <tr>
                                <td><?php echo $this->escape($what); ?></td>
                                <td><?php echo $this->escape($reason); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
