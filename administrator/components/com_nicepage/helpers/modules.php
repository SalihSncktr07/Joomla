<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
defined('_JEXEC') or die;

use NP\Processor\PositionsProcessor;
use NP\Utility\Theme;

/**
 * Style function for blocks on the page
 *
 * @param object $module  Current module
 * @param array  $params  Module parameters
 * @param array  $attribs Module statement attributes
 */
function modChrome_upstylefromplugin($module, &$params, &$attribs) {
    $number = $attribs['iterator'];
    $result = PositionsProcessor::$blockLayouts[$number];
    if (!empty($module->content) && $result) {
        if ($module->showtitle != 0) {
            $result = preg_replace('/<\!--block_header_content-->[\s\S]*?<\!--\/block_header_content-->/', $module->title, $result);
        } else {
            $result = preg_replace('/<\!--block_header-->[\s\S]+?<\!--\/block_header-->/', '', $result);
        }
        $moduleContent = str_replace('$', '\$', $module->content);
        $moduleContent = Theme::stylingDefaultControls($moduleContent);
        $result = preg_replace('/<\!--block_content_content-->[\s\S]*?<\!--\/block_content_content-->/', $moduleContent, $result);
        $result = preg_replace('/<\!--\/?block\_?(header|content)?-->/', '', $result);
        echo $result;
    }
}

/**
 * Style function for menu on the header
 *
 * @param object $module  Current module
 * @param array  $params  Module parameters
 * @param array  $attribs Module statement attributes
 */
function modChrome_menufromplugin($module, &$params, &$attribs) {
    JLoader::register('ModMenuHelper', JPATH_BASE . '/modules/mod_menu/helper.php');
    $list       = ModMenuHelper::getList($params);
    $base       = ModMenuHelper::getBase($params);
    $active     = ModMenuHelper::getActive($params);
    $default    = ModMenuHelper::getDefault();
    $active_id  = $active->id;
    $default_id = $default->id;
    $path       = $base->tree;
    $showAll    = $params->get('showAllChildren', 1);
    $class_sfx  = htmlspecialchars($params->get('class_sfx'), ENT_COMPAT, 'UTF-8');
    include dirname(JPATH_PLUGINS) . '/administrator/components/com_nicepage/views/controls/menu/default.php';
}