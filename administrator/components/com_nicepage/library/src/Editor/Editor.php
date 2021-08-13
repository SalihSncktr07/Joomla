<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

namespace NP\Editor;

defined('_JEXEC') or die;

use \JLoader, \JURI, \JFactory, \JTable, \JHtml, \JText, \JFile;
use \JComponentHelper, \JFolder, \JPath, \JRegistry;
use \NicepageHelpersNicepage;
use NP\Utility\Utility;

JLoader::register('NicepageModelActions', JPATH_ADMINISTRATOR . '/components/com_nicepage/models/actions.php');

/**
 * Class Editor
 */
class Editor
{
    private $_scriptsPhpVars = array();

    private $_article = null;
    private $_sections = null;
    private $_isConvertRequired = null;

    private $_dataBridgeScripts = '';

    private $_editorPageTypes = array(
        'default' => 'theme-template',
        'landing' => 'np-template-header-footer-from-plugin',
        'landing_with_header_footer' => 'np-template-header-footer-from-theme'
    );

    /**
     * NicepageEditor constructor.
     */
    public function __construct()
    {
        $aid = JFactory::getApplication()->input->get('id', '');

        $page = NicepageHelpersNicepage::getSectionsTable();
        if ($page->load(array('page_id' => $aid))) {
            NicepageHelpersNicepage::clearPreview($page);
            $this->_sections = $page;
        }

        $this->_componentConfig = NicepageHelpersNicepage::getConfig();
        $this->_article = JTable::getInstance("content");
        $this->_article->load($aid);

        $this->_isConvertRequired = !$this->_sections && ($this->_article->introtext . $this->_article->fulltext);
    }
    /**
     * Add common scripts
     */
    public function addCommonScript()
    {
        $domain = Utility::getDomain();
        $input = JFactory::getApplication()->input;
        $aid = $input->get('id', '');
        $element = $input->get('element', '');
        $view = $input->input->get('view', '');

        // start nicepage from edit article page
        if ($this->_sections) {
            $parts = '/#/builder/1/page/' . $aid;
        } else if ($view === 'theme') {
            $parts = '/#/builder/1/theme' . ($element ? '/' . $element : '');
        } else {
            $parts = '/#/landing';
        }
        $currentUrl = Utility::getAdminUrl() . '/index.php?option=com_nicepage&view=display&ver=' . urlencode('1607432772669')  . ($domain ? '&domain=' . $domain : '') . $parts;

        $this->_scriptsPhpVars = array_merge(
            $this->_scriptsPhpVars,
            array(
                'editorUrl' => $currentUrl,
                'adminUrl'  => Utility::getAdminUrl(),
            )
        );
    }



    /**
     * Get local storage key
     *
     * @return |null
     */
    public function getLocalStorageKey() {
        if (isset($this->_componentConfig['localStorageKey']) && $this->_componentConfig['localStorageKey']) {
            return $this->_componentConfig['localStorageKey'];
        }
        return null;
    }

    /**
     * Add joomla link dialog script
     */
    public function addLinkDialogScript()
    {
        $linkDialog = new LinkDialog();
        $linkDialog->addLinkDialog();
    }

