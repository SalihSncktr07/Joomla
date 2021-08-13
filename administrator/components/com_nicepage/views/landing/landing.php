<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

defined('_JEXEC') or die;

JHtml::_('bootstrap.framework');

?>
<!DOCTYPE html>
<html>
<head>
    <jdoc:include type="head" />
</head>
<body class="{bodyClass}" style="{bodyStyle}" {bodyDataBg}>
<jdoc:include type="component" />
</body>
</html>