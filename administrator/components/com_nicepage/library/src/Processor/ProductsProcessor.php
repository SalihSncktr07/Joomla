<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

namespace NP\Processor;

defined('_JEXEC') or die;

use NP\Utility\GridHelper;
use NP\Utility\Pagination;
use NP\Models\ContentModelCustomProducts;
use \vmJsApi, \JFactory;

class ProductsProcessor
{
    private $_products = array();
    private $_product = array();
    private $_pageId;

    private $_productsList = array();
    private $_productsPosition = 0;
    private $_paginationProps = null;

    private $_quantityExists = false;

    /**
     * ProductsProcessor constructor.
     *
     * @param string $pageId Page id
     */
    public function __construct($pageId = '')
    {
        $this->_pageId = $pageId;
    }

    /**
     * Process products
     *
     * @param string $content Content
     *
     * @return string|string[]|null
     */
    public function process($content) {
        $content = preg_replace_callback('/<\!--products-->([\s\S]+?)<\!--\/products-->/', array(&$this, '_processProducts'), $content);
        $content = preg_replace_callback('/<\!--product-->([\s\S]+?)<\!--\/product-->/', array(&$this, '_processProduct'), $content);

        $content = $this->_fixVmScripts($content);

        if (strpos($content, 'none-post-image') !== false) {
            $content = str_replace('u-products-item', 'u-products-item u-invisible', $content);
        }

        return $content;
    }

    /**
     * Process one products
     *
     * @param string $content Page content
     *
     * @return int
     */
    public function processProductsByAjaxLoad($content) {
        preg_replace_callback('/<\!--products-->([\s\S]+?)<\!--\/products-->/', array(&$this, '_processProducts'), $content);
        $position = JFactory::getApplication()->input->get('position', 1);
        $result = array_slice($this->_productsList, $position - 1, 1);
        return count($result) > 0 ? $result[0] : 0;
    }

    /**
     * Fix virtuemart scripts
     *
     * @param string $content Content
     *
     * @return mixed
     */
    private function _fixVmScripts($content)
    {
        $document = JFactory::getDocument();
        $scripts = $document->_scripts;
        $index = 0;
        foreach ($scripts as $filePath => $script) {
            $index++;
            if (preg_match('/com\_virtuemart.+vmprices\.js/', $filePath)) {
                $before = array_slice($scripts, 0, $index - 1);
                $after = array_slice($scripts, $index);
                $newFilePath = str_replace('com_virtuemart', 'com_nicepage', $filePath);
                $new = array($newFilePath => $script);
                $scripts = array_merge($before, $new, $after);
            }
        }
        $document->_scripts = $scripts;

        $content = str_replace('Virtuemart.product($("form.product"));', 'Virtuemart.product($(".product"));', $content);
        return $content;
    }

