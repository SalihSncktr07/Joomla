<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

namespace NP;

defined('_JEXEC') or die;

use \NicepageHelpersNicepage;

/**
 * Class Config
 */
class Config
{
    private static $_instance;

    private $_config;
    private $_settigns;

    /**
     * Config constructor.
     */
    public function __construct() {
        $this->_config = NicepageHelpersNicepage::getConfig();
        $this->_settigns = isset($this->_config['siteSettings']) ? json_decode($this->_config['siteSettings'], true) : array();
    }

    /**
     * Apply site settings to content\
     *
     * @param string $pageContent Document content
     *
     * @return mixed
     */
    public function applySiteSettings($pageContent) {
        if (count($this->_settigns) < 1) {
            return $pageContent;
        }
        if (isset($this->_settigns['captchaScript']) && $this->_settigns['captchaScript'] && strpos($pageContent, 'recaptchaResponse') !== false) {
            $pageContent = str_replace('</head>', $this->_settigns['captchaScript'] . '</head>', $pageContent);
        }
        if (isset($this->_settigns['metaTags']) && $this->_settigns['metaTags'] && strpos($pageContent, $this->_settigns['metaTags']) === false) {
            $pageContent = str_replace('</head>', $this->_settigns['metaTags'] . '</head>', $pageContent);
        }
        if (isset($this->_settigns['headHtml']) && $this->_settigns['headHtml'] && strpos($pageContent, $this->_settigns['headHtml']) === false) {
            $pageContent = str_replace('</head>', $this->_settigns['headHtml'] . '</head>', $pageContent);
        }
        if (isset($this->_settigns['analyticsCode']) && $this->_settigns['analyticsCode'] && strpos($pageContent, $this->_settigns['analyticsCode']) === false) {
            $pageContent = str_replace('</head>', $this->_settigns['analyticsCode'] . '</head>', $pageContent);
        }
        if (isset($this->_settigns['keywords']) && $this->_settigns['keywords'] && strpos($pageContent, $this->_settigns['keywords']) === false) {
            if (preg_match('/<meta\s+?name="keywords"\s+?content="([^"]+?)"\s+?\/>/', $pageContent, $keywordsMatches)) {
                $pageContent = str_replace($keywordsMatches[0], '<meta name="keywords" content="' . $this->_settigns['keywords'] . ', ' . $keywordsMatches[1] . '" />', $pageContent);
            } else {
                $pageContent = str_replace('<title>', '<meta name="keywords" content="' . $this->_settigns['keywords'] . '" />' . '<title>', $pageContent);
            }
        }
        if (isset($this->_settigns['description']) && $this->_settigns['description'] && strpos($pageContent, $this->_settigns['description']) === false) {
            if (preg_match('/<meta\s+?name="description"\s+?content="([^"]+?)"\s+?\/>/', $pageContent, $descMatches)) {
                $pageContent = str_replace($descMatches[0], '<meta name="description" content="' . $this->_settigns['description'] . ', ' . $descMatches[1] . '" />', $pageContent);
            } else {
                $pageContent = str_replace('<title>', '<meta name="description" content="' . $this->_settigns['description'] . '" />' . '<title>', $pageContent);
            }
        }
        $googleTagManager = isset($this->_settigns['googleTagManager']) && $this->_settigns['googleTagManager'] ? $this->_settigns['googleTagManager'] : '';
        if ($googleTagManager && strpos($pageContent, $googleTagManager) === false
            && isset($this->_config['googleTagManagerCode']) && $this->_config['googleTagManagerCode']
        ) {
            $pageContent = str_replace('</title>', '</title>' . $this->_config['googleTagManagerCode'], $pageContent);
            $pageContent = preg_replace('/(<body[^>]*?>)/', '$1' . $this->_config['googleTagManagerCodeNoScript'], $pageContent);
        }
        return $pageContent;
    }

    /**
     * Get config instance
     *
     * @return Config
     */
    public static function getInstance()
    {
        if (!is_object(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }
}