<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

namespace NP\Models;

defined('_JEXEC') or die;

use NP\Builder\ProductDataBuilder;

use \JFactory, \JRoute, \JComponentHelper;
use \VmModel, \vRequest, \VmConfig, \vmJsApi, \vmDefines, \vmLanguage, \VirtueMartCart;

class ContentModelCustomProducts
{
    private $_options = array();

    /**
     * ContentModelCustomProducts constructor.
     *
     * @param array $options options
     */
    public function __construct($options = array())
    {
        $this->_options = $options;
    }

    /**
     * Get products
     *
     * @return array
     */
    public function getProducts() {
        $products = array();

        if (!$this->_vmInit()) {
            return $products;
        }

        $productModel = VmModel::getModel('Product');
        $ratingModel = VmModel::getModel('ratings');

        $items = null;

        if (isset($this->_options['productId'])) {
            $productId = $this->_options['productId'];
            $product = $productModel->getProduct($productId);
            if ($product) {
                $items = array($product);
            }
        } else {
            $category_id = "0"; // top level category
            $filter_category = false;
            $categoryName = isset($this->_options['categoryName']) && $this->_options['categoryName'] ? $this->_options['categoryName'] : '';
            $isFeatured = $categoryName === 'Featured products' ? true : false;
            $isFecent = $categoryName === 'Recent products' ? true : false;
            if ($categoryName && !$isFeatured && !$isFecent) {
                $categoryModel = VmModel::getModel('category');
                $records = $categoryModel->getCategoryTree(0, 0, false);
                if ($records) {
                    foreach ($records as $record) {
                        if (strtolower($this->_options['categoryName']) == strtolower($record->category_name)) {
                            $category_id = $record->virtuemart_category_id;
                            $filter_category = true;
                            break;
                        }
                    }
                    if (!$filter_category) {
                        return $products;
                    }
                }
            }

            $product_group = $isFeatured ? 'featured' : 'latest';

            $max_items = 25;
            if (isset($this->_options['count']) && $this->_options['count']) {
                $max_items = $this->_options['count'];
            }

            $show_price = true;
            $filter_manufacturer = false;
            $manufacturer_id = null;

            $productModel::$omitLoaded = false;
            $items = $productModel->getProductListing(
                $product_group,
                $max_items,
                $show_price,
                true,
                false,
                $filter_category,
                $category_id,
                $filter_manufacturer,
                $manufacturer_id
            );
        }

        if (empty($items)) {
            return $products;
        }

        $productModel->addImages($items);

        foreach ($items as $index => $item) {
            if ($index == 0) { // for product details control
                if (!empty($item->customfields)) {
                    $item = $this->_displayProductCustomfieldFE($item);
                }
                $item->withRating = $item->showRating = $ratingModel->showRating($item->virtuemart_product_id);
                if ($item->withRating) {
                    if (!isset($item->rating)) {
                        $ratings = $productModel->getTable('ratings');
                        $ratings->load($item->virtuemart_product_id, 'virtuemart_product_id');
                        if ($ratings->published) {
                            $item->rating = $ratings->rating;
                        }
                    }
                }
                $item->allowReview = $ratingModel->allowReview($item->virtuemart_product_id);
                $item->showReview = $ratingModel->showReview($item->virtuemart_product_id);
                $item->rating_reviews='';
                if ($item->showReview) {
                    $item->review = $ratingModel->getProductReviewForUser($item->virtuemart_product_id);
                    $item->showall = vRequest::getBool('showall', false);
                    if ($item->showall) {
                        $limit = 50;
                    } else {
                        $limit = VmConfig::get('vm_num_ratings_show', 3);
                    }
                    $item->rating_reviews = $ratingModel->getReviews($item->virtuemart_product_id, 0, $limit);
                }
                if ($item->showRating) {
                    $this->vote = $ratingModel->getVoteByProduct($item->virtuemart_product_id);
                }
                $item->allowRating = $ratingModel->allowRating($item->virtuemart_product_id);
                $item->more_reviews = JRoute::_('index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id=' . $item->virtuemart_product_id . '&showall=1');
                $item->user = JFactory::getUser();
            }
            $builder = new ProductDataBuilder($item);
            $product = $builder->getData();
            array_push($products, $product);
        }

        return $products;
    }

    /**
     * Get cart object
     *
     * @return array|null
     */
    public function getCart() {
        if (!$this->_vmInit()) {
            return null;
        }
        $result = array();
        $cart = VirtueMartCart::getCart(false);
        $data = $cart->prepareAjaxData();

        if (isset($data->totalProduct)) {
            $result['count'] = $data->totalProduct;
        }

        $cartLink = $data->cart_show;
        if (preg_match('/(href=[\'"])([\s\S]+?)([\'"])/', $cartLink, $matches)) {
            $result['link'] = $matches[2];
        }
        return $result;
    }

