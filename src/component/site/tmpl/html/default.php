<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var \QrzLite\Component\Sitemap\Site\View\Html\HtmlView $this */

$base = rtrim(Uri::root(true), '/');
?>
<div class="com-qrzlitesitemap sitemap-html">
    <h1><?php echo $this->escape($this->heading); ?></h1>

    <?php if (!$this->items) : ?>
        <div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
    <?php else : ?>
        <ul class="list-unstyled">
            <?php foreach ($this->items as $item) : ?>
                <li style="padding-inline-start: <?php echo max(0, (int) $item->level - 1) * 1.5; ?>em">
                    <a href="<?php echo $this->escape($base . $item->path); ?>"><?php echo $this->escape($item->title !== '' ? $item->title : $item->path); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($this->pagination && $this->pagination->pagesTotal > 1) : ?>
            <div class="com-qrzlitesitemap__pagination w-100">
                <?php echo $this->pagination->getPagesLinks(); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
