<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
defined('_JEXEC') or die;

JLoader::register('Nicepage_Data_Mappers', JPATH_ADMINISTRATOR . '/components/com_nicepage/tables/mappers.php');

/**
 * Class Nicepage_Data_Loader
 */
class Nicepage_Data_Loader
{
    /**
     * @var null Sample data object
     */
    private $_data = null;

    /**
     * @var int Default category id
     */
    private $_blogCategoryId = 0;
    /**
     * Numeric identificator of the currently selected template style in Joomla
     * administrator.
     */
    private $_style;

    /**
     * @var string Sample images path
     */
    private $_images = '';

    /**
     * @var string Sample fonts path
     */
    private $_fonts = '';

    /**
     * @var array Sample images path
     */
    private $_foundImages = array();

    /**
     * Name of the template.
     */
    private $_template = '';

    /**
     * @var string Cms root url
     */
    private $_rootUrl = '';

    /**
     * @var string Sample data ids string
     */
    private $_dataIds = array();

    /**
     * @var bool Replace sample data flag
     */
    private $_replace = false;

    /**
     * @var bool Update settings of nicepage plugin
     */
    private $_updatePluginSettings = false;

    /**
     * @var bool Import menus
     */
    private $_importMenu = true;

    /**
     * @var string Commom css parts
     */
    private $_siteStyleCssParts = '';

    /**
     * @var string Prefix of table sections
     */
    private $_dbName = 'nicepage';

    /**
     * Method to load sample data.
     *
     * @param string $file           File path to sample data
     * @param bool   $isThemeContent Flag for theme
     *
     * @return null|string|void
     */
    public function load($file, $isThemeContent = false)
    {
        $config = JFactory::getConfig();
        $live_site = $config->get('live_site');
        if ($isThemeContent) {

        }
        $p = dirname(dirname(JURI::current()));
        $root = trim($live_site) != '' ? JURI::root(true) : ($isThemeContent ? dirname(dirname($p)) : $p);
        if ('/' === substr($root, -1)) {
            $this->_rootUrl  = substr($root, 0, -1);
        } else {
            $this->_rootUrl  = $root;
        }

        $path = realpath($file);
        if (false === $path) {
            return;
        }

        $images = dirname($path) . DIRECTORY_SEPARATOR . 'images';
        if (file_exists($images) && is_dir($images)) {
            $this->_images = $images;
        }

        $fonts = dirname($path) . DIRECTORY_SEPARATOR . 'fonts';
        if (file_exists($fonts) && is_dir($fonts)) {
            $this->_fonts = $fonts;
        }

        if ($isThemeContent) {
            $this->_template = basename(dirname(dirname($path)));
        }

        $categories = Nicepage_Data_Mappers::get('category');
        $categoryList = $categories->find(array('extension' => 'com_content'));
        foreach ($categoryList as & $categoryListItem) {
            if ($this->_blogCategoryId === 0 || $categoryListItem->id < $this->_blogCategoryId) {
                $this->_blogCategoryId = $categoryListItem->id;
            }
        }

        return $this->_parse($path);
    }

    /**
     * Method to execute installing sample data.
     *
     * @param array $params Sample data installing parameters
     */
    public function execute($params)
    {
        $callback = array();
        $callback[] = $this;
        $callback[] = '_error';
        Nicepage_Data_Mappers::errorCallback($callback);

        if (isset($params['updatePluginSettings']) && $params['updatePluginSettings'] == '1') {
            $this->_updatePluginSettings = true;
        }

        if (isset($params['importMenus']) && $params['importMenus'] == '0') {
            $this->_importMenu = false;
        }

        if ($this->_template) {
            $action = isset($params['action']) && is_string($params['action']) ? $params['action'] : '';
            if (0 == strlen($action) || !in_array($action, array('check', 'run', 'nicepage'))) {
                return 'Invalid action.';
            }
            $this->_style = isset($params['id']) && is_string($params['id'])
            && ctype_digit($params['id']) ? intval($params['id'], 10) : -1;
            if (-1 === $this->_style) {
                return 'Invalid style id.';
            }
            $this->_replace = isset($params['replace']) && $params['replace'] == '1' ? true : false;
            switch ($action) {
            case 'check':
                echo 'result:' . ($this->_contentIsInstalled() ? '1' : '0');
                break;
            case 'run':
                $this->_load();
                $dataIds = $this->_getDataIds();
                $parameters = array();
                if ($dataIds) {
                    $parameters['jform_params_dataIds'] = json_encode($dataIds);
                }
                echo 'result:' . (count($parameters) ? json_encode($parameters) : 'ok');
                break;
            }
        } else {
            $this->_replace = isset($params['replaceStatus']) && $params['replaceStatus'] == '1' ? true : false;
            $this->_load();
        }
    }

    /**
     * Method to throw errors.
     *
     * @param string $msg  Text message
     * @param int    $code Number error
     *
     * @throws Exception
     */
    public function _error($msg, $code)
    {
        throw new Exception($msg);
    }