    /**
     * Process products
     *
     * @param array $productsMatch Matches
     *
     * @return string|string[]|null
     */
    private function _processProducts($productsMatch) {
        $this->_paginationProps = null;
        $this->_productsPosition += 1;
        $productsHtml = $productsMatch[1];
        $productsOptions = array();
        if (preg_match('/<\!--products_options_json--><\!--([\s\S]+?)--><\!--\/products_options_json-->/', $productsHtml, $matches)) {
            $productsOptions = json_decode($matches[1], true);
            $productsHtml = str_replace($matches[0], '', $productsHtml);
        }
        $productsSourceType = isset($productsOptions['type']) ? $productsOptions['type'] : '';
        if ($productsSourceType === 'products-featured') {
            $productsSource = 'Featured products';
        } else if ($productsSourceType === 'products-recent') {
            $productsSource = 'Recent products';
        } else {
            $productsSource = isset($productsOptions['source']) && $productsOptions['source'] ? $productsOptions['source'] : '';
        }
        $productsCount = isset($productsOptions['count']) ? (int) $productsOptions['count'] : '';
        $products = $this->_getProducts(array('categoryName' => $productsSource));
        $this->_quantityExists = false;

        if ($productsCount && count($products) > $productsCount) {
            $app = JFactory::getApplication();
            $limitstart = $app->input->get('offset', 0);
            $pageId = $app->input->get('pageId', $this->_pageId);
            $positionOnPage = $app->input->get('position', $this->_productsPosition);
            $this->_paginationProps = array(
                'allPosts' => count($products),
                'offset' => (int) $limitstart,
                'postsPerPage' => $productsCount,
                'pageId' => (int) $pageId,
                'positionOnPage' => $positionOnPage,
                'task' => 'productlist',
            );
            $this->_products = array_slice($products, $limitstart, $productsCount);
        } else {
            $this->_products = $products;
        }
        $productsCount = count($this->_products);
        $productsHtml = preg_replace_callback('/<\!--product_item-->([\s\S]+?)<\!--\/product_item-->/', array(&$this, '_processProductItem'), $productsHtml);
        $productsHtml = preg_replace_callback('/<\!--products_pagination-->([\s\S]+?)<\!--\/products_pagination-->/', array(&$this, '_processProductsPagination'), $productsHtml);

        $productsGridProps = isset($productsOptions['gridProps']) ? $productsOptions['gridProps'] : array();
        $productsHtml .= GridHelper::buildGridAutoRowsStyles($productsGridProps, $productsCount);

        array_push($this->_productsList, $productsHtml);
        return $productsHtml;
    }

    /**
     * Process pagination
     *
     * @param array $paginationMatch Matches
     *
     * @return false|mixed|string
     */
    private function _processProductsPagination($paginationMatch) {
        if (!$this->_paginationProps) {
            return '';
        }
        $paginationHtml = $paginationMatch[1];
        $paginationStyleOptions = array();
        if (preg_match('/<\!--products_pagination_options_json--><\!--([\s\S]+?)--><\!--\/products_pagination_options_json-->/', $paginationHtml, $matches)) {
            $paginationStyleOptions = json_decode($matches[1], true);
        }
        $pagination = new Pagination($this->_paginationProps, $paginationStyleOptions);
        return $pagination->getPagination();
    }

    /**
     * Process product
     *
     * @param array $productMatch Matches
     *
     * @return string|string[]|null
     */
    private function _processProduct($productMatch) {
        $productHtml = $productMatch[1];

        $productOptions = array();
        if (preg_match('/<\!--product_options_json--><\!--([\s\S]+?)--><\!--\/product_options_json-->/', $productHtml, $matches)) {
            $productOptions = json_decode($matches[1], true);
            $productHtml = str_replace($matches[0], '', $productHtml);
        }
        if (isset($productOptions['source']) && $productOptions['source']) {
            $options = array('productId' => $productOptions['source']);
        } else {
            $options = array('categoryName' => 'Recent products');
        }
        $options['pageId'] = $this->_pageId;
        $this->_products = array_slice($this->_getProducts($options), 0, 1);
        $productHtml = preg_replace_callback('/<\!--product_item-->([\s\S]+?)<\!--\/product_item-->/', array(&$this, '_processProductItem'), $productHtml);
        $productHtml = '<div class="product-container">' . $productHtml . '</div>';
        return $productHtml;
    }

