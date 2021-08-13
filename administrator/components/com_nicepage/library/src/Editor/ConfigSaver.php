<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

namespace NP\Editor;

defined('_JEXEC') or die;

use \JURI, \JInput;
use \NicepageHelpersNicepage;

/**
 * Class ConfigSaver
 */
class ConfigSaver
{
    /**
     * Save custom settings for editor
     *
     * @param array|JInput $data Data parameters
     *
     * @return mixed|string
     */
    public static function saveConfig($data)
    {
        if (!is_array($data)) {
            $data = $data->getArray();
        }
        NicepageHelpersNicepage::saveConfig($data);
    }

    /**
     * Save site settings
     *
     * @param string $settings Data parameters
     * @param bool   $savePage Page save
     */
    public static function saveSiteSettings($settings, $savePage = false) {
        if (!$settings) {
            return;
        }

        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }

        $saveAndPublish = isset($settings['saveAndPublish']) && ($settings['saveAndPublish'] == 'true' || $settings['saveAndPublish']  == '1') ? true : false;
        if (!$savePage) {
            $publishHeaderFooter = self::saveHeaderFooter(new JInput($settings), $saveAndPublish);
        }
        $toSave = array();

        if (isset($settings['googleTagManagerCode']) && isset($settings['googleTagManagerCodeNoScript'])) {
            $toSave['googleTagManagerCode'] = $settings['googleTagManagerCode'];
            $toSave['googleTagManagerCodeNoScript'] = $settings['googleTagManagerCodeNoScript'];
            unset($settings['googleTagManagerCode']);
            unset($settings['googleTagManagerCodeNoScript']);
        }

        $publishCookiesSection = '';
        if (isset($settings['cookiesConsent']) && $settings['cookiesConsent']) {
            $toSave['cookiesConsent'] = json_encode($settings['cookiesConsent']);
            $publishCookiesSection = $settings['cookiesConsent']['publishCookiesSection'];
        } else {
            if (isset($settings['cookies']) && isset($settings['cookieConfirmCode'])) {
                $currentConfig = NicepageHelpersNicepage::getConfig();
                $defaultCookiesSection = $settings['cookiesSection'];
                $cookiesConsent = isset($currentConfig['cookiesConsent']) ? json_decode($currentConfig['cookiesConsent'], true) : array();
                $publishCookiesSection = isset($cookiesConsent['publishCookiesSection']) ? $cookiesConsent['publishCookiesSection'] : $defaultCookiesSection;
                $cookiesConsent = array(
                    'hideCookies' => $settings['cookies'] == 'false' ? 'true' : 'false',
                    'cookieConfirmCode' => $settings['cookieConfirmCode'],
                    'publishCookiesSection' => $publishCookiesSection,
                );
                $toSave['cookiesConsent'] = json_encode($cookiesConsent);
            }
        }

        if ($saveAndPublish && !$savePage && isset($settings['publishNicePageCss']) && $settings['publishNicePageCss']) {
            list($siteStyleCssParts, $pageCssUsedIds, $headerFooterCssUsedIds, $cookiesCssUsedIds) = NicepageHelpersNicepage::processAllColors($settings['publishNicePageCss'], '', $publishHeaderFooter, $publishCookiesSection);
            $toSave['siteStyleCssParts'] = $siteStyleCssParts;
            $toSave['siteStyleCss'] = '';
            $toSave['headerFooterCssUsedIds'] = $headerFooterCssUsedIds;
            $toSave['cookiesCssUsedIds'] = $cookiesCssUsedIds;
        }

        if (isset($settings['showBrand'])) {
            $toSave['hideBacklink'] = $settings['showBrand'] === 'true' ? false : true;
        }
        $toSave['siteSettings'] = json_encode($settings);
        self::saveConfig($toSave);
    }

    /**
     * Save header and footer content
     *
     * @param JInput $data           Data parameters
     * @param bool   $saveAndPublish Save and Publish flag
     * @param bool   $isPreview      Preview flag
     */
    public static function saveHeaderFooter($data, $saveAndPublish = true, $isPreview = false)
    {
        $result = array();
        $keys = array('header', 'footer');
        $publishHeaderFooter = '';
        $currentConfig = NicepageHelpersNicepage::getConfig($isPreview);
        foreach ($keys as $key) {
            $html = $data->get($key, '', 'RAW');
            $htmlCss = $data->get($key . 'Css', '', 'RAW');
            $htmlPhp = $data->get('publish' . ucfirst($key), '', 'RAW');
            $formsData = $data->get($key . 'FormsData', '', 'RAW');
            $dialogs  = $data->get($key . 'Dialogs', '', 'RAW');
            if (!$html) {
                if (isset($currentConfig[$key])) {
                    $item = json_decode($currentConfig[$key], true);
                    $publishHeaderFooter .= $item && isset($item['php']) ? $item['php'] : '';
                }
            } else {
                $publishHeaderFooter .= $htmlPhp;
            }

            if (!$html) {
                continue;
            }

            $homeUrl = dirname(dirname(JURI::current()));
            $html = str_replace($homeUrl, '[[site_path_editor]]', $html);
            $publishParts = str_replace(
                $homeUrl . '/',
                '[[site_path_live]]',
                array(
                    'Css'   => $htmlCss,
                    'Php'   => $htmlPhp,
                )
            );

            if ($saveAndPublish) {
                $result[$key . ':autosave'] = '';
                $result[$key . ':preview'] = '';
            }

            if ($isPreview) {
                $key .= ':preview';
            } else if (!$saveAndPublish) {
                $key .= ':autosave';
            }

            $result[$key] = json_encode(
                array(
                    'html' => $html,
                    'php' => $publishParts['Php'],
                    'styles' => $publishParts['Css'],
                    'formsData' => $formsData,
                    'dialogs'  => $dialogs,
                )
            );
        }
        // Save header and footer content data
        ConfigSaver::saveConfig($result);

        return $publishHeaderFooter;
    }
}