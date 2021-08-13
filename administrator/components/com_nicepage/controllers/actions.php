<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
defined('_JEXEC') or die;

/**
 * Class NicepageControllerActions
 */
class NicepageControllerActions extends JControllerForm
{
    /**
     * Execute actions controller
     *
     * @param string $action Type of action
     */
    public function execute($action)
    {
        $app = JFactory::getApplication();

        $viewName = 'actions';
        $app->input->set('action', $action);
        $app->input->set('view', $viewName);

        $document = JFactory::getDocument();
        $document->setType('json');

        return parent::execute($viewName);
    }
}