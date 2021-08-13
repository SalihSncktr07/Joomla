<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
defined('_JEXEC') or die;

// Load translations
$lang = JFactory::getLanguage();
$lang->load('com_nicepage.sys', JPATH_ADMINISTRATOR);

if ($nicepageComponentItems && !empty($nicepageComponentItems->submenu)) {
    $nicepageMenu = '<ul id="nicepage-menu" class="nav" >';
    $nicepageMenu .= '<li class="dropdown" ><a class="dropdown-toggle" data-toggle="dropdown" href="#"><span class="nicepage-icon">&nbsp;</span>' . JText::_($nicepageComponentItems->title) . '<span class="caret"></span></a>';
    $nicepageMenu .= '<ul class="dropdown-menu">';
    foreach ($nicepageComponentItems->submenu as $sub) {
        if (strpos($sub->link, 'view=theme') !== false) {
            if (strpos($sub->link, 'element=') !== false) {
                if (strpos($sub->link, 'element=Header') !== false) {
                    $nicepageMenu .= '<li class="divider"><span></span></li>';
                }
                $nicepageMenu .= '<li><a class="' . $sub->class . '" href="' . $sub->link . '">' . JText::_($sub->title) . '</a></li>';
            } else {
                $nicepageMenu .= '<li class="dropdown-submenu"><a class="' . $sub->class . '" href="' . $sub->link . '">' . JText::_($sub->title) . '</a>';
                $nicepageMenu .= '<ul class="dropdown-menu">';
            }
        } else {
            $nicepageMenu .= '<li><a class="' . $sub->class . '" href="' . $sub->link . '">' . JText::_($sub->title) . '</a></li>';
        }
    }
    $nicepageMenu .= '</ul></li>'; // close Theme menu
    $nicepageMenu .= '</ul>';
    $nicepageMenu .= '</li></ul>';
    echo $nicepageMenu;
    $nicepageIcon = JUri::getInstance(JUri::root())->toString() . '/components/com_nicepage/assets/images/button-icon.png?r=' . md5(mt_rand(1, 100000));
    ?>
    <style>
        .nicepage-icon {
            background: url('<?php echo $nicepageIcon; ?>') no-repeat;
            float: left;
            width: 16px;
            height: 16px;
            margin-right: 6px;
            background-size: 16px;
        }
    </style>
    <script>
        var mainMenu = document.getElementById('menu'),
            npMenu = document.getElementById('nicepage-menu');
        if (mainMenu && npMenu) {
            mainMenu.innerHTML = mainMenu.innerHTML + npMenu.innerHTML;
            npMenu.parentNode.removeChild(npMenu);
        }
    </script>
    <?php
}