    /**
     * Method check content installing
     *
     * @return bool
     */
    private function _contentIsInstalled()
    {
        $content = Nicepage_Data_Mappers::get('content');

        if (($ids = $this->_getDataIds()) !== '') {
            foreach ($ids as $id) {
                $contentList = $content->find(array('id' => $id));
                if (0 != count($contentList)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Installing sample data.
     */
    private function _load()
    {
        if ($this->_replace) {
            $this->_deletePreviousContent();
        }
        $this->_loadPosts();
        $this->_loadPages();
        $this->_saveDataIds();
        if ($this->_template) {
            if ($this->_importMenu) {
                $this->_loadMenus();
                $this->_loadModules();
                $this->_configureModulesVisibility();
            }
            $this->_configureEditor();
        }
        $this->_updatePages();
        $this->_loadParameters();
        $this->_copyImages();
        $this->_copyFonts();
    }

    /**
     * Import client mode option
     */
    public function importClientLicenseMode()
    {
        if (!isset($this->_data['Parameters'])) {
            return;
        }

        $parameters = $this->_data['Parameters'];
        if (!isset($parameters['nicepageSiteSettings'])) {
            return;
        }

        $siteSettings = json_decode($parameters['nicepageSiteSettings'], true);
        if (empty($siteSettings)) {
            return;
        }

        $cliendMode = isset($siteSettings['clientMode']) ? $siteSettings['clientMode'] : false;

        $config = NicepageHelpersNicepage::getConfig();

        if (isset($config['siteSettings'])) {
            $newSiteSettings = json_decode($config['siteSettings'], true);
            $newSiteSettings['clientMode'] = $cliendMode;
            NicepageHelpersNicepage::saveConfig(array('siteSettings' => json_encode($newSiteSettings)));
        }
    }

    /**
     * Load Parameters
     */
    private function _loadParameters()
    {
        if (!isset($this->_data['Parameters'])) {
            return;
        }

        $parameters = $this->_data['Parameters'];

        $config = NicepageHelpersNicepage::getConfig();
        $new = array();
        if (($this->_updatePluginSettings || !isset($config['siteSettings'])) && $parameters['nicepageSiteSettings']) {
            $new['siteSettings'] = $this->_processingContent($parameters['nicepageSiteSettings']);
        }
        if (($this->_updatePluginSettings || !isset($config['publishDialogs'])) && isset($parameters['publishDialogs'])) {
            $new['publishDialogs'] = $this->_processingContent($parameters['publishDialogs']);
        }
        if ($this->_siteStyleCssParts) {
            $new['siteStyleCssParts'] = $this->_processingContent($this->_siteStyleCssParts);
        }
        if (isset($parameters['header'])) {
            $header = $parameters['header'];
            $header['html'] = $this->_processingContent($header['html'], 'editor');
            $new['header'] = $this->_processingContent(json_encode($header), 'publish');
        }
        if (isset($parameters['footer'])) {
            $footer = $parameters['footer'];
            $footer['html'] = $this->_processingContent($footer['html'], 'editor');
            $new['footer'] = $this->_processingContent(json_encode($footer), 'publish');
        }
        if (isset($parameters['cookiesConsent'])) {
            $new['cookiesConsent'] = $this->_processingContent($parameters['cookiesConsent'], 'publish');
        }
        if (isset($parameters['backToTop'])) {
            $new['backToTop'] = $this->_processingContent($parameters['backToTop'], 'publish');
        }
        if (count($new) > 0) {
            NicepageHelpersNicepage::saveConfig($new);
        }
    }

    /**
     * Load menus from content data
     */
    private function _loadMenus()
    {
        if (!isset($this->_data['Menus'])) {
            return;
        }

        if (count($this->_data['Menus']) > 0) {
            $menusMapper = Nicepage_Data_Mappers::get('menu');
            $menuItemsMapper = Nicepage_Data_Mappers::get('menuItem');

            $home = $menuItemsMapper->find(array('home' => 1));
            $homeItem = count($home) > 0 ? $home[0] : null;
            $defaultMenuDataFound = false;
            foreach ($this->_data['Menus'] as $menuData) {
                foreach ($menuData['items'] as $key => $itemData) {
                    if (isset($itemData['default']) && $itemData['default']) {
                        $defaultMenuDataFound = true;
                    }
                }
            }
            // Create a temporary menu with one item to clean up the Home flag:
            $rndMenu = null;
            if ($homeItem && $defaultMenuDataFound) {
                $rndMenu = $menusMapper->create();
                $rndMenu->title = $rndMenu->menutype = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 10);
                $status = $menusMapper->save($rndMenu);
                if (is_string($status)) {
                    trigger_error($status, E_USER_ERROR);
                }
                $rndItem = $menuItemsMapper->create();
                $rndItem->home = '1';
                $rndItem->checked_out = $homeItem->checked_out;
                $rndItem->menutype = $rndMenu->menutype;
                $rndItem->alias = $rndItem->title = $rndMenu->menutype;
                $rndItem->link = 'index.php?option=com_content&view=article&id=';
                $rndItem->type = 'component';
                $rndItem->component_id = '22';
                $rndItem->params = $this->_paramsToString(array());
                $status = $menuItemsMapper->save($rndItem);
                if (is_string($status)) {
                    trigger_error($status, E_USER_ERROR);
                }
            }


            foreach ($this->_data['Menus'] as  $index => $menuData) {
                if ($index == 'default') {
                    continue;
                }
                $menuList = $menusMapper->find(array('title' => $menuData['caption']));
                foreach ($menuList as $menuListItem) {
                    $status = $menusMapper->delete($menuListItem->id);
                    if (is_string($status)) {
                        trigger_error($status, E_USER_ERROR);
                    }
                }
            }

            $parameters = isset($this->_data['Parameters']) ? $this->_data['Parameters'] : null;
            $menuHomePageId = '';
            if ($parameters && isset($parameters['menuHomePageId'])) {
                $menuHomePageId = $parameters['menuHomePageId'];
            }

            $blogPostsCount = '4';
            if ($parameters && isset($parameters['blogPostsCount'])) {
                $blogPostsCount = $parameters['blogPostsCount'];
            }

            $foundHomeItem = false;
            foreach ($this->_data['Menus'] as $index => $menuData) {
                if ($index == 'default') {
                    continue;
                }
                if ($foundHomeItem && $index == 'home') {
                    continue;
                }
                $menu = $menusMapper->create();
                $menu->title = $menuData['caption'];
                $menu->menutype = $menuData['name'];
                $status = $menusMapper->save($menu);
                if (is_string($status)) {
                    trigger_error($status, E_USER_ERROR);
                }

                foreach ($menuData['items'] as $key => $itemData) {
                    $item = $menuItemsMapper->create();

                    $item->menutype = $menu->menutype;
                    $item->title = $itemData['caption'];
                    $item->alias = $itemData['name'];

                    $href = $this->_getPropertyValue('href', $itemData, '');
                    $type = $this->_getPropertyValue('type', $itemData, '');

                    $postId = '';
                    $contentPageId = '';
                    $pageData = null;
                    if (preg_match('/\[page_(\d+)\]/', $href, $matches)) {
                        $pages = $this->_data['Pages'];
                        $pid = $matches[1];
                        $hrefParts = explode('#', $href);
                        $pageData = isset($pages[$pid]) ? $pages[$pid] : array();
                        if (isset($pageData['joomla_id'])) {
                            $contentPageId = $matches[1];
                            $type = 'single-article';
                            $postId = $pageData['joomla_id'];
                            if (count($hrefParts) > 1) {
                                $postId .= '#' . $hrefParts[1];
                            }
                        } else {
                            $href = '#';
                            $type = 'custom';
                        }
                    }

                    if (preg_match('/\[blog_(\d+)\]/', $href, $matches)) {
                        $type = 'category-blog-layout';
                        $categoryId = $this->_blogCategoryId ? $this->_blogCategoryId : $this->_getDefaultCategory();
                    }

                    if (!$postId && !$type) {
                        $type = 'custom';
                    }

                    if (!$foundHomeItem) {
                        if ($menuHomePageId && strpos($href, $menuHomePageId) !== false) {
                            $item->home = '1';
                            $foundHomeItem = true;
                        }

                        if (!$menuHomePageId && $postId) {
                            $item->home = '1';
                            $foundHomeItem = true;
                        }
                    }


                    switch ($type) {
                    case 'single-article':
                        $item->link = 'index.php?option=com_content&view=article&id=' . $postId;
                        $item->type = 'component';
                        $item->component_id = '22';
                        $params = array
                        (
                            'show_title' => '1',
                            'link_titles' => '',
                            'show_intro' => '0',
                            'show_category' => '0',
                            'link_category' => '',
                            'show_parent_category' => '0',
                            'link_parent_category' => '',
                            'show_author' => '0',
                            'link_author' => '',
                            'show_create_date' => '0',
                            'show_modify_date' => '0',
                            'show_publish_date' => '0',
                            'show_item_navigation' => '0',
                            'show_vote' => '0',
                            'show_icons' => '0',
                            'show_print_icon' => '0',
                            'show_email_icon' => '0',
                            'show_hits' => '0',
                            'show_noauth' => '',
                            'menu-anchor_title' => '',
                            'menu-anchor_css' => '',
                            'menu_image' => '',
                            'menu_text' => '1',
                            'page_title' => '',
                            'show_page_heading' => '0',
                            'page_heading' => '',
                            'pageclass_sfx' => '',
                            'menu-meta_description' => $pageData && isset($pageData['description']) ? $pageData['description'] : '',
                            'menu-meta_keywords' => $pageData && isset($pageData['keywords']) ? $pageData['keywords'] : '',
                            'robots' => '',
                            'secure' => '0',
                            'page_title' => $pageData && isset($pageData['titleInBrowser']) ? $pageData['titleInBrowser'] : ''
                        );
                        break;
                    case 'category-blog-layout':
                        $item->link = 'index.php?option=com_content&view=category&layout=blog&id=' . $categoryId;
                        $item->type = 'component';
                        $item->component_id = '22';
                        $params = array
                        (
                            'layout_type' => 'blog',
                            'show_category_title' => '',
                            'show_description' => '',
                            'show_description_image' => '',
                            'maxLevel' => '',
                            'show_empty_categories' => '',
                            'show_no_articles' => '',
                            'show_subcat_desc' => '',
                            'show_cat_num_articles' => '',
                            'page_subheading' => '',
                            'num_leading_articles' => '0',
                            'num_intro_articles' => $blogPostsCount,
                            'num_columns' => '1',
                            'num_links' => '',
                            'multi_column_order' => '',
                            'show_subcategory_content' => '',
                            'orderby_pri' => '',
                            'orderby_sec' => 'rdate',
                            'order_date' => '',
                            'show_pagination' => '',
                            'show_pagination_results' => '',
                            'show_title' => '',
                            'link_titles' => '',
                            'show_intro' => '',
                            'show_category' => '',
                            'link_category' => '',
                            'show_parent_category' => '',
                            'link_parent_category' => '',
                            'show_author' => '',
                            'link_author' => '',
                            'show_create_date' => '',
                            'show_modify_date' => '',
                            'show_publish_date' => '',
                            'show_item_navigation' => '',
                            'show_vote' => '',
                            'show_readmore' => '',
                            'show_readmore_title' => '',
                            'show_icons' => '',
                            'show_print_icon' => '',
                            'show_email_icon' => '',
                            'show_hits' => '',
                            'show_noauth' => '',
                            'show_feed_link' => '',
                            'feed_summary' => '',
                            'menu-anchor_title' => '',
                            'menu-anchor_css' => '',
                            'menu_image' => '',
                            'menu_text' => 1,
                            'page_title' => '',
                            'show_page_heading' => 0,
                            'page_heading' => '',
                            'pageclass_sfx' => '',
                            'menu-meta_description' => '',
                            'menu-meta_keywords' => '',
                            'robots' => '',
                            'secure' => 0,
                            'page_title' => ''
                        );
                        break;
                    default:
                        $item->link = $href;
                        $item->type = 'url';
                        $item->component_id = '0';
                        $params = array
                        (
                            'menu-anchor_title' => '',
                            'menu-anchor_css' => '',
                            'menu_image' => '',
                            'menu_text' => 1
                        );
                    }

                    // parameters:
                    $item->params = $this->_paramsToString($params);

                    // parent:
                    if (isset($itemData['parent'])) {
                        $item->setLocation($this->_data['Menus'][$index]['items'][$itemData['parent']]['joomla_id'], 'last-child');
                    }

                    $status = $menuItemsMapper->save($item);
                    if (is_string($status)) {
                        trigger_error($status, E_USER_ERROR);
                    }

                    $this->_data['Menus'][$index]['items'][$key]['joomla_id'] = $item->id;
                    if ($contentPageId && $type == 'single-article') {
                        $this->_data['Pages'][$contentPageId]['joomla_menu_id'] = $item->id;
                    }
                }
            }
            if (!$foundHomeItem && $homeItem && $rndItem) {
                $homeItem->checked_out = $rndItem->checked_out;
                $homeItem->home = '1';
                $status = $menuItemsMapper->save($homeItem);
                if (is_string($status)) {
                    trigger_error($status, E_USER_ERROR);
                }
            }
            if ($rndMenu) {
                $status = $menusMapper->delete($rndMenu->id);
                if (is_string($status)) {
                    trigger_error($status, E_USER_ERROR);
                }
            }
        }
    }

    /**
     * Create modules from import data
     */
    private function _loadModules() {
        if (!isset($this->_data['Modules'])) {
            return;
        }

        $modulesMapper = Nicepage_Data_Mappers::get('module');

        foreach ($this->_data['Modules'] as $moduleData) {
            $modulesList = $modulesMapper->find(array('title' => $moduleData['title']));
            foreach ($modulesList as $modulesListItem) {
                $status = $modulesMapper->delete($modulesListItem->id);
            }
        }

        $order = array();

        foreach ($this->_data['Modules'] as $key => $moduleData) {
            $module = $modulesMapper->create();
            $module->title = $moduleData['title'];
            $module->position = $moduleData['position'];
            $style = isset($moduleData['style']) ? $moduleData['style'] : '';
            $params = array();

            if ($moduleData['type'] == 'cart' && !file_exists(JPATH_ROOT .'/modules/mod_virtuemart_cart/tmpl/default.php')) {
                continue;
            }

            switch ($moduleData['type']) {
            case 'menu':
                $module->module = 'mod_menu';
                $params = array
                (
                    'menutype' => $moduleData['menu'],
                    'startLevel' => '1',
                    'endLevel' => '0',
                    'showAllChildren' => '1',
                    'tag_id' => '',
                    'class_sfx' => '',
                    'window_open' => '',
                    'layout' => '_:default',
                    'moduleclass_sfx' => $style,
                    'cache' => '1',
                    'cache_time' => '900',
                    'cachemode' => 'itemid'
                );
                break;
            case 'breadcrumbs':
                $module->module = 'mod_breadcrumbs';
                $params = array
                (
                    'showHere' => '1',
                    'showHome' => '1',
                    'homeText' => '',
                    'showLast' => '1',
                    'separator' => '1',
                    'layout' => '_:default',
                    'moduleclass_sfx' => '',
                    'cache' => '0',
                    'cache_time' => '0',
                    'cachemode' => 'itemid'
                );
                break;
            case 'cart':
                $module->module = 'mod_virtuemart_cart';
                $params = array
                (
                    'moduleid_sfx' => '',
                    'moduleclass_sfx' => '',
                    'show_price' => '1',
                    'show_product_list' => '1',
                    'separator' => '1',
                    'layout' => '_:default',
                    'module_tag' => 'div',
                    'bootstrap_size' => '0',
                    'header_tag' => 'h3',
                    'header_class' => '',
                    'style' => "0"
                );
                break;
            case 'custom':
                $module->module = 'mod_custom';
                $module->content = $this->_processingContent($moduleData['content']);
                $params = array
                (
                    'prepare_content' => '1',
                    'layout' => '_:default',
                    'moduleclass_sfx' => '',
                    'cache' => '1',
                    'cache_time' => '900',
                    'cachemode' => 'static'
                );
                break;
            }
            $module->showtitle = 'true' == $moduleData['showTitle'] ? '1' : '0';
            // style:
            if (isset($moduleData['style']) && isset($params['moduleclass_sfx'])) {
                $params['moduleclass_sfx'] = $moduleData['style'];
            }
            // parameters:
            $module->params = $this->_paramsToString($params);

            // ordering:
            if (!isset($order[$moduleData['position']])) {
                $order[$moduleData['position']] = 1;
            }
            $module->ordering = $order[$moduleData['position']];
            $order[$moduleData['position']]++;

            $status = $modulesMapper->save($module);
            if (is_string($status)) {
                trigger_error($status, E_USER_ERROR);
            }
            $this->_data['Modules'][$key]['joomla_id'] = $module->id;
        }
    }

    /**
     * To configure visibility of modules
     */
    private function _configureModulesVisibility()
    {
        if (!isset($this->_data['Modules'])) {
            return;
        }
        if (!isset($this->_data['Modules'])) {
            return;
        }

        $contentMenuItems = array();

        foreach ($this->_data['Menus'] as $index => $menuData) {
            if ($index == 'default') {
                continue;
            }
            foreach ($menuData['items'] as $itemData) {
                if (isset($itemData['joomla_id'])) {
                    $contentMenuItems[] = $itemData['joomla_id'];
                }
            }
        }

        $contentModules = array();
        foreach ($this->_data['Modules'] as $widgetData) {
            $contentModules[] = $widgetData['joomla_id'];
        }

        $modules = Nicepage_Data_Mappers::get('module');
        $menuItems = Nicepage_Data_Mappers::get('menuItem');

        $userMenuItems = array();
        $menuItemList = $menuItems->find(array('scope' => 'site'));
        foreach ($menuItemList as $menuItem) {
            if (in_array($menuItem->id, $contentMenuItems)) {
                continue;
            }
            $userMenuItems[] = $menuItem->id;
        }

        $moduleList = $modules->find(array('scope' => 'site'));
        foreach ($moduleList as $moduleListItem) {
            if (in_array($moduleListItem->id, $contentModules)) {
                $modules->enableOn($moduleListItem->id, $contentMenuItems);
            } else {
                $pages = $modules->getAssignment($moduleListItem->id);
                if (1 == count($pages) && '0' == $pages[0]) {
                    $modules->disableOn($moduleListItem->id, $contentMenuItems);
                }
                if (0 < count($pages) && 0 > $pages[0]) {
                    $disableOnPages = array_unique(array_merge(array_map('abs', $pages), $contentMenuItems));
                    $modules->disableOn($moduleListItem->id, $disableOnPages);
                }
            }
        }
    }

    /**
     * Get value from json by property name
     *
     * @param string $property Property name
     * @param array  $a        Data
     * @param string $default  Default value if not exists
     *
     * @return mixed|string
     */
    private function _getPropertyValue($property, $a = array(), $default = '')
    {
        if (array_key_exists($property, $a)) {
            return $a[$property];
        }
        return $default;
    }

    /**
     * Delete previous content
     */
    private function _deletePreviousContent()
    {
        $content = Nicepage_Data_Mappers::get('content');

        if (($ids = $this->_getDataIds()) !== '') {
            foreach ($ids as $id) {
                $contentList = $content->find(array('id' => $id));
                if (0 != count($contentList)) {
                    $content->delete($contentList[0]->id);
                    // delete sections
                    $db = JFactory::getDBO();
                    $query = $db->getQuery(true);
                    $query->delete('#__' . $this->_dbName . '_sections')
                        ->where($db->qn('page_id') . ' = ' . $db->q($contentList[0]->id));
                    $db->setQuery($query);
                    try {
                        $db->execute();
                    }
                    catch (Exception $exc) {
                        // Nothing
                    }
                }
            }
        }
    }

    /**
     * Method to save sample data ids
     */
    private function _saveDataIds()
    {
        if (count($this->_dataIds) < 1) {
            return;
        }

        $parameters = $this->_getExtOptions();
        $parameters['dataIds'] = json_encode($this->_dataIds);
        $this->_setExtOptions($parameters);
    }

    /**
     * Method to get sample data ids
     *
     * @return array|string
     */
    private function _getDataIds()
    {
        $parameters = $this->_getExtOptions();
        if (isset($parameters['dataIds']) && $parameters['dataIds']) {
            $dataIds = json_decode($parameters['dataIds'], true);
            if (!$dataIds) {
                $dataIds = explode(',', $parameters['dataIds']);
            }
            return $dataIds;
        } else {
            return '';
        }
    }

    /**
     * Method to get or create default category id
     *
     * @throws Exception
     */
    private function _getDefaultCategory()
    {
        $categories = Nicepage_Data_Mappers::get('category');

        $categoryList = $categories->find(array('title' => 'Uncategorised', 'extension' => 'com_content'));
        foreach ($categoryList as & $categoryListItem) {
            return  $categoryListItem->id;
        }

        $category = $categories->create();
        $category->title = 'Uncategorised';
        $category->extension = 'com_content';
        $category->metadata = $this->_paramsToString(array('robots' => '', 'author' => '', 'tags' => ''));
        $status = $categories->save($category);
        if (is_string($status)) {
            return $this->_error($status, 1);
        }
        return $category->id;
    }

    /**
     * Method load posts to cms
     *
     * @throws Exception
     */
    private function _loadPosts()
    {
        if (!isset($this->_data['Posts']) || count($this->_data['Posts']) == 0) {
            return;
        }

        $categories = Nicepage_Data_Mappers::get('category');
        $content = Nicepage_Data_Mappers::get('content');

        $categoryList = $categories->find(array('title' => 'Articles'));

        if ($this->_replace) {
            foreach ($categoryList as & $categoryListItem) {
                $categories->delete($categoryListItem->id);
            }
        }

        if ($this->_replace || count($categoryList) == 0) {
            $category = $categories->create();
            $category->title = 'Articles';
            $category->extension = 'com_content';
            $category->metadata = $this->_paramsToString(array('robots' => '', 'author' => '', 'tags' => ''));
            $status = $categories->save($category);
            if (is_string($status)) {
                return $this->_error($status, 1);
            }
            $this->_blogCategoryId = $category->id;
        } else {
            $this->_blogCategoryId = $categoryList[0]->id;
        }

        $key = 0;
        $date = JFactory::getDate();
        foreach ($this->_data['Posts'] as $alias =>& $articleData) {
            $titleData = isset($articleData['title']) && $articleData['title'] ? $articleData['title'] : 'Post';
            $key++;
            $article = $content->create();
            $article->catid = $this->_blogCategoryId;
            list($title, $alias) = $this->_generateNewTitle($this->_blogCategoryId, $titleData, $alias, $key);
            $article->title = $title;
            $article->alias = $alias;

            // Extra properties for post
            /*$rawIntro = $this->_processingContent(isset($articleData['rawIntro']) ? $articleData['rawIntro'] : '', 'relative');
            $rawFull = $this->_processingContent(isset($articleData['rawFull']) ? $articleData['rawFull'] : '', 'relative');*/

            $excerpt = $this->_processingContent(isset($articleData['excerpt']) ? $articleData['excerpt'] : '', 'relative');
            $html = $this->_processingContent(isset($articleData['html']) ? $articleData['html'] : '', 'relative');
            $article->introtext = $excerpt;
            $article->fulltext = $html;

            $image = $this->_processingContent(isset($articleData['featured']) ? $articleData['featured'] : '', 'relative');
            $images = array(
                'image_intro' => $image,
                'float_intro' => '',
                'image_intro_alt' => '',
                'image_intro_caption' => '',
                'image_fulltext' => $image,
                'float_fulltext' => '',
                'image_fulltext_alt' => '',
                'image_fulltext_caption' => '',
            );
            $article->images = json_encode($images);

            $article->attribs = $this->_paramsToString(
                array (
                    'show_title' => '',
                    'link_titles' => '',
                    'show_intro' => '0',
                    'show_category' => '',
                    'link_category' => '',
                    'show_parent_category' => '',
                    'link_parent_category' => '',
                    'show_author' => '',
                    'link_author' => '',
                    'show_create_date' => '',
                    'show_modify_date' => '',
                    'show_publish_date' => '',
                    'show_item_navigation' => '',
                    'show_icons' => '',
                    'show_print_icon' => '',
                    'show_email_icon' => '',
                    'show_vote' => '',
                    'show_hits' => '',
                    'show_noauth' => '',
                    'alternative_readmore' => '',
                    'article_layout' => ''
                )
            );
            $article->metadata = $this->_paramsToString(array('robots' => '', 'author' => '', 'rights' => '', 'xreference' => '', 'tags' => ''));
            $article->metakey = ''; //support postgresql
            $article->metadesc = ''; //support postgresql
            $status = $content->save($article);
            if (is_string($status)) {
                return $this->_error($status, 1);
            }

            // Update modified date
            $date->modify('-1 second');
            $strDate = $date->toSql();
            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query->update('#__content');
            $query->set($db->quoteName('modified') . '=' . $db->quote($strDate));
            $query->set($db->quoteName('created') . '=' . $db->quote($strDate));
            $query->set($db->quoteName('publish_up') . '=' . $db->quote($strDate));
            $query->where('id=' . $article->id);
            $db->setQuery($query);
            $db->execute();

            $articleData['joomla_id'] = $article->id;
        }
    }

    /**
     * Method load sample pages to cms
     *
     * @throws Exception
     */
    private function _loadPages()
    {
        $content = Nicepage_Data_Mappers::get('content');
        $defaultCategoryId = $this->_getDefaultCategory();
        $key = 0;
        $contentPageIds = array_keys($this->_data['Pages']);
        foreach ($this->_data['Pages'] as & $articleData) {
            $contentPageId = array_shift($contentPageIds);
            $key++;
            $article = $content->create();
            $article->catid = $defaultCategoryId;
            list($title, $alias) = $this->_generateNewTitle($defaultCategoryId, $articleData['caption'], '', $key);
            $article->title = $title;
            $article->alias = $alias;
            $article->introtext = isset($articleData['introHtml']) ? $articleData['introHtml'] : '';
            $article->attribs = $this->_paramsToString(
                array (
                    'show_title' => '',
                    'link_titles' => '',
                    'show_intro' => '0',
                    'show_category' => '',
                    'link_category' => '',
                    'show_parent_category' => '',
                    'link_parent_category' => '',
                    'show_author' => '',
                    'link_author' => '',
                    'show_create_date' => '',
                    'show_modify_date' => '',
                    'show_publish_date' => '',
                    'show_item_navigation' => '',
                    'show_icons' => '',
                    'show_print_icon' => '',
                    'show_email_icon' => '',
                    'show_vote' => '',
                    'show_hits' => '',
                    'show_noauth' => '',
                    'alternative_readmore' => '',
                    'article_layout' => ''
                )
            );
            $article->metadata = $this->_paramsToString(array('robots' => '', 'author' => '', 'rights' => '', 'xreference' => '', 'tags' => ''));
            $article->metakey = ''; //support postgresql
            $article->metadesc = ''; //support postgresql
            $status = $content->save($article);
            if (is_string($status)) {
                return $this->_error($status, 1);
            }
            $articleData['joomla_id'] = $article->id;
            $this->_dataIds[$contentPageId] = $article->id;
        }
    }

    /**
     * Update page content
     *
     * @throws Exception
     */
    private function _updatePages() {
        $content = Nicepage_Data_Mappers::get('content');
        $config = NicepageHelpersNicepage::getConfig();
        foreach ($this->_data['Pages'] as & $articleData) {
            $article = $content->fetch($articleData['joomla_id']);
            $seoOptions = array(
                'title' => '',
                'keywords' => '',
                'description' => ''
            );
            if (!is_null($article)) {
                $fullText = '';
                if (isset($articleData['properties'])) {
                    $properties = $articleData['properties'];
                    if (($this->_updatePluginSettings || !isset($config['siteStyleCss'])) && isset($this->_data['Parameters']) && isset($this->_data['Parameters']['publishNicePageCss'])) {
                        $publishNicepageCss = $this->_data['Parameters']['publishNicePageCss'];
                        list($siteStyleCssParts, $pageCssUsedIds) = NicepageHelpersNicepage::processAllColors($publishNicepageCss, $properties['publishHtml']);
                        $this->_siteStyleCssParts = $siteStyleCssParts;
                        $properties['pageCssUsedIds'] = $pageCssUsedIds;
                    }


                    $properties['dialogs'] = isset($properties['dialogs']) ? $this->_processingContent($properties['dialogs'], 'publish') : '';
                    $properties['head'] = $this->_processingContent($properties['head'], 'publish');
                    $properties['bodyStyle'] = isset($properties['bodyStyle']) ? $this->_processingContent($properties['bodyStyle'], 'publish') : '';
                    $properties['bodyDataBg'] = isset($properties['bodyDataBg']) ? $this->_processingContent($properties['bodyDataBg'], 'publish') : '';
                    $properties['html'] = $this->_processingContent($properties['html'], 'editor');
                    $properties['publishHtml'] = $fullText = $this->_processingContent($properties['publishHtml'], 'publish');

                    $parameters = isset($this->_data['Parameters']) ? $this->_data['Parameters'] : null;
                    if ($parameters && (isset($parameters['header']) || isset($parameters['footer']))) {
                        $properties['pageView'] = 'landing';
                    } else {
                        $properties['pageView'] = 'landing_with_header_footer';
                    }

                    $properties['titleInBrowser']   = isset($articleData['titleInBrowser']) ? $articleData['titleInBrowser'] : '';
                    $seoOptions['title']            = $properties['titleInBrowser'];

                    $properties['keywords']         = isset($articleData['keywords']) ? $articleData['keywords'] : '';
                    $seoOptions['keywords']         = $properties['keywords'];

                    $properties['description']      = isset($articleData['description']) ? $articleData['description'] : '';
                    $seoOptions['description']      = $properties['description'];

                    $properties['canonical']      = isset($articleData['canonical']) ? $articleData['canonical'] : '';

                    $properties['metaTags']         = isset($articleData['metaTags']) ? $articleData['metaTags'] : '';
                    $properties['customHeadHtml']   = isset($articleData['customHeadHtml']) ? $articleData['customHeadHtml'] : '';
                    $properties['metaGeneratorContent']   = isset($articleData['metaGeneratorContent']) ? $articleData['metaGeneratorContent'] : '';

                    $properties['introImgStruct'] = $this->_processingContent($properties['introImgStruct'], 'publish');
                    $db = JFactory::getDBO();

                    // remove nice pages with article id, before adding a new nice page
                    $db->setQuery('delete from #__' . $this->_dbName . '_sections WHERE ' . $db->quoteName('page_id') . '=' . $db->quote($article->id));
                    $db->execute();

                    $query = $db->getQuery(true);
                    $query->insert('#__' . $this->_dbName . '_sections');
                    $query->columns(array($db->quoteName('props'), $db->quoteName('page_id')));
                    $query->values($db->quote(call_user_func('base' . '64_encode', serialize($properties))) . ', ' . $db->quote($article->id));
                    $db->setQuery($query);
                    $db->query();
                }
                $article->introtext = $this->_processingContent($article->introtext, 'publish');
                $article->fulltext = '<!--np_fulltext-->' . $fullText . '<!--/np_fulltext-->';
                $article->metakey = $seoOptions['keywords'];
                $article->metadesc = $seoOptions['description'];
                $attribs = $this->_stringToParams($article->attribs);
                $attribs['article_page_title'] = $seoOptions['title'];
                $article->attribs = $this->_paramsToString($attribs);

                $status = $content->save($article);
                if (is_string($status)) {
                    return $this->_error($status, 1);
                }
            }
        }
    }

    /**
     * Generate new title for page
     *
     * @param int    $catId Category Id
     * @param string $title Start title
     * @param string $alias Start alias
     * @param int    $key   Custom key for alias
     *
     * @return array
     */
    private function _generateNewTitle($catId, $title, $alias = '', $key = 0)
    {
        $title = $title ? strip_tags($title) : 'Post';
        $alias = $alias ? $alias : $title;
        if (JFactory::getConfig()->get('unicodeslugs') == 1) {
            $alias = JFilterOutput::stringURLUnicodeSlug($alias);
        } else {
            $alias = JFilterOutput::stringURLSafe($alias);
        }
        $table = JTable::getInstance('Content');
        while ($table->load(array('alias' => $alias, 'catid' => $catId))) {
            $alias = JString::increment($alias, 'dash');
        }
        while ($table->load(array('title' => $title, 'catid' => $catId))) {
            $title = JString::increment($title);
        }
        if (!$alias) {
            $date = new JDate();
            $alias = $date->format('Y-m-d-H-i-s') . '-' . $key;
        }
        return array($title, $alias);
    }

    /**
     * Process link hrefs witk '[page_' placeholder
     *
     * @param array $matches Href matches
     *
     * @return string
     */
    private function _parseHref($matches)
    {
        $pageId = $matches[1];
        if (isset($this->_data['Pages'][$pageId])) {
            $page = $this->_data['Pages'][$pageId];

            if (empty($page['joomla_id'])) {
                return '#';
            }

            $content = Nicepage_Data_Mappers::get('content');
            $article = $content->fetch($page['joomla_id']);

            $menuId = isset($page['joomla_menu_id']) ? '&amp;Itemid=' . $page['joomla_menu_id'] : '';
            if (!is_null($article)) {
                return 'index.php?option=com_content&amp;view=article' .
                    '&amp;id=' . $article->id . '&amp;catid=' . $article->catid . $menuId;
            }
        }

        return $matches[0];
    }

    /**
     * Method to proccess page content
     *
     * @param string $content Page sample content
     * @param string $state   Type path
     *
     * @return mixed
     */
    private function _processingContent($content, $state = 'full')
    {
        if ($content == '') {
            return $content;
        }

        $old = $this->_rootUrl;

        switch ($state) {
        case 'full':
            $this->_rootUrl .= '/';
            break;
        case 'publish':
            $this->_rootUrl = '[[site_path_live]]';
            break;
        case 'editor':
            $this->_rootUrl = '[[site_path_editor]]/';
            break;
        case 'relative':
            $this->_rootUrl = '';
            break;
        }
        $content = $this->_replacePlaceholdersForImages($content);
        $this->_rootUrl =  $old;

        $content = preg_replace_callback('/\[page_(\d+)\]/', array( &$this, '_parseHref'), $content);

        $content = preg_replace('/\[blog_(\d+)\]/', 'index.php?option=com_content&view=category&layout=blog&id=' . $this->_blogCategoryId, $content);
        return $content;
    }

    /**
     * Replace image placeholders in page content
     *
     * @param string $content Page sample content
     *
     * @return mixed
     */
    private function _replacePlaceholdersForImages($content)
    {
        //change default image
        $content = str_replace('[image_default]', $this->_rootUrl . 'components/com_nicepage/assets/images/nicepage-images/default-image.jpg', $content);
        $content = preg_replace_callback('/\[image_(\d+)\]/', array(&$this, '_replacerImages'), $content);
        return $content;
    }

    /**
     * Callback function for replacement image placeholders
     *
     * @param array $match
     *
     * @return string
     */
    private function _replacerImages($match)
    {
        $full = $match[0];
        $n = $match[1];
        if (isset($this->_data['Images'][$n])) {
            $imageName = $this->_data['Images'][$n]['fileName'];
            array_push($this->_foundImages, $imageName);
            return $this->_rootUrl . 'images/nicepage-images/' . $imageName;
        }
        return $full;
    }

    /**
     * To configure editor
     *
     * @return null|void
     * @throws Exception
     */
    private function _configureEditor()
    {
        $extensions = Nicepage_Data_Mappers::get('extension');
        $tinyMce = $extensions->findOne(array('element' => 'tinymce'));
        if (is_string($tinyMce)) {
            return $this->_error($tinyMce, 1);
        }
        if (!is_null($tinyMce)) {
            $params = $this->_stringToParams($tinyMce->params);
            $elements = isset($params['extended_elements']) && strlen($params['extended_elements']) ? explode(',', $params['extended_elements']) : array();
            $invalidElements = isset($params['invalid_elements']) && strlen($params['invalid_elements']) ? explode(',', $params['invalid_elements']) : array();
            if (in_array('script', $invalidElements)) {
                array_splice($invalidElements, array_search('script', $invalidElements), 1);
            }
            if (!in_array('style', $elements)) {
                $elements[] = 'style';
            }
            if (!in_array('script', $elements)) {
                $elements[] = 'script';
            }
            if (!in_array('div[*]', $elements)) {
                $elements[] = 'div[*]';
            }
            $params['extended_elements'] = implode(',', $elements);
            $params['invalid_elements'] = implode(',', $invalidElements);
            $tinyMce->params = $this->_paramsToString($params);
            $status = $extensions->save($tinyMce);
            if (is_string($status)) {
                return $this->_error($status, 1);
            }
        }
        return null;
    }

    /**
     * Method to copy sample images to cms
     *
     * @param bool $onlyFound Flag
     */
    private function _copyImages($onlyFound = false)
    {
        if (!$this->_images) {
            return;
        }
        $imgDir = dirname(JPATH_BASE) . DIRECTORY_SEPARATOR . 'images';
        $contentDir = $imgDir . DIRECTORY_SEPARATOR . 'nicepage-images';
        if (!file_exists($contentDir)) {
            mkdir($contentDir);
        }
        if ($handle = opendir($this->_images)) {
            while (false !== ($file = readdir($handle))) {
                if ('.' == $file || '..' == $file || is_dir($file)) {
                    continue;
                }
                if ($onlyFound && array_search($file, $this->_foundImages) === false) {
                    continue;
                }
                copy($this->_images . DIRECTORY_SEPARATOR . $file, $contentDir . DIRECTORY_SEPARATOR . $file);
            }
            closedir($handle);
        }
    }

    /**
     * Method to copy sample fonts to cms
     */
    private function _copyFonts() {
        if (!isset($this->_data['Pages'])) {
            return;
        }
        if (!$this->_fonts) {
            return;
        }
        $imgDir = dirname(JPATH_BASE) . '/' . 'images';
        $contentFontsDir = $imgDir . '/' . 'nicepage-fonts';
        if (!file_exists($contentFontsDir)) {
            mkdir($contentFontsDir);
        }

        //copy font files
        $fontFilesDir = $this->_fonts . '/fonts';
        if (file_exists($fontFilesDir)) {
            $contentFontFilesDir = $imgDir . '/' . 'nicepage-fonts' . '/fonts';
            if (!file_exists($contentFontFilesDir)) {
                mkdir($contentFontFilesDir);
            }
            if ($handle = opendir($fontFilesDir)) {
                while (false !== ($file = readdir($handle))) {
                    $fileSource = $fontFilesDir . '/' . $file;
                    if ('.' == $file || '..' == $file || is_dir($fileSource)) {
                        continue;
                    }
                    copy($fileSource, $contentFontFilesDir . '/' . $file);
                }
                closedir($handle);
            }
        }

        //copy pages css
        if ($handle = opendir($this->_fonts)) {
            while (false !== ($file = readdir($handle))) {
                $fileSource = $this->_fonts . '/' . $file;
                if ('.' == $file || '..' == $file || is_dir($fileSource)) {
                    continue;
                }
                $fileInfo = pathinfo($file);
                $fileNameParts = explode('_', $fileInfo['filename']);
                if (count($fileNameParts) > 1 && isset($this->_data['Pages'][$fileNameParts[1]])) {
                    $joomlaPageId = $this->_data['Pages'][$fileNameParts[1]]['joomla_id'];
                    copy($fileSource, $contentFontsDir . '/' . str_replace($fileNameParts[1], $joomlaPageId, $file));
                }
            }
            closedir($handle);
        }
    }

    /**
     * Method to get Nicepage Component options
     *
     * @return mixed
     */
    private function _getExtOptions()
    {
        if ($this->_template) {
            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query->select('params')
                ->from('#__template_styles')
                ->where('id=' . $query->escape($this->_style));
            $db->setQuery($query);
            return $this->_stringToParams($db->loadResult());
        } else {
            return NicepageHelpersNicepage::getConfig();
        }
    }

    /**
     * Method to save Nicepage Component options
     *
     * @param array $parameters
     */
    private function _setExtOptions($parameters)
    {
        if ($this->_template) {
            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query->update('#__template_styles')
                ->set($db->quoteName('params') . '=' . $db->quote($this->_paramsToString($parameters)))
                ->where('id=' . $query->escape($this->_style));
            $db->setQuery($query);
            $db->query();
        } else {
            NicepageHelpersNicepage::saveConfig(array('dataIds' => $parameters['dataIds']));
        }
    }

    /**
     * Convert parameters array to string
     *
     * @param array $params
     *
     * @return mixed
     */
    private function _paramsToString($params)
    {
        $registry = new JRegistry();
        $registry->loadArray($params);
        return $registry->toString();
    }

    /**
     * Convert parameters string to array
     *
     * @param string $string
     *
     * @return mixed
     */
    private function _stringToParams($string)
    {
        $registry = new JRegistry();
        $registry->loadString($string);
        return $registry->toArray();
    }

    /**
     * Parsing of sample data file
     *
     * @param string $file
     *
     * @return null|string
     */
    private function _parse($file)
    {
        $error = null;
        if (!($fp = fopen($file, 'r'))) {
            $error = 'Could not open json input';
        }
        $contents = '';
        if (is_null($error)) {
            while (!feof($fp)) {
                $contents .= fread($fp, 4096);
            }
            fclose($fp);
        }

        $this->_data = json_decode($contents, true);

        return $error;
    }

    /**
     * Parsing of sample data file
     *
     * @param string $file
     */
    public function parse($file) {
        $this->_parse($file);
    }

    /**
     * Set root url
     *
     * @param string $url
     */
    public function setRootUrl($url)
    {
        $this->_rootUrl = $url;
    }

    /**
     * Set images path
     *
     * @param string $path Path
     */
    public function setImagesPath($path)
    {
        $this->_images = $path;
    }

    /**
     * Copy only found images
     */
    public function copyOnlyFoundImages()
    {
        $this->_copyImages(true);
    }

    /**
     * Processing content
     *
     * @param string $content
     * @param string $state
     *
     * @return mixed
     */
    public function processingContent($content, $state = 'full')
    {
        return $this->_processingContent($content, $state);
    }

    /**
     * Load parameters
     */
    public function loadParameters()
    {
        $this->_loadParameters();
    }
}