    /**
     * Add script for making data for editor
     */
    public function addDataBridgeScript()
    {
        $app = JFactory::getApplication();
        $input = $app->input;
        $aid = $this->_article->id;
        $start = $input->get('start', '0');
        $autostart = $input->get('autostart', '0');
        $domain = Utility::getDomain();
        $prettyCode =  array_key_exists('JSON_PRETTY_PRINT', get_defined_constants()) ? JSON_PRETTY_PRINT : 0;

        $editorSettings = NicepageHelpersNicepage::getEditorSettings();
        if ($aid) {
            $editorSettings['pageId'] = $this->_isConvertRequired ? '' : $aid;
            $editorSettings['startPageId'] = $aid;
        }

        $cmsSettings = NicepageHelpersNicepage::getCmsSettings();
        $cmsSettings['isFirstStart'] = $start == '1' ? true : false;
        $cmsSettings['disableAutosave'] = $this->getDisableAutoSave();

        $editorSettingsJson = json_encode($editorSettings, $prettyCode);
        $cmsSettingsJson = json_encode($cmsSettings, $prettyCode);

        $modelActions = new \NicepageModelActions();
        $site = $modelActions->getSite();
        $isNewPage = $this->_article->state == '2' && ($start == '1' || $autostart == '1');
        if ($isNewPage) {
            $site['items'][] = array(
                'siteId' => '1',
                'title' => $this->_article->title,
                'id' => (int) $aid,
                'order' => 0,
                'status' => 2,
                'editorUrl' => Utility::getAdminUrl() . '/index.php?option=com_nicepage&task=nicepage.autostart&postid=' . $aid . ($domain ? '&domain=' . $domain : ''),
                'htmlUrl' => Utility::getAdminUrl() . '/index.php?option=com_nicepage&task=actions.getPageHtml&pageId=' . $aid
            );
        }

        $keys = array('header', 'footer');
        foreach ($keys as $key) {
            $keyJson = '';
            if (isset($this->_componentConfig[$key . ':autosave']) && $this->_componentConfig[$key . ':autosave']) {
                $keyJson = $this->_componentConfig[$key . ':autosave'];
            } else if (isset($this->_componentConfig[$key]) && $this->_componentConfig[$key]) {
                $keyJson = $this->_componentConfig[$key];
            }
            if ($keyJson) {
                $item = json_decode(str_replace('[[site_path_editor]]', dirname(Utility::getAdminUrl()), $keyJson), true);
                $site[$key] = $item['html'];
            }
        }

        $info = array(
            'productsExists' => $this->vmEnabled(),
            'newPageUrl' => Utility::getAdminUrl() . '/index.php?option=com_nicepage&task=nicepage.start' . ($domain ? '&domain=' . $domain : ''),
            'forceModified' => $this->forceModified(),
            'generalSettingsUrl' => Utility::getAdminUrl() . '/index.php?option=com_config#page-server',
            'typographyPageHtmlUrl' => $this->getFrontendUrl(),
            'siteIsSecureAndLocalhost' => Utility::siteIsSecureAndLocalhost(),
            'newPageTitle' => $isNewPage ? $this->_article->title : '',
            'fontsInfo' => $this->getFontsInfo(),
            'videoFiles' => Utility::getVideoFiles(),
            'localStorageKey' => $this->getLocalStorageKey(),
        );

        $themeEditorSettings = $this->getEditorSettingsFromDefaultTheme();
        if ($themeEditorSettings) {
            $info['themeTypography'] = $themeEditorSettings['typography'];
            $info['themeFontScheme'] = $themeEditorSettings['fontScheme'];
            $info['themeColorScheme'] = $themeEditorSettings['colorScheme'];
        }

        $pageHtml = $this->getSectionHtml();
        $pageHtml = str_replace('[[site_path_editor]]', dirname(Utility::getAdminUrl()), $pageHtml);
        $pageHtml = $this->_restoreSeoOptions($pageHtml);
        $pageHtml = $this->_restorePageType($pageHtml);
        $pageHtml = call_user_func('base' . '64_encode', $pageHtml);

        $data = json_encode(
            array (
                'site' => $site,
                'pageHtml' => $pageHtml,
                'startTerm' => $this->_isConvertRequired ? 'site:joomla:' . $aid : '',
                'defaultPageType' => $this->getDefaultPageType(true),
                'info' => $info,
                'nicePageCss' => $this->getDynamicNicepageCss(),
                'downloadedFonts' => $this->getDownloadedFonts(),
                'customFonts' => $this->getCustomFonts(),
            ),
            $prettyCode
        );

        $this->_dataBridgeScripts .= <<<EOF
var dataBridgeData = $data;
window.dataBridge = {
    getSite: function () {
        return dataBridgeData.site;
    },
    setSite: function (site) {
        dataBridgeData.site = site;
    },
    getPageHtml: function () {
        return decodeURIComponent(Array.prototype.map.call(atob(dataBridgeData.pageHtml), function(c) {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
        }).join(''))
    },
    getStartTerm: function () {
        return dataBridgeData.startTerm;
    },
    getDefaultPageType: function () {
        return dataBridgeData.defaultPageType;
    },
    getInfo: function getInfo() {
        return dataBridgeData.info;
    },
    getNPCss: function getNPCss() {
        return dataBridgeData.nicePageCss;
    },
    getDownloadedFonts: function getDownloadedFonts() {
        return dataBridgeData.downloadedFonts;
    },
    setDownloadedFonts: function setDownloadedFonts(downloadedFonts) {
        dataBridgeData.downloadedFonts = downloadedFonts;
    },
    getCustomFonts: function getCustomFonts() {
        return dataBridgeData.customFonts;
    },
    setCustomFonts: function setCustomFonts(customFonts) {
        dataBridgeData.customFonts = customFonts;
    },
    settings: $editorSettingsJson,
    cmsSettings: $cmsSettingsJson
};
EOF;
    }