    /**
     * Process product item
     *
     * @param array $productItemMatch Matches
     *
     * @return mixed|string|string[]|null
     */
    private function _processProductItem($productItemMatch) {
        $productItemHtml = $productItemMatch[1];

        if (count($this->_products) < 1) {
            return ''; // remove cell, if post is missing
        }

        $productItemHtml = str_replace('u-products-item ', 'u-products-item product ', $productItemHtml);
        $productItemHtml = str_replace('u-product ', 'u-product product ', $productItemHtml);

        $result = '';
        while (count($this->_products) > 0) {
            $this->_product = array_shift($this->_products);
            $newProductItemtHtml = preg_replace_callback('/<\!--product_title-->([\s\S]+?)<\!--\/product_title-->/', array(&$this, '_setTitleData'), $productItemHtml);
            $newProductItemtHtml = preg_replace_callback('/<\!--product_content-->([\s\S]+?)<\!--\/product_content-->/', array(&$this, '_setTextData'), $newProductItemtHtml);
            $newProductItemtHtml = preg_replace_callback('/<\!--product_image-->([\s\S]+?)<\!--\/product_image-->/', array(&$this, '_setImageData'), $newProductItemtHtml);
            $newProductItemtHtml = preg_replace_callback('/<\!--product_quantity-->([\s\S]+?)<\!--\/product_quantity-->/', array(&$this, '_setQuantityData'), $newProductItemtHtml);
            $newProductItemtHtml = preg_replace_callback('/<\!--product_button-->([\s\S]+?)<\!--\/product_button-->/', array(&$this, '_setButtonData'), $newProductItemtHtml);
            $newProductItemtHtml = preg_replace_callback('/<\!--product_price-->([\s\S]+?)<\!--\/product_price-->/', array(&$this, '_setPriceData'), $newProductItemtHtml);
            $newProductItemtHtml = preg_replace_callback('/<\!--product_gallery-->([\s\S]+?)<\!--\/product_gallery-->/', array(&$this, '_setGalleryData'), $newProductItemtHtml);
            $newProductItemtHtml = preg_replace_callback('/<\!--product_variations-->([\s\S]+?)<\!--\/product_variations-->/', array(&$this, '_setVariationsData'), $newProductItemtHtml);
            $newProductItemtHtml = preg_replace_callback('/<\!--product_tabs-->([\s\S]+?)<\!--\/product_tabs-->/', array(&$this, '_setTabsData'), $newProductItemtHtml);
            $result .= $newProductItemtHtml;
        }
        return $result;
    }

    /**
     * Get products by source
     *
     * @param array $options Source options
     *
     * @return array
     */
    private function _getProducts($options)
    {
        $products = new ContentModelCustomProducts($options);
        return $products->getProducts();
    }

    /**
     * Set title
     *
     * @param string $titleMatch Title match
     *
     * @return mixed|string|string[]|null
     */
    private function _setTitleData($titleMatch) {
        $titleHtml = $titleMatch[1];
        $titleHtml = preg_replace_callback(
            '/<\!--product_title_content-->([\s\S]+?)<\!--\/product_title_content-->/',
            function ($titleContentMatch) {
                return isset($this->_product['product-title']) ? $this->_product['product-title'] : $titleContentMatch[1];
            },
            $titleHtml
        );
        $titleLink = isset($this->_product['product-title-link']) ? $this->_product['product-title-link'] : '#';
        $titleHtml = preg_replace('/(href=[\'"])([\s\S]+?)([\'"])/', '$1' . $titleLink . '$3', $titleHtml);
        return $titleHtml;
    }

    /**
     * Set text
     *
     * @param string $textMatch Text match
     *
     * @return mixed|string|string[]|null
     */
    private function _setTextData($textMatch) {
        $textHtml = $textMatch[1];
        $textHtml = preg_replace_callback(
            '/<\!--product_content_content-->([\s\S]+?)<\!--\/product_content_content-->/',
            function ($contentMatch) {
                return isset($this->_product['product-desc']) ? $this->_product['product-desc'] : $contentMatch[1];
            },
            $textHtml
        );
        return $textHtml;
    }

