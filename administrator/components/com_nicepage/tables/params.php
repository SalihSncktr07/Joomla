<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
defined('_JEXEC') or die;

class PagesTableParams extends JTable
{
    /**
     * Constructor
     *
     * @param JDatabaseDriver $db Database connector object
     */
    public function __construct(&$db)
    {
        parent::__construct('#__nicepage_params', 'id', $db);
    }

    /**
     * Get params from table
     *
     * @return array
     */
    public function getParameters()
    {
        if (!$this->load(array('name' => 'com_nicepage'))) {
            $parameters = $this->getAll();
            $result = array();
            if (count($parameters) > 0) {
                for ($i = 0; $i < count($parameters); $i++) {
                    $param = $parameters[$i];
                    $result[$param['name']] = json_decode($param['params']);
                }
            }
            return $result;
        }
        $registry = new JRegistry();
        $registry->loadString($this->params);
        return $registry->toArray();
    }

    /**
     * Save params to table
     *
     * @param string $params Editor params
     */
    public function saveParameters($params)
    {
        if (count($params) > 0) {
            $this->removeAll();
            foreach ($params as $key => $value) {
                JTable::getInstance('Params', 'PagesTable')->save(
                    array(
                    'name' => $key,
                    'params'   => json_encode($value)
                    )
                );
            }
        }
    }

    /**
     * Get all parameters
     *
     * @return mixed
     */
    public function getAll()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($this->_tbl);
        $db->setQuery($query);
        return $db->loadAssocList();
    }

    /**
     * Remove all parameters
     */
    public function removeAll()
    {
        $db = $this->getDbo();
        $db->setQuery('TRUNCATE TABLE ' . $this->_tbl)->execute();
    }
}