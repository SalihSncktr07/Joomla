<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
defined('_JEXEC') or die;

use NP\Processor\ContentProcessorFacade;
use NP\Utility\Utility;

/**
 * Class NicepageHelpersNicepage
 */
class NicepageHelpersNicepage
{
    /**
     * Extension type name
     *
     * @var string
     */
    public static $extension = 'com_nicepage';

    /**
     * Add submenu on page
     *
     * @param string $vName Page name
     */
    public static function addSubmenu($vName)
    {
        JHtmlSidebar::addEntry(
            JText::_('COM_NICEPAGE_SUBMENU_EDITOR'),
            'index.php?option=com_nicepage&task=nicepage.start',
            false
        );

        JHtmlSidebar::addEntry(
            JText::_('COM_NICEPAGE_SUBMENU_IMPORT'),
            'index.php?option=com_nicepage&view=import',
            $vName == 'import'
        );

        JHtmlSidebar::addEntry(
            JText::_('COM_NICEPAGE_SUBMENU_CONFIGURATION'),
            'index.php?option=com_nicepage&view=config',
            $vName == 'config'
        );

        JHtmlSidebar::addEntry(
            JText::_('COM_NICEPAGE_SUBMENU_THEME'),
            'index.php?option=com_nicepage&view=theme',
            false
        );
    }

    /**
     * Get actions for nicepage component
     *
     * @return JObject
     */
    public static function getActions()
    {
        $user = JFactory::getUser();
        $result = new JObject;

        $assetName = 'com_nicepage';
        $level = 'component';

        $actions = JAccess::getActions('com_nicepage', $level);

        foreach ($actions as $action) {
            $result->set($action->name, $user->authorise($action->name, $assetName));
        }

        return $result;
    }

    /**
     * Get domain get parameter
     *
     * @return mixed|string
     */
    public static function getDomain()
    {
        $default = defined('NICEPAGE_DOMAIN') ? NICEPAGE_DOMAIN : '';
        $app = JFactory::getApplication();
        $domain = urldecode($app->input->getVar('domain', $default));
        if ($domain) {
            $domain = preg_replace('#^https?:#', '', $domain); // remove protocol
            $domain = preg_replace('#\/$#', '', $domain); // remove last slash
        }
        return $domain;
    }

    /**
     * Get site url
     *
     * @return string
     */
    public static function getSiteUrl()
    {
        return dirname(dirname((JURI::current())));
    }

    /**
     * Get files for nicepage starting
     *
     * @return array
     */
    public static function getStartFiles()
    {
        $domain = self::getDomain();

        $extension = JTable::getInstance('extension');
        $id = $extension->find(array('element' => 'com_nicepage'));
        $extension->load($id);
        $componentInfo = json_decode($extension->manifest_cache, true);
        $hash = $componentInfo['version'];

        return array(
            'sw' => self::getSiteUrl() . '/administrator/index.php?option=com_nicepage&task=actions.getSw',
            'editor' => self::getSiteUrl() . '/administrator/components/com_nicepage/assets/app/editor.js?ver=' . $hash,
            'loader' => $domain ? $domain . '/Editor/loader.js' : self::getLoader($hash),
            'auth' => self::getSiteUrl() . '/administrator/components/com_nicepage/helpers/auth.php?uid=' . JFactory::getUser()->id . '&ver=' . $hash
        );
    }

    /**
     * Get custom loader file
     *
     * @param string $hash Hash for get parameter
     *
     * @return string
     */
    public static function getLoader($hash) {
        return self::getSiteUrl() . '/administrator/components/com_nicepage/assets/app/loader.js?ver=' . $hash;
    }

    /**
     * Get actions list for nicepage app
     *
     * @return array
     */
    public static function getEditorSettings()
    {
        $index = self::getSiteUrl() . '/administrator/index.php?option=com_nicepage&task=actions.';
        return array(
            'actions' => array(
                'uploadFile' => $index . 'uploadFile',
                'uploadImage' => $index . 'uploadImage',
                'savePage' => $index . 'savePage',
                'saveLocalStorageKey' => $index . 'saveLocalStorageKey',
                'clearChunks' => $index . 'clearChunks',
                'getSite' => $index . 'getSite',
                'getSitePosts' => $index . 'getSitePosts',
                'saveSiteSettings' => $index . 'saveSiteSettings',
                'savePreferences' => $index . 'savePreferences',
                'saveMenuItems' => $index . 'saveMenuItems',
                'removeFont' => $index . 'removeFont',
                'getPosts' => self::getSiteUrl() . '/index.php?option=com_nicepage&task=posts',
                'getProducts' => self::getSiteUrl() . '/index.php?option=com_nicepage&task=products',
            ),
            'uploadFileOptions' => array(
                'formFileName' => 'async-upload'
            ),
            'dashboardUrl' => self::getSiteUrl() . '/administrator/',
            'editPostUrl' => self::getSiteUrl() . '/administrator/index.php?option=com_content&view=article&layout=edit&id={id}'
        );
    }

