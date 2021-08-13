<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_nicepage/library/loader.php';

$controller = JControllerLegacy::getInstance('Nicepage');
$controller->execute(JFactory::getApplication()->input->get('task', 'display'));
$controller->redirect();
