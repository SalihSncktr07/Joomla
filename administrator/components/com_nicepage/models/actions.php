<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */
defined('_JEXEC') or die;

use NP\Uploader\FileUploader;
use NP\Uploader\Chunk;
use NP\Editor\SitePostsBuilder;
use NP\Editor\MenuItemsSaver;
use NP\Editor\PageSaver;
use NP\Editor\ConfigSaver;

/**
 * Class NicepageModelActions
 */
class NicepageModelActions extends JModelAdmin
{
    /**
     * NicepageModelActions constructor.
     */
    public function __construct()
    {
        jimport('joomla.filesystem.file');
        jimport('joomla.filesystem.folder');

        parent::__construct();
    }

    /**
     * Method to get the record form.
     *
     * @param array   $data     Data for the form. [optional]
     * @param boolean $loadData True if the form is to load its own data (default case), false if not. [optional]
     *
     * @return JForm|boolean A JForm object on success, false on failure
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_nicepage.page', 'page', array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }
        return $form;
    }

    /**
     * Get data
     *
     * @param array $data Data parameters
     *
     * @return array|JInput|string
     */
    private function _getRequestData($data) {
        $saveType = $data->get('saveType', '');
        switch ($saveType) {
        case 'base64':
            return new JInput(json_decode(base64_decode($data->get('data', '', 'RAW')), true));
            break;
        case 'chunks':
            $chunk = new Chunk();
            $ret = $chunk->save($data);
            if (is_array($ret)) {
                return array($ret);
            }
            if ($chunk->last()) {
                $result = $chunk->complete();
                if ($result['status'] === 'done') {
                    return new JInput(json_decode(base64_decode($result['data']), true));
                } else {
                    $result['result'] = 'error';
                    return array($result);
                }
            } else {
                return 'processed';
            }
            break;
        default:
        }
        return $data;
    }

    /**
     * Get service worker
     */
    public function getSw() {
        $sw = JPATH_ADMINISTRATOR . '/components/com_nicepage/assets/app/sw.js';
        if (file_exists($sw)) {
            $content = file_get_contents($sw);
            header('Content-Type: application/javascript');
            exit($content);
        }
    }

    /**
     * Main Action - Get pseudo posts to build new page
     *
     * @param JInput $data Data parameters
     *
     * @return mixed|string
     */
    public function getSitePosts($data) {
        $builder = new SitePostsBuilder();
        return $this->_response(
            array(
                'result' => 'done',
                'data' => $builder->getSitePosts($data),
            )
        );
    }

    /**
     * Save local storage key
     *
     * @param JInput $data Data parameters
     *
     * @return mixed|string
     */
    public function saveLocalStorageKey($data) {
        $data = $this->_getRequestData($data);
        if (is_string($data) || (is_array($data) && isset($data['status']) && $data['status'] === 'error')) {
            return $this->_response($data);
        }
        $json = $data->get('json', array(), 'RAW');
        ConfigSaver::saveConfig(array('localStorageKey' => $json));
        return $this->_response(
            array(
                'result' => 'done',
                'data' => $json,
            )
        );
    }


    /**
     * Get site object by page id
     *
     * @return array
     */
    public function getSite()
    {
        $config = NicepageHelpersNicepage::getConfig();
        $siteSettings = isset($config['siteSettings']) ? $config['siteSettings'] : '{}';
        $site = array(
            'id' => '1',
            'isFullLoaded' => true,
            'items' => array(),
            'order' => 0,
            'publicUrl' => $this->getHomeUrl(),
            'status' => 2,
            'title' => JFactory::getConfig()->get('sitename', 'My Site'),
            'settings' => $siteSettings
        );

        $pages = array();
        $sectionsPageIds = NicepageHelpersNicepage::getSectionsTable()->getAllPageIds();
        if (count($sectionsPageIds) > 0) {
            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query->select('*');
            $query->from('#__content');
            $query->where('(state = 1 or state = 0)');
            $query->where('id in (' . implode(',', $sectionsPageIds) . ')');
            $query->order('created', 'desc');
            $db->setQuery($query);
            $list = $db->loadObjectList();

            foreach ($list as $key => $item) {
                $pages[] = $this->_getPageData($item);
            }
        }
        $site['items'] = $pages;
        return $site;
    }

    /**
     *
     * @return string
     */
    public function getPageHtml()
    {
        $html = '';
        $pageId = JFactory::getApplication()->input->get('pageId', -1);
        $page = NicepageHelpersNicepage::getSectionsTable();
        if ($page->load(array('page_id' => $pageId))) {
            $props = $page->autosave_props ? $page->autosave_props : $page->props;
            $html = isset($props['html']) ? $props['html'] : '';
            $html = NicepageHelpersNicepage::processSectionsHtml($html, false);
        }
        return $html;
    }