    /**
     * Get raw html
     *
     * @return mixed|string
     */
    public function getSectionHtml()
    {
        $html = '';
        if ($this->_sections) {
            $props = $this->_sections->autosave_props ? $this->_sections->autosave_props : $this->_sections->props;
            $html = isset($props['html']) ? $props['html'] : '';
            $html = NicepageHelpersNicepage::processSectionsHtml($html, false);
        }
        return $html;
    }

    /**
     * Get fonts info
     *
     * @return array
     */
    public function getFontsInfo() {
        jimport('joomla.filesystem.path');
        jimport('joomla.filesystem.file');
        jimport('joomla.filesystem.folder');

        $info = array(
            'path' => '',
            'canSave' => true,
        );
        $assets = dirname(JPATH_ADMINISTRATOR) . '/components/com_nicepage/assets/css';
        if (JFolder::exists($assets)) {
            $error = $this->checkWritable($assets);
            if (count($error) > 0) {
                return array_merge($info, $error);
            }
            $fonts = $assets . '/fonts';
            if (!JFolder::exists($fonts)) {
                if (!JFolder::create($fonts)) {
                    return array_merge($info, array('path' => $fonts, 'canSave' => false));
                }
            } else {
                $error = $this->checkWritable($fonts);
                if (count($error) > 0) {
                    return array_merge($info, $error);
                }
            }
        }
        return $info;
    }

    /**
     * Check path writable
     *
     * @param string $path Path
     *
     * @return string
     */
    public function checkWritable($path) {
        $user = get_current_user();
        chown($path, $user);
        JPath::setPermissions($path, '0777');
        $result = array();
        if (!is_writable($path)) {
            $result = array(
                'path' => $path,
                'canSave' => false,
            );
        }
        return $result;
    }

    /**
     * Add main script
     */
    public function addMainScript()
    {
        $input = JFactory::getApplication()->input;

        $cookie = $input->cookie;
        $themeTypographyCacheForceRefresh = '0';
        $cachedDefaultTheme = $cookie ? $cookie->get('DEFAULT_THEME', '') : '';
        if (!$cachedDefaultTheme || $cachedDefaultTheme !== Utility::getActiveTemplate()) {
            setcookie('DEFAULT_THEME', Utility::getActiveTemplate(), time() + 31536000); // will expire after year
            $themeTypographyCacheForceRefresh = '1';
        }

        $this->_scriptsPhpVars = array_merge(
            $this->_scriptsPhpVars,
            array(
                'jEditor' => JFactory::getConfig()->get('editor'),
                'forceRefresh' => $themeTypographyCacheForceRefresh,
                'infoDataUrl'  => dirname(Utility::getAdminUrl()) . '/index.php?option=com_nicepage&task=getInfoData',
                'pageId'  => $this->_article->id ? $this->_article->id : -1,
                'startParam'  => $input->get('start', '0'),
                'autoStartParam'  => $input->get('autostart', '0'),
                'viewParam'  => $input->get('view', ''),
            )
        );

        $aid = $this->_article->id;
        if ($aid) {
            $pageView = $this->getDefaultPageType();
            if ($this->_sections) {
                $props = $this->_getPageProps($this->_sections, true);
                $pageView = isset($props['pageView']) ? $props['pageView'] : $pageView;
            }

            switch($pageView) {
            case 'landing':
                $templateOptions = JText::sprintf('PLG_EDITORS-XTD_TEMPLATE_OPTIONS', '', 'selected', '');
                break;
            case 'landing_with_header_footer':
                $templateOptions = JText::sprintf('PLG_EDITORS-XTD_TEMPLATE_OPTIONS', '', '', 'selected');
                break;
            default:
                $templateOptions = JText::sprintf('PLG_EDITORS-XTD_TEMPLATE_OPTIONS', 'selected', '', '');
            }

            $this->_scriptsPhpVars = array_merge(
                $this->_scriptsPhpVars,
                array(
                    'npButtonText'      => $this->_isConvertRequired ? JText::_('PLG_EDITORS-XTD_TURN_TO_NICEPAGE_BUTTON_TEXT') : JText::_('PLG_EDITORS-XTD_EDIT_WITH_NICEPAGE_BUTTON_TEXT'),
                    'buttonAreaClass'   => $this->_isConvertRequired ? '' : 'nicepage-select-template-area',
                    'duplicatePageUrl'  => Utility::getAdminUrl() . '/index.php?option=com_nicepage&task=actions.duplicatePage',
                    'templateOptions'   => $templateOptions,
                    'savePageTypeUrl'   => Utility::getAdminUrl() . '/index.php?option=com_nicepage&task=actions.savePageType',
                    'autoSaveMsg'       => $this->_autoSaveChangesExists($this->_sections) ? JText::sprintf('PLG_EDITORS-XTD_AUTOSAVE_CHANGES') : '',
                    'frontUrl'          => dirname(Utility::getAdminUrl()) . '/index.php?option=com_nicepage',
                    'userId'            => JFactory::getUser()->id,
                    'previewPageUrl'    => dirname(Utility::getAdminUrl()) . '/index.php?option=com_content&view=article&id=' . $aid,
                )
            );
        }
    }

