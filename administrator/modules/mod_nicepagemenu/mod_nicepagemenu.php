<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
defined('_JEXEC') or die;

$user = JFactory::getUser();
if ($user->guest) {
    return;
}

// Include the module helper classes.
if (!class_exists('ModNicepageMenuHelper')) {
    include dirname(__FILE__) . '/helper.php';
}
$nicepageComponentItems = ModNicepageMenuHelper::getNicepageComponent(true);
// Render the module layout
require JModuleHelper::getLayoutPath('mod_nicepagemenu', $params->get('layout', 'default'));
