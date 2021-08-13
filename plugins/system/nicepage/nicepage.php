<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_nicepage/library/loader.php';

use NP\Factory;
/**
 * Class PlgContentNicepage
 */
class PlgSystemNicepage extends JPlugin
{
    /**
     * Process component content
     */
    public function onAfterDispatch()
    {
        $app = JFactory::getApplication();

        if ($app->isAdmin() || ($app->get('offline') && !JFactory::getUser()->authorise('core.login.offline'))) {
            return;
        }

        $doc = JFactory::getDocument();
        $buf = $doc->getBuffer('component');
        if (preg_match('/<\!--np\_landing-->([\s\S]+?)<\!--\/np\_landing-->/', $buf, $landingMatches)) {
            $app->set('theme', 'landing');
            $app->set('themes.base', JPATH_ADMINISTRATOR . '/components/com_nicepage/views');
            $app->set('themeFile', 'landing.php');
        }
    }

    /**
     *  Proccess page content after rendering
     */
    public function onAfterRender()
    {
        $app = JFactory::getApplication();
        $pageContent = $app->getBody();

        // Move dataBridge object initialization at top head tag in admin panel
        if ($app->isAdmin() && $this->moveDataBridgeToTop($pageContent)) {
            return;
        }

        if ($app->isAdmin() || ($app->get('offline') && !JFactory::getUser()->authorise('core.login.offline'))) {
            return;
        }

        //Process article by np page elements
        if (preg_match('/<\!--np\_(content|landing)-->/', $pageContent) && preg_match('/<\!--np\_page_id-->([\s\S]+?)<\!--\/np\_page_id-->/', $pageContent, $matches)) {
            $pageId = $matches[1];
            $pageContent = str_replace($matches[0], '', $pageContent);
            $page = Factory::getPage($pageId);
            if ($page) {
                $pageContent = $page->get($pageContent);
            }
        }

        // Apply np settings to page
        $config = Factory::getConfig();
        $pageContent = $config->applySiteSettings($pageContent);

        //Add id attribute for typography parser
        if ($app->input->get('toEdit', '0') === '1') {
            $pageContent = preg_replace('/class="(item-page|u-page-root)/', ' id="np-test-container" class="$1', $pageContent);
        }

        $app->setBody($pageContent);
    }

    /**
     * Move data bridge to top head
     *
     * @param string $pageContent Page content
     *
     * @return bool
     */
    public function moveDataBridgeToTop($pageContent) {
        if (preg_match('/<\!--np\_databridge_script-->([\s\S]+?)<\!--\/np\_databridge_script-->/', $pageContent, $adminScriptsMatches)) {
            $adminPageScripts = $adminScriptsMatches[1];
            $pageContent = str_replace($adminScriptsMatches[0], '', $pageContent);
            $pageContent = preg_replace('/(<head>)/', '$1[[dataBridgeScript]]', $pageContent, 1);
            $pageContent = str_replace('[[dataBridgeScript]]', $adminPageScripts, $pageContent);
            JFactory::getApplication()->setBody($pageContent);
            return true;
        }
        return false;
    }
}