    /**
     * Set product image
     *
     * @param string $imageMatch Image match
     *
     * @return mixed
     */
    private function _setImageData($imageMatch) {
        $imageHtml = $imageMatch[1];
        $isBackgroundImage = strpos($imageHtml, '<div') !== false ? true : false;

        $link = isset($this->_product['product-title-link']) ? $this->_product['product-title-link'] : '';
        $src = isset($this->_product['product-image']) ? $this->_product['product-image'] : '';

        if (!$src) {
            return $isBackgroundImage ? $imageHtml : '<div class="none-post-image" style="display: none;"></div>';
        }

        if ($isBackgroundImage) {
            $imageHtml = str_replace('<div', '<div data-product-control="' . $link . '"', $imageHtml);
            if (strpos($imageHtml, 'data-bg') !== false) {
                $imageHtml = preg_replace('/(data-bg=[\'"])([\s\S]+?)([\'"])/', '$1url(' . $this->_product['product-image'] . ')$3', $imageHtml);
            } else {
                $imageHtml = str_replace('<div', '<div' . ' style="background-image:url(' . $this->_product['product-image'] . ')"', $imageHtml);
            }
        } else {
            $imageHtml = preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $this->_product['product-image'] . '$3 style="cursor:pointer;" data-product-control="' . $link . '"', $imageHtml);
        }