    /**
     * Convert cms post to editor format
     *
     * @param object $postObject Cms post object
     *
     * @return array
     */
    private function _getPageData($postObject)
    {
        $head = null;
        $page = NicepageHelpersNicepage::getSectionsTable();
        if ($page->load(array('page_id' => $postObject->id))) {
            $head = isset($page->props['head']) ? $page->props['head'] : '';
        }
        $domain = JFactory::getApplication()->input->get('domain', '', 'RAW');
        $current = dirname(dirname((JURI::current())));
        $adminPanelUrl = $current . '/administrator';
        return array(
            'siteId' => '1',
            'title' => $postObject->title,
            'publicUrl' => $this->getArticleUrlById($postObject->id),
            'publishUrl' => $this->getArticleUrlById($postObject->id),
            'canShare' => false,
            'html' => null,
            'head' => $head,
            'keywords' => null,
            'imagesUrl' => array(),
            'id' => (int) $postObject->id,
            'order' => 0,
            'status' => 2,
            'editorUrl' => $adminPanelUrl . '/index.php?option=com_nicepage&task=nicepage.autostart&postid=' . $postObject->id . ($domain ? '&domain=' . $domain : ''),
            'htmlUrl' => $adminPanelUrl . '/index.php?option=com_nicepage&task=actions.getPageHtml&pageId=' . $postObject->id
        );
    }

    /**
     * Main Action - Upload new image
     *
     * @param JInput $data Data parameters
     *
     * @return bool|mixed|string
     */
    public function uploadImage($data)
    {
        $files = JFactory::getApplication()->input->files;
        if (!$files) {
            JFactory::getApplication()->enqueueMessage(JText::_('File not found'), 'error');
            return false;
        }

        $file = $files->get('async-upload');

        $imagesPaths = $this->getImagesPaths();
        $name = $file['name'];
        $file['filepath'] = $imagesPaths['realpath'] . '/' . $name;

        if (file_exists($file['filepath'])) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $name = md5($file['name'] . microtime()) . '.' . $ext;
            $file['filepath'] = $imagesPaths['realpath'] . '/' . $name;
        }

        $objectFile = new JObject($file);
        if (!JFile::upload($objectFile->tmp_name, $objectFile->filepath)) {
            JFactory::getApplication()->enqueueMessage(JText::_('Unable to upload file'), 'error');
            return false;
        }

