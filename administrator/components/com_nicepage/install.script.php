<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
defined('_JEXEC') or die;

/**
 * Class Com_NicepageInstallerScript
 */
class Com_NicepageInstallerScript
{
    /**
     * Custom install operations
     *
     * @param object $parent Parent object
     */
    public function install($parent) {
        jimport('joomla.filesystem.folder');
        jimport('joomla.filesystem.file');

        $src = JPATH_ROOT . '/components/com_nicepage/assets/images/nicepage-images';

        $this->createFolder(JPATH_ROOT . '/images/nicepage-images');

        JFile::copy($src . '/default-image.jpg', JPATH_ROOT . '/images/nicepage-images/default-image.jpg');
    }

    /**
     * Create folder by path
     *
     * @param string $path Path for creating
     *
     * @return bool
     */
    public function createFolder($path)
    {
        if (JFolder::create($path)) {
            if (!JFile::exists($path . '/index.html')) {
                JFile::copy(JPATH_ROOT . '/components/index.html', $path . '/index.html');
            }
            return true;
        }
        return false;
    }

    /**
     * Update action for installing
     *
     * @param object $parent Parent object
     */
    public function update($parent)
    {
        return $this->install($parent);
    }

    /**
     * Postflight method for joomla core
     *
     * @param string $type   Extension type
     * @param object $parent Parent object
     *
     * @return bool
     */
    public function postflight($type, $parent)
    {
        $this->sectionsTableFixer();
        $this->paramsTableFixer();
        $this->updateLinkInAdminMenu();
        $this->clearUnusedPages();
        $this->fixedShowIntroArticleOption();

        $this->createDefaultSettings();

        return true;
    }

    public $dbName = 'nicepage';
    /**
     * Fixer for section table
     */
    public function sectionsTableFixer() {
        $db = JFactory::getDbo();
        if ($db->name == 'postgresql' || $db->name == 'pgsql') {
            $db->setQuery((string)'SELECT column_name FROM information_schema.columns WHERE table_name = \'' . $db->getPrefix() . $this->dbName . '_sections\'');
        } else {
            $db->setQuery((string)'DESCRIBE #__' . $this->dbName . '_sections');
        }
        $result = $db->loadColumn(0);
        if (is_array($result)) {
            if (!in_array('preview_props', $result)) {
                if ($db->name == 'postgresql' || $db->name == 'pgsql') {
                    $db->setQuery((string)'ALTER TABLE #__' . $this->dbName .'_sections ADD COLUMN preview_props text NOT NULL DEFAULT \'\'');
                } else {
                    $db->setQuery((string)'ALTER TABLE #__' . $this->dbName . '_sections ADD COLUMN preview_props mediumtext NOT NULL DEFAULT \'\' AFTER props');
                }
                $db->execute();
            }
            if (!in_array('autosave_props', $result)) {
                if ($db->name == 'postgresql' || $db->name == 'pgsql') {
                    $db->setQuery((string)'ALTER TABLE #__' . $this->dbName . '_sections ADD COLUMN autosave_props text NOT NULL DEFAULT \'\'');
                } else {
                    $db->setQuery((string)'ALTER TABLE #__' . $this->dbName . '_sections ADD COLUMN autosave_props mediumtext NOT NULL DEFAULT \'\' AFTER props');
                }
                $db->execute();
            }
        }
    }

    /**
     * Fixer for params
     */
    public function paramsTableFixer()
    {
        $db = JFactory::getDbo();
        $db->setQuery((string)'SELECT * FROM #__' . $this->dbName . '_params');
        $result = $db->loadResult();
        if (!$result) {
            // insert default params value
            $db->setQuery((string)'INSERT INTO #__' . $this->dbName .'_params (id, name, params) VALUES (1, \'com_' . $this->dbName . '\', \'{}\');');
            $db->execute();
        }
        // change params column type
        if ($db->name == 'postgresql' || $db->name == 'pgsql') {
            $db->setQuery((string)'ALTER TABLE #__' . $this->dbName . '_params ALTER COLUMN params TYPE text');
        } else {
            $db->setQuery((string)'ALTER TABLE #__' . $this->dbName . '_params MODIFY params mediumtext');
        }
        $db->execute();
    }