    /**
     * Include all scripts to page document
     */
    public function includeScripts()
    {
        JHtml::_('behavior.modal'); // for SqueezeBox

        $doc = JFactory::getDocument();
        $doc->addCustomTag(
            '<script src="' . Utility::getAdminUrl() . '/components/com_nicepage/assets/js/typography-parser.js"></script>' .
            '<script> window.cmsVars = ' . json_encode($this->_scriptsPhpVars) . '</script>' .
            '<script src="' . Utility::getAdminUrl() . '/components/com_nicepage/assets/js/cms.js"></script>'
        );
        $doc->addCustomTag('<!--np_databridge_script--><script>' . $this->_dataBridgeScripts . '</script><!--/np_databridge_script-->');
    }

    /**
     * Get default page type
     *
     * @param bool $forEditor
     *
     * @return mixed|string
     */
    public function getDefaultPageType($forEditor = false) {
        $type = isset($this->_componentConfig['pageType']) ? $this->_componentConfig['pageType'] : 'landing';
        if ($forEditor) {
            $type = $this->_editorPageTypes[$type];
        }
        return $type;
    }

    /**
     * Get downloaded fonts
     *
     * @return false|string
     */
    public function getDownloadedFonts() {
        $downloadedFontsFile = dirname(JPATH_ADMINISTRATOR) . '/components/com_nicepage/assets/css/fonts/downloadedFonts.json';
        return file_exists($downloadedFontsFile) ? file_get_contents($downloadedFontsFile) : '';
    }

    /**
     * Get custom fonts
     *
     * @return array
     */
    public function getCustomFonts() {
        $customFontsDir = dirname(JPATH_BASE) . '/' . 'images/nicepage-fonts/fonts';
        $fonts = array();
        if (file_exists($customFontsDir)) {
            if ($handle = opendir($customFontsDir)) {
                while (false !== ($file = readdir($handle))) {
                    $fileSource = $customFontsDir . '/' . $file;
                    if ('.' == $file || '..' == $file || is_dir($fileSource)) {
                        continue;
                    }
                    $fileInfo = pathinfo($file);
                    $font = array(
                        'fileName' => $file,
                        'id' => 'user-file-' . $file,
                        'name' => $fileInfo['filename'],
                        'publicUrl' => JURI::root() . 'images/nicepage-fonts/fonts/' . $file
                    );
                    array_push($fonts, $font);
                }
                closedir($handle);
            }
        }
        return $fonts;
    }

    /**
     * Get disable auto save value
     *
     * @return string
     */
    public function getDisableAutoSave() {
        $disableAutosave = isset($this->_componentConfig['siteStyleCssParts']) ? true : false; // autosave disable for new user
        if (isset($this->_componentConfig['disableAutosave'])) {
            $disableAutosave = $this->_componentConfig['disableAutosave'] == '1' ? true : false;
        }
        return $disableAutosave;
    }