        $info = @getimagesize($file['filepath']);
        $imagesUrl = str_replace(JPATH_ROOT, $this->getHomeUrl(), $file['filepath']);
        $imagesUrl = str_replace('\\', '/', $imagesUrl);
        return $this->_response(
            array(
                'status' => 'done',
                'image' => array(
                    'sizes' => array(
                        array(
                            'height' => @$info[1],
                            'url' => $imagesUrl,
                            'width' => @$info[0],
                        )
                    ),
                    'type' => 'image',
                    'id' => $name
                )
            )
        );
    }

    /**
     * Main Action - Save new template type of page
     *
     * @param JInput $data Data parameters
     */
    public function savePageType($data) {
        $id   = $data->get('pageId', '');
        $type = $data->get('pageType', '');
        if ($id && $type) {
            $page = NicepageHelpersNicepage::getSectionsTable();
            if ($page->load(array('page_id' => $id))) {
                $props = $page->props;
                $props['pageView'] = $type;
                $page->save(array('props' => $props));
            }
        }
    }

    /**
     * Save site setttings action
     *
     * @param JInput $data Data parameters
     *
     * @return mixed|string
     */
    public function saveSiteSettings($data)
    {
        $data = $this->_getRequestData($data);
        if (is_string($data) || (is_array($data) && isset($data['status']) && $data['status'] === 'error')) {
            return $this->_response($data);
        }
        $settings = $data->get('settings', '', 'RAW');
        ConfigSaver::saveSiteSettings($settings);
        return $this->_response(
            array(
                'result' => 'done'
            )
        );
    }

    /**
     * @param JInput $data Data parameters
     *
     * @return mixed|string
     */
    public function savePreferences($data)
    {
        $data = $this->_getRequestData($data);
        if (is_string($data) || (is_array($data) && isset($data['status']) && $data['status'] === 'error')) {
            return $this->_response($data);
        }

        $settings = $data->get('settings', '', 'RAW');
        if ($settings) {
            if (is_string($settings)) {
                $settings = json_decode($settings, true);
            }
            $disableAutoSave = isset($settings['disableAutosave']) ? $settings['disableAutosave'] : '1';
            $toSave = array('disableAutosave' => $disableAutoSave);
            ConfigSaver::saveConfig($toSave);
        }
        return $this->_response(
            array(
                'result' => 'done'
            )
        );
    }

    /**
     * @param JInput $data Data parameters
     *
     * @return mixed|string
     */
    public function saveMenuItems($data)
    {
        $menuData = $data->get('menuData', '', 'RAW');
        $menuItemsSaver = new MenuItemsSaver($menuData);
        $result = $menuItemsSaver->save();
        return $this->_response($result);
    }

    /**
     * Remove custom font
     *
     * @param JInput $data Data parameters
     *
     * @return mixed|string
     */
    public function removeFont($data)
    {
        $fileName = $data->get('fileName', '', 'RAW');
        $customFontPath = dirname(JPATH_BASE) . '/' . 'images/nicepage-fonts/fonts/' . $fileName;
        $success = true;
        if (JFile::exists($customFontPath) && !JFile::delete($customFontPath)) {
            $success = false;
        }
        return $this->_response(
            array(
                'result' => 'done',
                'success' => $success,
            )
        );
    }

    /**
     * Main Action - New Save or Update page
     *
     * @param JInput $data Data parameters
     *
     * @return mixed|string
     */
    public function savePage($data)
    {
        $data = $this->_getRequestData($data);
        if (is_string($data) || (is_array($data) && isset($data['status']) && $data['status'] === 'error')) {
            return $this->_response($data);
        }

        $pageSaver = new PageSaver($data);

        if (!$pageSaver->check()) {
            return $this->_response(
                array(
                    'status' => 'error',
                    'message' => 'The page parameters is incomplete',
                )
            );
        }

        $pageSaver->save();
        $article = $pageSaver->getArticle();
        return $this->_response(
            array(
                'result' => 'done',
                'data' => $this->_getPageData($article),
            )
        );
    }

    /**
     * Clear chunk by id
     *
     * @param JInput $data Clear chunks
     */
    public function clearChunks($data) {
        $id = $data->get('id', '', 'RAW');
        Chunk::clearChunksById($id);
        return $this->_response(
            array(
                'result' => 'done'
            )
        );
    }

    /**
     * Main Action - Duplicate page
     *
     * @param JInput $data Array of data
     *
     * @return mixed|string
     */
    public function duplicatePage($data)
    {
        $postId = $data->get('postId', '');
        $error = array('status' => 'error');
        $succes = array('result' => 'ok');

        if (!$postId) {
            return $this->_response($error);
        }

        $page = NicepageHelpersNicepage::getSectionsTable();
        if (!$page->load(array('page_id' => $postId))) {
            return $this->_response($error);
        }

        $newPage = NicepageHelpersNicepage::getSectionsTable();
        $pageData = array(
            'page_id'               => 1000000,
            'props'                 => $page->props,
            $newPage->getKeyName()  => null
        );
        if (!$newPage->save($pageData)) {
            return $this->_response($error);
        }

        return $this->_response($succes);
    }

    /**
     * @param string|array $result Result
     *
     * @return mixed|string
     */
    private function _response($result)
    {
        if (is_string($result)) {
            $result = array('result' => $result);
        }
        return json_encode($result);
    }

    /**
     * @return array
     */
    public function getImagesPaths()
    {
        $imagesFolder = JPATH_ROOT . '/images';
        if (!file_exists($imagesFolder)) {
            JFolder::create($imagesFolder);
        }

        $nicepageContentFolder = JPath::clean(implode('/', array($imagesFolder, 'nicepage-images')));
        if (!file_exists($nicepageContentFolder)) {
            JFolder::create($nicepageContentFolder);
        }

        $nicepageContentFolderUrl = $this->getHomeUrl() . '/images/nicepage-images';

        return array('realpath' => $nicepageContentFolder, 'url' => $nicepageContentFolderUrl);
    }

    /**
     * @return string
     */
    public function getHomeUrl()
    {
        return dirname(dirname(JURI::current()));
    }

    /**
     * @param int $id Article id
     *
     * @return string
     */
    public function getArticleUrlById($id)
    {
        return $this->getHomeUrl() . '/index.php?option=com_content&view=article&id=' . $id;
    }

    /**
     * Main Action - Import data from plugin
     *
     * @param JInput $data Data parameters
     *
     * @return mixed|string
     * @throws Exception
     */
    public function importData($data)
    {
        $fileName   = $data->get('filename', '');
        $isLast     = $data->get('last', '');

        if ('' === $fileName) {
            throw new Exception("Empty filename");
        } else {
            $unzipHere = '';

            $tmp = JPATH_SITE . '/tmp';
            if (file_exists($tmp) && is_writable($tmp)) {
                $unzipHere = $tmp . '/' . $fileName;
            }

            $images = JPATH_SITE . '/images';
            if (!$unzipHere && file_exists($images) && is_writable($images)) {
                $unzipHere = $images . '/' . $fileName;
            }

            if (!$unzipHere) {
                throw new Exception("Upload dir don't writable");
            }
            $uploader = new FileUploader();
            $result = $uploader->upload($unzipHere, $isLast);
            if ($result['status'] == 'done') {
                $contentDir = $this->_contentUnZip($unzipHere);

                $contentJsonPath = $contentDir . '/content/content.json';
                if (!file_exists($contentJsonPath)) {
                    $pathInfo = pathinfo($unzipHere);
                    $contentJsonPath = $contentDir . '/' . $pathInfo['filename']. '/content/content.json';
                }

                if (file_exists($contentJsonPath)) {
                    JLoader::register('Nicepage_Data_Loader', JPATH_ADMINISTRATOR . '/components/com_nicepage' . '/helpers/import.php');
                    $loader = new Nicepage_Data_Loader();
                    $loader->load($contentJsonPath);
                    $loader->execute(JFactory::getApplication()->input->getArray());
                }
            }
        }
        return $this->_response(
            array(
                'result' => 'done'
            )
        );
    }

    /**
     * Upload file
     *
     * @param JInput $data File data
     *
     * @return mixed|string
     * @throws Exception
     */
    public function uploadFile($data)
    {
        $fileName   = $data->get('filename', '');
        $isLast     = $data->get('last', '');
        $isFont     = $data->get('isFont', '');

        if ('' === $fileName) {
            throw new Exception("Empty filename");
        } else {
            $uploadHere = '';

            $params = JComponentHelper::getParams('com_media');
            $filesPath = JPATH_SITE . '/' . $params->get('image_path', 'images');

            if ($isFont) {
                $filesPath = $filesPath . '/' . 'nicepage-fonts/fonts';
                if (!JFolder::exists($filesPath)) {
                    if (!JFolder::create($filesPath)) {
                        throw new Exception("Fonts dir don't created");
                    }
                }
            }

            if (file_exists($filesPath) && is_writable($filesPath)) {
                $uploadHere = $filesPath . '/' . $fileName;
            }

            if (!$uploadHere) {
                throw new Exception("Upload dir $uploadHere don't writable");
            }

            $uploader = new FileUploader();
            $result = $uploader->upload($uploadHere, $isLast);
            if ($result['status'] == 'done') {
                if ($isFont) {
                    $fileInfo = pathinfo($result['fileName']);
                    $response = array(
                        'fileName' => $result['fileName'],
                        'id' => 'user-file-' . $result['fileName'],
                        'name' => isset($fileInfo['filename']) ? $fileInfo['filename'] : $fileInfo['basename'],
                        'publicUrl' => str_replace(JPATH_SITE, $this->getHomeUrl(), $result['path']),
                        'result' => 'done',
                    );
                } else {
                    $response = array(
                        'url' => str_replace(JPATH_SITE, $this->getHomeUrl(), $result['path']),
                        'title' => $result['fileName'],
                        'result' => 'done',
                    );
                }
                return $this->_response($response);
            }
        }
        return $this->_response(
            array(
                'result' => 'done'
            )
        );
    }

    /**
     * @param string $zipPath Zip path
     *
     * @return string
     */
    private function _contentUnZip($zipPath)
    {
        $tmpdir = dirname($zipPath) . '/' . md5(round(microtime(true)));
        if (class_exists('ZipArchive')) {
            $this->_nativeUnzip($zipPath, $tmpdir);
        } else {
            $this->_joomlaUnzip($zipPath, $tmpdir);
        }
        JFile::delete($zipPath);
        return $tmpdir;
    }

    /**
     * Native unzip
     *
     * @param string $zipPath Zip path
     * @param string $tmpdir  Tmp path
     */
    private function _nativeUnzip($zipPath, $tmpdir)
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) === true) {
            $zip->extractTo($tmpdir);
            $zip->close();
        }
    }

    /**
     * Joomla unzip
     *
     * @param string $zipPath Zip path
     * @param string $tmpdir  Tmp path
     */
    private function _joomlaUnzip($zipPath, $tmpdir)
    {
        try {
            JArchive::extract($zipPath, $tmpdir);
        } catch (Exception $e) {
            // to do
        }
    }
}