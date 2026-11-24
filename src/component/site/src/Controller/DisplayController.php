<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * The frontend has a single view: the HTML sitemap page. It only reads; the sitemap is never generated
 * from a frontend request.
 */
class DisplayController extends BaseController
{
    /**
     * @var  string
     */
    protected $default_view = 'html';

    public function display($cachable = true, $urlparams = [])
    {
        $this->input->set('view', 'html');

        return parent::display($cachable, ['limitstart' => 'UINT', 'lang' => 'CMD']);
    }
}