    /**
     * Get max request size
     *
     * @return mixed
     */
    public static function getMaxRequestSize()
    {
        return Utility::getMaxRequestSize();
    }

    /**
     * Get cms custom settings
     *
     * @return array
     */
    public static function getCmsSettings()
    {
        return array(
            'defaultImageUrl' => self::getSiteUrl() . '/components/com_nicepage/assets/images/nicepage-images/default-image.jpg',
            'defaultLogoUrl' => self::getSiteUrl() . '/components/com_nicepage/assets/images/nicepage-images/default-logo.png',
            'isFirstStart' => false,
            'maxRequestSize' => Utility::getMaxRequestSize(),
            'isWhiteLabelPlugin' => pathinfo(dirname(dirname(__FILE__)), PATHINFO_BASENAME) != ('com_' . 'n' . 'i' . 'c' . 'e' . 'p' . 'a' . 'g' . 'e')
        );
    }

    /**
     * Build site style css
     *
     * @param array  $params         plugin config parameters
     * @param string $pageCssUsedIds used css ids
     * @param string $html           public html of page
     * @param string $pageId         id of page
     *
     * @return string
     */
    public static function buildSiteStyleCss($params, $pageCssUsedIds, $html, $pageId)
    {
        $result = isset($params['siteStyleCss']) ? $params['siteStyleCss'] : '';
        $partsJson = isset($params['siteStyleCssParts']) ? $params['siteStyleCssParts'] : '';
        if ($partsJson) {
            $cssParts = json_decode($partsJson, true);
            if (!$pageCssUsedIds) {
                $usedIds = self::processUsedColors($cssParts, $html);
                self::updateUsedColor($usedIds, $pageId);
            } else {
                $usedIds = json_decode($pageCssUsedIds, true);
            }
            $headerFooterCssUsedIds = isset($params['headerFooterCssUsedIds']) ? json_decode($params['headerFooterCssUsedIds'], true) : array();
            $cookiesCssUsedIds = isset($params['cookiesCssUsedIds']) ? json_decode($params['cookiesCssUsedIds'], true) : array();
            $result   = '';
            foreach ($cssParts as $part) {
                if ($part['type'] !== 'color' || !empty($usedIds[$part['id']]) || !empty($headerFooterCssUsedIds[$part['id']]) || !empty($cookiesCssUsedIds[$part['id']])) {
                    $result .= $part['css'];
                }
            }
        }
        return $result;
    }

    /**
     * @param string $styles                common styles
     * @param string $publishHtml           publish html of page
     * @param string $publishHeaderFooter   publish html of page
     * @param string $publishCookiesSection publish cookies section
     *
     * @return array
     */
    public static function processAllColors($styles, $publishHtml, $publishHeaderFooter = '', $publishCookiesSection = '') {
        $split = preg_split('#(\/\*begin-color [^*]+\*\/[\s\S]*?\/\*end-color [^*]+\*\/)#', $styles, -1, PREG_SPLIT_DELIM_CAPTURE);
        $parts = array();
        foreach ($split as $part) {
            $part = trim($part);
            if (!$part) {
                continue;
            }

            if (preg_match('#\/\*begin-color ([^*]+)\*\/#', $part, $m)) {
                $id = 'color_' . $m[1];
                $parts[] = array(
                    'type' => 'color',
                    'id' => $id,
                    'css' => $part,
                );
                if (strpos($publishHtml, $m[1]) !== false) {
                    $usedIds[$id] = true;
                }
            } else {
                $parts[] = array(
                    'type' => '',
                    'css' => $part,
                );
            }
        }
        $headerFooterUsedIds = $publishHeaderFooter ? self::processUsedColors($parts, $publishHeaderFooter) : array();
        $cookiesCssUsedIds = $publishCookiesSection ? self::processUsedColors($parts, $publishCookiesSection) : array();
        $usedIds = self::processUsedColors($parts, $publishHtml);
        return array(json_encode($parts), json_encode($usedIds), json_encode($headerFooterUsedIds), json_encode($cookiesCssUsedIds));
    }