    /**
     * Restore seo props for page from joomla original props
     *
     * @param string $pageHtml Html of page
     *
     * @return mixed
     */
    private function _restoreSeoOptions($pageHtml) {
        $titleInBrowser = '';
        $keywords = '';
        $description = '';
        if ($this->_sections) {
            $props = $this->_getPageProps($this->_sections);
            $titleInBrowser = isset($props['titleInBrowser']) ? $props['titleInBrowser'] : '';
            $keywords = isset($props['keywords']) ? $props['keywords'] : '';
            $description = isset($props['description']) ? $props['description'] : '';
        }

        if ($this->_article->metakey && $keywords) {
            $pageHtml = str_replace('<meta name="keywords" content="' . $keywords . '">', '<meta name="keywords" content="' . $this->_article->metakey . '">', $pageHtml);
        }
        if ($this->_article->metadesc && $description) {
            $pageHtml = str_replace('<meta name="description" content="' . $description . '">', '<meta name="description" content="' . $this->_article->metadesc . '">', $pageHtml);
        }
        if ($this->_article->attribs) {
            $registry = new JRegistry();
            $registry->loadString($this->_article->attribs);
            $attribs = $registry->toArray();
            if (isset($attribs['article_page_title']) && $attribs['article_page_title'] && $titleInBrowser) {
                $pageHtml = str_replace('<title>' . $titleInBrowser . '</title>', '<title>' . $attribs['article_page_title'] . '</title>', $pageHtml);
            }
        }
        return $pageHtml;
    }

    /**
     * Restore page type for editor
     *
     * @param string $pageHtml Page html
     *
     * @return mixed
     */
    private function _restorePageType($pageHtml) {
        if ($this->_sections) {
            $props = $this->_getPageProps($this->_sections);
            $pageView = isset($props['pageView']) ? $props['pageView'] : $this->getDefaultPageType();
            $rePageType = '/<meta name="page_type" content="[^"]+?">/';
            if (preg_match($rePageType, $pageHtml)) {
                $pageHtml = preg_replace($rePageType, '<meta name="page_type" content="' . $this->_editorPageTypes[$pageView] . '">', $pageHtml);
            } else {
                $pageHtml = str_replace('<head>', '<head><meta name="page_type" content="' . $this->_editorPageTypes[$pageView] . '">', $pageHtml);
            }
        }
        return $pageHtml;
    }

    /**
     * Get page properties
     *
     * @param object $page     Page entity
     * @param bool   $allProps Get all props
     *
     * @return mixed
     */
    private function _getPageProps($page, $allProps = false)
    {
        return (!$allProps && $page->autosave_props) ? $page->autosave_props : $page->props;
    }

    /**
     * Autosave changes exists
     *
     * @param object $page Page entity
     *
     * @return bool
     */
    private function _autoSaveChangesExists($page) {
        if (!$page) {
            return false;
        }
        return !!$page->autosave_props;
    }

    /**
     * Check the existence of Virtuemart
     *
     * @return bool
     */
    public function vmEnabled()
    {
        if (!file_exists(dirname(JPATH_ADMINISTRATOR) . '/components/com_virtuemart/')) {
            return false;
        }

        if (!JComponentHelper::getComponent('com_virtuemart', true)->enabled) {
            return false;
        }
        return true;
    }

    /**
     * Check force saving or not
     */
    public function forceModified()
    {
        if ($this->_sections) {
            $props = $this->_getPageProps($this->_sections);
            return isset($props['pageCssUsedIds']) ? false : true;
        }
        return true;
    }

    /**
     * Get frontend site url
     *
     * @return string
     */
    public function getFrontendUrl()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('*')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($query);
        $ret = $db->loadObject();

        if ($ret !== null) {
            return dirname(Utility::getAdminUrl()) . '/' . 'index.php?option=com_content&view=article&id=' . $ret->id . '&toEdit=1';
        } else {
            $frontEndUri = new JUri(dirname(dirname((JURI::current()))) . '/');
            $frontEndUri->setVar('toEdit', '1');
            return $frontEndUri->toString();
        }
    }

    /**
     * Get editor settings from default theme
     *
     * @return mixed|null
     */
    public function getEditorSettingsFromDefaultTheme()
    {
        $template = Utility::getActiveTemplate();
        if ($template) {
            $funcsFilePath = dirname(dirname(JPATH_THEMES)) . '/templates/' . $template . '/template.json';
            if (file_exists($funcsFilePath) && ($content = JFile::read($funcsFilePath)) !== false) {
                return json_decode($content, true);
            }
        }
        return null;
    }

    /**
     * Get content from nicepage-dynamic.css
     *
     * @return string
     */
    public function getDynamicNicepageCss()
    {
        $assets = dirname(JPATH_PLUGINS) . '/administrator/components/com_nicepage/assets';
        $cssPath = $assets . '/css/nicepage-dynamic.css';
        $result = '';
        if (file_exists($cssPath) && ($content = JFile::read($cssPath)) !== false) {
            $result = $content;
        }
        return $result;
    }
}