    /**
     * Build variation custom fields
     *
     * @param object $item Product object
     *
     * @return mixed
     */
    private function _displayProductCustomfieldFE($item) {
        /*
        * Set for dynamic content change
        * vRequest::setVar('view','productdetails');
        */
        vRequest::setVar('view', 'productdetails');
        $customfieldsModel = VmModel::getModel('Customfields');
        $customfieldsModel->displayProductCustomfieldFE($item, $item->customfields);

        $stockhandle = VmConfig::get('stockhandle_products', false) && $item->product_stockhandle ? $item->product_stockhandle : VmConfig::get('stockhandle', 'none');
        $extra = ' and ( published = "1" ';
        if ($stockhandle == 'disableit_children') {
            $extra .= ' AND (`product_in_stock` - `product_ordered`) > "0" ';
        }
        $extra .= ')';

        $productModel = VmModel::getModel('product');
        $item->customfieldsSorted = array();

        foreach ($item->customfields as $k => $customfield) {
            if (!empty($customfield->layout_pos)) {
                $item->customfieldsSorted[$customfield->layout_pos][] = $customfield;
            } else {
                $item->customfieldsSorted['normal'][] = $customfield;
            }
            if ($customfield->field_type == 'C' && empty($customfield->selectType)) {
                $avail = $productModel->getProductChildIds($customfield->virtuemart_product_id, $extra);
                if (!in_array($customfield->virtuemart_product_id, $avail)) {
                    array_unshift($avail, $customfield->virtuemart_product_id);
                }
                foreach ($customfield->options as $product_id => $variants) {
                    if (!in_array($product_id, $avail)) {
                        continue;
                    }
                    $getParamPageId = isset($this->_options['pageId']) && $this->_options['pageId'] ? '&pageId=' . $this->_options['pageId'] : '';
                    $url = JRoute::_('index.php?option=com_nicepage&task=product&virtuemart_category_id=' . $item->virtuemart_category_id . '&virtuemart_product_id=' . $product_id . $getParamPageId, false);
                    $jsArray[] = '["' . $url . '","' . implode('","', $variants) . '"]';
                }

                $jsVariants = implode(',', $jsArray);
                $selector = 'select[data-cvsel="field' . $customfield->virtuemart_customfield_id . '"]';
                $hash = md5($selector);
                $j = "jQuery(document).ready(function($) {
                            Virtuemart.setBrowserState = false;
							$('" . $selector . "').off('change', Virtuemart.cvFind);
							$('" . $selector . "').on('change', { variants:[" . $jsVariants . "] }, Virtuemart.cvFind);
						});";
                vmJsApi::addJScript('npvars' . $hash, $j, true, false, false, $hash);
            }
            $scripts = vmJsApi::getJScripts();
            foreach ($scripts as $name => $script) {
                if (strpos($name, 'cvselvars') !== false) { //remove vm scripts for select
                    vmJsApi::removeJScript($name);
                }
            }
        }
        return $item;
    }

    /**
     * Check vm
     *
     * @return bool
     */
    private function _vmInit()
    {
        if (!file_exists(dirname(JPATH_ADMINISTRATOR) . '/components/com_virtuemart/')) {
            return false;
        }

        if (!JComponentHelper::getComponent('com_virtuemart', true)->enabled) {
            return false;
        }

        $vmdefinesPath = JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/vmdefines.php';
        if (!class_exists('vmDefines') && !file_exists($vmdefinesPath)) {
            return false;
        }

        $configPath = JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/config.php';
        if (!class_exists('VmConfig') && !file_exists($configPath)) {
            return false;
        }

        include_once $vmdefinesPath;
        include_once $configPath;

        if (!method_exists('VmConfig', 'loadConfig')) {
            return false;
        }

        if (!method_exists('vmDefines', 'core')) {
            return false;
        }

        VmConfig::loadConfig();
        vmDefines::core(JPATH_ROOT);
        vmLanguage::loadJLang('com_virtuemart', true);

        $document = JFactory::getDocument();

        $scripts = <<<SCRIPT
            <script type="text/javascript">
                if (typeof Virtuemart === "undefined") {
                    var Virtuemart = {};
                }
                jQuery(function ($) {
                    Virtuemart.customUpdateVirtueMartNpCart = function(el, options) {
                        var base 	= this;
                        base.npEl 	= $(".u-shopping-cart");
                        base.options 	= $.extend({}, Virtuemart.customUpdateVirtueMartNpCart.defaults, options);
                        
                        base.init = function() {
                            $.ajaxSetup({cache: false});
                            $.getJSON(Virtuemart.vmSiteurl + "index.php?option=com_virtuemart&nosef=1&view=cart&task=viewJS&format=json" + Virtuemart.vmLang,
                                function (datas, textStatus) {
                                    base.npEl.each(function(index, control) {
                                        $(control).find(".u-shopping-cart-count").html(datas.totalProduct);
                                    });
                                }
                            );
                        };
                        base.init();
                    };
                });
                
                jQuery(document).ready(function( $ ) {
                    $(document).off("updateVirtueMartCartModule", "body", Virtuemart.customUpdateVirtueMartNpCart);
                    $(document).on("updateVirtueMartCartModule", "body", Virtuemart.customUpdateVirtueMartNpCart);
                });
            </script>
SCRIPT;
        $document->addCustomTag($scripts);

        return true;
    }
}