    /**
     * Update link in admin joomla menu
     */
    public function updateLinkInAdminMenu()
    {
        $db = JFactory::getDbo();
        $db->setQuery((string) 'UPDATE #__menu SET link = \'index.php?option=com_nicepage&task=nicepage.start\' WHERE title = \'COM_NICEPAGE\'');
        $db->execute();
    }

    /**
     * Clear unused pages
     */
    public function clearUnusedPages()
    {
        $db = JFactory::getDbo();
        $db->setQuery((string) 'DELETE FROM #__' . $this->dbName .'_sections WHERE page_id not in (SELECT id from #__content)');
        $db->execute();
    }

    /**
     * Update show_intro option for article
     */
    public function fixedShowIntroArticleOption()
    {
        $db = JFactory::getDbo();
        $db->setQuery((string) 'SELECT * FROM #__content WHERE id in (SELECT page_id from #__' . $this->dbName . '_sections)');
        $articles = $db->loadAssocList();
        if (count($articles) > 0) {
            foreach ($articles as $article) {
                $attribs = json_decode($article['attribs'], true);
                if (!$attribs) {
                    $attribs = array();
                }

                $showIntro = '';
                if (isset($attribs['show_intro'])) {
                    $showIntro = $attribs['show_intro'];
                }

                if ($showIntro != '0') {
                    $attribs['show_intro'] = '0';

                    $registry = new JRegistry();
                    $registry->loadArray($attribs);

                    $query = $db->getQuery(true);
                    $query->update('#__content');
                    $query->set($db->quoteName('attribs') . '=' . $db->quote($registry->toString()));
                    $query->where('id=' . (int) $article['id']);
                    $db->setQuery($query);
                    $db->execute();
                }
            }
        }
    }

    /**
     * Create default header and footer
     */
    public function createDefaultSettings()
    {
        $installSourceDir = dirname(__FILE__);

        $contentPath = '';
        if (file_exists(dirname($installSourceDir) . '/content/content.json')) {
            $contentPath = dirname($installSourceDir) . '/content/content.json';
        }

        $themeContentPath = dirname(dirname(JPATH_THEMES)) . '/templates/' . $this->getDefaultClientTemplate() . '/content/content.json';
        if (!$contentPath && file_exists($themeContentPath)) {
            $contentPath = $themeContentPath;
        }


        if ($contentPath) {
            JLoader::register('NicepageHelpersNicepage', $installSourceDir . '/admin/helpers/nicepage.php');
            JLoader::register('Nicepage_Data_Loader', $installSourceDir . '/admin/helpers/import.php');
            $loader = new Nicepage_Data_Loader();
            $loader->setRootUrl(JPATH_ROOT . '/');
            $loader->parse($contentPath);
            $loader->importClientLicenseMode();


            $paramsTable = NicepageHelpersNicepage::getParamsTable();
            $params = $paramsTable->getParameters();
            if (isset($params['header']) && isset($params['footer'])) {
                return;
            }
            $loader->loadParameters();
            $imagesPath = dirname($contentPath) . '/images';
            if (file_exists($imagesPath) && is_dir($imagesPath)) {
                $loader->setImagesPath($imagesPath);
                $loader->copyOnlyFoundImages();
            }
        }
    }

    /**
     * @return string
     */
    public function getDefaultClientTemplate() {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from('#__template_styles');
        $query->where('client_id = 0');
        $query->where('home=\'1\'');
        $db->setQuery($query);
        $ret = $db->loadObject();
        return $ret ? $ret->template : '';
    }
}