    /**
     * @param array  $parts       All styles array
     * @param string $publishHtml Html of page
     *
     * @return array
     */
    public static function processUsedColors($parts, $publishHtml)
    {
        $usedIds = array();
        foreach ($parts as &$part) {
            if (isset($part['id']) && strpos($publishHtml, preg_replace('#^color_#', '', $part['id'])) !== false) {
                $usedIds[$part['id']] = true;
            }
        }
        return $usedIds;
    }

    /**
     * Get nicepage properties
     *
     * @param boolean $isPreview Preview flag
     *
     * @return mixed
     */
    public static function getConfig($isPreview = false)
    {
        $ret = NicepageHelpersNicepage::getParamsTable()->getParameters();
        if (isset($ret['siteStyleCss']) && substr($ret['siteStyleCss'], 0, 6) === '<style') {
            // backward compatibility
            $css = preg_replace('#</?style[^>]*>#', '', $ret['siteStyleCss']);
            ob_start();
            include_once dirname(JPATH_PLUGINS) . '/administrator/components/com_nicepage/assets/css/nicepage-dynamic.css';
            $dynamicPart = ob_get_clean();
            $ret['siteStyleCss'] = $css . $dynamicPart;
        }
        $ret['header'] = isset($ret['header']) ? $ret['header'] : '';
        $ret['footer'] = isset($ret['footer']) ? $ret['footer'] : '';
        if ($isPreview) {
            $ret['header'] = isset($ret['header:preview']) && $ret['header:preview'] ? $ret['header:preview'] : $ret['header'];
            $ret['footer'] = isset($ret['footer:preview']) && $ret['footer:preview'] ? $ret['footer:preview'] : $ret['footer'] ;
        }
        return $ret;
    }

    /**
     * Save nicepage settings
     *
     * @param array $data Data parameters
     *
     * @return mixed|string
     */
    public static function saveConfig($data)
    {
        $paramsTable = NicepageHelpersNicepage::getParamsTable();

        $params = $paramsTable->getParameters();
        $excludeParameters = array('option', 'action', 'controller', 'task', 'view');
        foreach ($data as $key => $value) {
            if (in_array($key, $excludeParameters)) {
                continue;
            }
            $params[$key] = $value;
        }
        $paramsTable->saveParameters($params);
    }

    /**
     * Get new sections object
     *
     * @return mixed
     */
    public static function getSectionsTable()
    {
        JLoader::register('PagesTableSections', dirname(JPATH_PLUGINS) . '/administrator/components/com_nicepage/tables/sections.php');
        return JTable::getInstance('Sections', 'PagesTable');
    }

    /**
     * Get new params object
     *
     * @return mixed
     */
    public static function getParamsTable()
    {
        JLoader::register('PagesTableParams', dirname(JPATH_PLUGINS) . '/administrator/components/com_nicepage/tables/params.php');
        return JTable::getInstance('Params', 'PagesTable');
    }

    /**
     * Clear preview page
     *
     * @param object $page sections page object
     *
     * @return null
     */
    public static function clearPreview($page) {
        $page->save(array('preview_props' => ''));
    }

    /**
     * @param array $parts  Used color list
     * @param int   $pageId Id of page
     */
    public static function updateUsedColor($parts, $pageId) {
        $page = NicepageHelpersNicepage::getSectionsTable();
        if ($page->load(array('page_id' => $pageId))) {
            $props = $page->props;
            $props['pageCssUsedIds'] = json_encode($parts);
            $page->save(array('props' => $props));
        }
    }

    /**
     * @param string $content  Page content
     * @param bool   $isPublic Flag for public content
     * @param string $pageId   Type Id
     *
     * @return mixed
     */
    public static function processSectionsHtml($content, $isPublic = true, $pageId = '') {
        $contentProcessorFacade = new ContentProcessorFacade($isPublic, $pageId);
        return $contentProcessorFacade->process($content);
    }
}