        return $imageHtml;
    }

    /**
     * Set tabs data
     *
     * @param array $quantityMatch Quantity match
     *
     * @return mixed|string|string[]|null
     */
    private function _setQuantityData($quantityMatch) {
        $quantityHtml = $quantityMatch[1];

        if ($this->_product['product-quantity-notify']) {
            return $this->_product['product-quantity-notify'];
        }

        if (!$this->_product['product-quantity-html']) {
            return '';
        }

        $quantityHtml = preg_replace_callback(
            '/<\!--product_quantity_label_content-->([\s\S]+?)<\!--\/product_quantity_label_content-->/',
            function ($quantityLabelContentMatch) {
                return isset($this->_product['product-quantity-label']) ? $this->_product['product-quantity-label'] : $quantityLabelContentMatch[1];
            },
            $quantityHtml
        );

        $quantityHtml = preg_replace_callback(
            '/<\!--product_quantity_input-->([\s\S]+?)<\!--\/product_quantity_input-->/',
            function ($quantityInputMatch) {
                $quantityInputHtml = $quantityInputMatch[1];
                preg_match('/class=[\'"](.*?)[\'"]/', $quantityInputHtml, $inputClassMatch);
                $newQuantityInputHtml = str_replace('js-recalculate', 'js-recalculate ' . $inputClassMatch[1], $this->_product['product-quantity-html']);
                $newQuantityInputHtml = str_replace('quantity-input', '', $newQuantityInputHtml);
                return $newQuantityInputHtml;
            },
            $quantityHtml
        );

        $quantityHtml = str_replace('minus', 'quantity-minus', $quantityHtml);
        $quantityHtml = str_replace('plus', 'quantity-plus', $quantityHtml);
        $quantityHtml = str_replace('disabled', '', $quantityHtml);

        $this->_quantityExists = true;

        return $quantityHtml;
    }

    /**
     * Set product button
     *
     * @param array $buttonMatch Image match
     *
     * @return mixed
     */
    private function _setButtonData($buttonMatch) {
        $isOnlyCatalog = !$this->_product['product-button-text'] ? true : false;
        if ($isOnlyCatalog) {
            return '';
        }
        $buttonHtml = $buttonMatch[1];
        $controlOptions = array();
        if (preg_match('/<\!--options_json--><\!--([\s\S]+?)--><\!--\/options_json-->/', $buttonHtml, $matches)) {
            $controlOptions = json_decode($matches[1], true);
            $buttonHtml = str_replace($matches[0], '', $buttonHtml);
        }
        $goToProduct = false;
        if (isset($controlOptions['clickType']) && $controlOptions['clickType'] === 'go-to-page') {
            $goToProduct = true;
        }
        if ($this->_product['product-button-html'] && isset($controlOptions['content']) && $controlOptions['content']) {
            $this->_product['product-button-text'] = $controlOptions['content'];
        }
        $buttonHtml = preg_replace_callback(
            '/<\!--product_button_content-->([\s\S]+?)<\!--\/product_button_content-->/',
            function ($buttonContentMatch) {
                return isset($this->_product['product-button-text']) ? $this->_product['product-button-text'] : $buttonContentMatch[1];
            },
            $buttonHtml
        );
        if ($this->_product['product-button-html'] && !$goToProduct) {
            $buttonHtml = str_replace('[[button]]', $buttonHtml, $this->_product['product-button-html']);
            $defaultQuantityHtml = '<input type="hidden" class="quantity-input js-recalculate" name="quantity[]" value="1">';
            $buttonHtml = str_replace('[[quantity]]', !$this->_quantityExists ? $defaultQuantityHtml : '', $buttonHtml);
            $buttonHtml = str_replace('<a', '<a name="addtocart"', $buttonHtml);
            $buttonLink = '#';
        } else {
            $buttonLink = $this->_product['product-button-link'];
        }
        $buttonHtml = preg_replace('/(href=[\'"])([\s\S]+?)([\'"])/', '$1' . $buttonLink . '$3', $buttonHtml);

        vmJsApi::jPrice();
        vmJsApi::cssSite();
        vmJsApi::jDynUpdate();

        $buttonHtml .= vmJsApi::writeJS();
        return $buttonHtml;
    }

    /**
     * Set product price
     *
     * @param array $priceMatch Price match
     *
     * @return mixed|string|string[]|null
     */
    private function _setPriceData($priceMatch) {
        $priceHtml = $priceMatch[1];

        $priceHtml = preg_replace_callback(
            '/<\!--product_regular_price-->([\s\S]+?)<\!--\/product_regular_price-->/',
            function ($regularPriceMatch) {
                if ($this->_product['product-price']) {
                    return preg_replace('/<\!--product_regular_price_content-->([\s\S]+?)<\!--\/product_regular_price_content-->/', $this->_product['product-price'], $regularPriceMatch[1]);
                } else {
                    return '';
                }
            },
            $priceHtml
        );

        $priceHtml = preg_replace_callback(
            '/<\!--product_old_price-->([\s\S]+?)<\!--\/product_old_price-->/',
            function ($oldPriceMatch) {
                if ($this->_product['product-old-price'] && $this->_product['product-old-price'] !== $this->_product['product-price']) {
                    return preg_replace('/<\!--product_old_price_content-->([\s\S]+?)<\!--\/product_old_price_content-->/', $this->_product['product-old-price'], $oldPriceMatch[1]);
                } else {
                    return '';
                }
            },
            $priceHtml
        );

        return $priceHtml;
    }

    /**
     * Set gallery data
     *
     * @param array $galleryMatch Gallery match
     *
     * @return string
     */
    private function _setGalleryData($galleryMatch) {
        $galleryHtml = $galleryMatch[1];
        $galleryData = $this->_product['product-gallery'];

        if (count($galleryData) < 1) {
            return '';
        }

        $controlOptions = array();
        if (preg_match('/<\!--options_json--><\!--([\s\S]+?)--><\!--\/options_json-->/', $galleryHtml, $matches)) {
            $controlOptions = json_decode($matches[1], true);
            $galleryHtml = str_replace($matches[0], '', $galleryHtml);
        }

        $maxItems = -1;
        if (isset($controlOptions['maxItems']) && $controlOptions['maxItems']) {
            $maxItems = (int) $controlOptions['maxItems'];
        }

        if ($maxItems !== -1 && count($galleryData) > $maxItems) {
            $galleryData = array_slice($galleryData, 0, $maxItems);
        }

        $galleryItemRe = '/<\!--product_gallery_item-->([\s\S]+?)<\!--\/product_gallery_item-->/';
        preg_match($galleryItemRe, $galleryHtml, $galleryItemMatch);
        $galleryItemHtml = str_replace('u-active', '', $galleryItemMatch[1]);

        $galleryThumbnailRe = '/<\!--product_gallery_thumbnail-->([\s\S]+?)<\!--\/product_gallery_thumbnail-->/';
        $galleryThumbnailHtml = '';
        if (preg_match($galleryThumbnailRe, $galleryHtml, $galleryThumbnailMatch)) {
            $galleryThumbnailHtml = $galleryThumbnailMatch[1];
        }

        $newGalleryItemListHtml = '';
        $newThumbnailListHtml = '';
        foreach ($galleryData as $key => $img) {
            $newGalleryItemHtml = $key == 0 ? str_replace('u-gallery-item', 'u-gallery-item u-active', $galleryItemHtml) : $galleryItemHtml;
            $newGalleryItemListHtml .= preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $img . '$3', $newGalleryItemHtml);
            if ($galleryThumbnailHtml) {
                $newThumbnailHtml = preg_replace('/data-u-slide-to=([\'"])([\s\S]+?)([\'"])/', 'data-u-slide-to="' . $key . '"', $galleryThumbnailHtml);
                $newThumbnailListHtml .= preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $img . '$3', $newThumbnailHtml);
            }
        }

        $galleryParts = preg_split($galleryItemRe, $galleryHtml, -1, PREG_SPLIT_NO_EMPTY);
        $newGalleryHtml = $galleryParts[0] . $newGalleryItemListHtml . $galleryParts[1];

        $newGalleryParts = preg_split($galleryThumbnailRe, $newGalleryHtml, -1, PREG_SPLIT_NO_EMPTY);
        return $newGalleryParts[0] . $newThumbnailListHtml . $newGalleryParts[1];
    }

    /**
     * Set variations data
     *
     * @param array $variationsMatch Variations match
     *
     * @return mixed|string|string[]|null
     */
    private function _setVariationsData($variationsMatch) {
        $variationsHtml = $variationsMatch[1];
        $variationsData = $this->_product['product-variations'];

        if (count($variationsData) < 1) {
            return '';
        }

        $variationRe = '/<\!--product_variation-->([\s\S]+?)<\!--\/product_variation-->/';
        preg_match($variationRe, $variationsHtml, $variationMatch);

        $newVariationListHtml = '';
        foreach ($variationsData as $i => $variationData) {
            $newVariationHtml = str_replace('<select', '<select ' . $variationData['s_attributes'], $variationMatch[1]);
            $newVariationHtml = str_replace('u-input ', 'u-input ' . $variationData['s_classes'] . ' ', $newVariationHtml);
            $newVariationHtml = preg_replace('/<\!--product_variation_label_content-->([\s\S]+?)<\!--\/product_variation_label_content-->/', $variationData['title'], $newVariationHtml);
            preg_match('/<\!--product_variation_option-->([\s\S]+?)<\!--\/product_variation_option-->/', $newVariationHtml, $optionMatch);
            $optionHtml = $optionMatch[1];

            $options = $variationData['options'];
            $newOptionsHtml = '';
            foreach ($options as $option) {
                $newOptionHtml = preg_replace('/<\!--product_variation_option_content-->([\s\S]+?)<\!--\/product_variation_option_content-->/', $option['text'], $optionHtml);
                if ($option['selected']) {
                    $newOptionHtml = str_replace('<option', '<option selected="selected"', $newOptionHtml);
                }
                $newOptionHtml = preg_replace('/(value=[\'"])([\s\S]+?)([\'"])/', '$1[[value]]$3', $newOptionHtml);
                $newOptionHtml = str_replace('[[value]]', $option['value'], $newOptionHtml);
                $newOptionsHtml .= $newOptionHtml;
            }
            if ($i !== 0) {
                $newVariationHtml = '<div style="margin-top: 10px;">' . $newVariationHtml . '</div>';
            }
            $newVariationParts = preg_split('/<\!--product_variation_option-->([\s\S]+?)<\!--\/product_variation_option-->/', $newVariationHtml, -1, PREG_SPLIT_NO_EMPTY);
            $newVariationListHtml .= $newVariationParts[0] . $newOptionsHtml . $newVariationParts[1];
        }

        $variationsParts = preg_split($variationRe, $variationsHtml, -1, PREG_SPLIT_NO_EMPTY);
        $newVariationsHtml = $variationsParts[0] . $newVariationListHtml . $variationsParts[1];
        $newVariationsHtml = str_replace('u-product-variations ', 'u-product-variations product-field-display ', $newVariationsHtml);
        return $newVariationsHtml;
    }

    /**
     * Set tabs data
     *
     * @param array $tabsMatch Tabs match
     *
     * @return mixed|string|string[]|null
     */
    private function _setTabsData($tabsMatch) {
        $tabsHtml = $tabsMatch[1];
        $tabsData = $this->_product['product-tabs'];

        if (count($tabsData) < 1) {
            return '';
        }

        $tabItemRe = '/<\!--product_tabitem-->([\s\S]+?)<\!--\/product_tabitem-->/';
        preg_match($tabItemRe, $tabsHtml, $tabItemMatch);
        $tabItemLinkClassRe = '/(class=[\'"])(.*?u-tab-link.*?)([\'"])/';
        preg_match($tabItemLinkClassRe, $tabItemMatch[1], $tabItemLinkClassMatch);
        $classesParts = explode(' ', $tabItemLinkClassMatch[2]);
        $key = array_search('active', $classesParts);
        if ($key !== false) {
            array_splice($classesParts, $key, 1);
        }
        $tabItemHtml = preg_replace($tabItemLinkClassRe, '$1' . implode(' ', $classesParts) . '$3', $tabItemMatch[1]);

        $tabPaneRe = '/<\!--product_tabpane-->([\s\S]+?)<\!--\/product_tabpane-->/';
        preg_match($tabPaneRe, $tabsHtml, $tabPaneMatch);
        $tabPaneHtml = str_replace('u-tab-active', '', $tabPaneMatch[1]);

        $newTabItemListHtml = '';
        $newTabPaneListHtml = '';
        foreach ($tabsData as $key => $tab) {
            $newTabItemHtml = preg_replace('/<\!--product_tabitem_title-->([\s\S]+?)<\!--\/product_tabitem_title-->/', $tab['title'], $tabItemHtml);
            $newTabItemHtml = $key == 0 ? str_replace('u-tab-link', 'u-tab-link active', $newTabItemHtml) : $newTabItemHtml;
            $newTabItemHtml = preg_replace('/(id=[\'"])([\s\S]+?)([\'"])/', '$1tab-' . $tab['guid'] . '$3', $newTabItemHtml);
            $newTabItemHtml = preg_replace('/(href=[\'"])([\s\S]+?)([\'"])/', '$1#link-tab-' . $tab['guid'] . '$3', $newTabItemHtml);
            $newTabItemHtml = preg_replace('/(aria-controls=[\'"])([\s\S]+?)([\'"])/', '$1link-tab-' . $tab['guid'] . '$3', $newTabItemHtml);
            $newTabItemListHtml .= $newTabItemHtml;

            $newTabPaneHtml = $key == 0 ? str_replace('u-tab-pane', 'u-tab-pane u-tab-active', $tabPaneHtml) : $tabPaneHtml;
            $newTabPaneHtml = preg_replace('/(id=[\'"])([\s\S]+?)([\'"])/', '$1link-tab-' . $tab['guid'] . '$3', $newTabPaneHtml);
            $newTabPaneHtml = preg_replace('/(aria-labelledby=[\'"])([\s\S]+?)([\'"])/', '$1tab-' . $tab['guid'] . '$3', $newTabPaneHtml);
            $newTabPaneHtml = preg_replace('/<\!--product_tabpane_content-->([\s\S]+?)<\!--\/product_tabpane_content-->/', $tab['content'], $newTabPaneHtml);
            $newTabPaneListHtml .= $newTabPaneHtml;
        }

        $tabsParts = preg_split($tabItemRe, $tabsHtml, -1, PREG_SPLIT_NO_EMPTY);
        $newTabsHtml = $tabsParts[0] . $newTabItemListHtml . $tabsParts[1];

        $tabsParts = preg_split($tabPaneRe, $newTabsHtml, -1, PREG_SPLIT_NO_EMPTY);
        return $tabsParts[0] . $newTabPaneListHtml . $tabsParts[1];
    }
}