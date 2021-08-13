<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

namespace NP\Processor;

defined('_JEXEC') or die;

use NP\Utility\Pagination;
use NP\Utility\GridHelper;
use NP\Models\ContentModelCustomArticles;
use \JLoader, \JFactory;

JLoader::register('Nicepage_Data_Mappers', JPATH_ADMINISTRATOR . '/components/com_nicepage/tables/mappers.php');

class BlogProcessor
{
    private $_posts = array();
    private $_post = array();
    private $_pageId;

    private $_blogList = array();
    private $_blogPosition = 0;
    private $_paginationProps = null;

    private $_metaDataType = '';

    /**
     * BlogProcessor constructor.
     *
     * @param string $pageId Page id
     */
    public function __construct($pageId = '')
    {
        $this->_pageId = $pageId;
    }

    /**
     * Process blog
     *
     * @param string $content Content
     *
     * @return string|string[]|null
     */
    public function process($content) {
        $content = preg_replace_callback('/<\!--blog-->([\s\S]+?)<\!--\/blog-->/', array(&$this, '_processBlog'), $content);
        $content = preg_replace_callback('/<\!--post_details-->([\s\S]+?)<\!--\/post_details-->/', array(&$this, '_processPost'), $content);

        if (strpos($content, 'none-post-image') !== false) {
            $content = str_replace('u-blog-post', 'u-blog-post u-invisible', $content);
        }
        return $content;
    }

    /**
     * Process one blog
     *
     * @param string $content Content
     *
     * @return int
     */
    public function processBlogByAjaxLoad($content) {
        preg_replace_callback('/<\!--blog-->([\s\S]+?)<\!--\/blog-->/', array(&$this, '_processBlog'), $content);
        $position = JFactory::getApplication()->input->get('position', 1);
        $result = array_slice($this->_blogList, $position - 1, 1);
        return count($result) > 0 ? $result[0] : 0;
    }

    /**
     * Process product
     *
     * @param array $postMatch Matches
     *
     * @return string|string[]|null
     */
    private function _processPost($postMatch) {
        $postHtml = $postMatch[1];

        $postOptions = array();
        if (preg_match('/<\!--post_details_options_json--><\!--([\s\S]+?)--><\!--\/post_details_options_json-->/', $postHtml, $matches)) {
            $postOptions = json_decode($matches[1], true);
            $postHtml = str_replace($matches[0], '', $postHtml);
        }
        $blogSource = '';
        if (isset($postOptions['source']) && $postOptions['source']) {
            $blogSource = 'postId:' . $postOptions['source'];
        }
        $this->_posts = $this->_getBlogPosts($blogSource);
        $postHtml = preg_replace_callback('/<\!--blog_post-->([\s\S]+?)<\!--\/blog_post-->/', array(&$this, '_processBlogPost'), $postHtml);
        return $postHtml;
    }

    /**
     * Process blog
     *
     * @param array $blogMatch Matches
     *
     * @return string|string[]|null
     */
    private function _processBlog($blogMatch) {
        $blogHtml = $blogMatch[1];
        $blogOptions = array();
        $this->_paginationProps = null;
        $this->_blogPosition += 1;
        if (preg_match('/<\!--blog_options_json--><\!--([\s\S]+?)--><\!--\/blog_options_json-->/', $blogHtml, $matches)) {
            $blogOptions = json_decode($matches[1], true);
            $blogHtml = str_replace($matches[0], '', $blogHtml);
        }
        $blogSourceType = isset($blogOptions['type']) ? $blogOptions['type'] : '';
        if ($blogSourceType === 'Tags') {
            $blogSource = 'tags:' . (isset($blogOptions['tags']) && $blogOptions['tags'] ? $blogOptions['tags'] : '');
        } else {
            $blogSource = isset($blogOptions['source']) && $blogOptions['source'] ? $blogOptions['source'] : '';
        }
        $blogPostCount = isset($blogOptions['count']) ? (int) $blogOptions['count'] : '';
        $posts = $this->_getBlogPosts($blogSource);

        if ($blogPostCount && count($posts) > $blogPostCount) {
            $app = JFactory::getApplication();
            $limitstart = $app->input->get('offset', 0);
            $pageId = $app->input->get('pageId', $this->_pageId);
            $positionOnPage = $app->input->get('position', $this->_blogPosition);
            $this->_paginationProps = array(
                'allPosts' => count($posts),
                'offset' => (int) $limitstart,
                'postsPerPage' => $blogPostCount,
                'pageId' => (int) $pageId,
                'positionOnPage' => $positionOnPage,
                'task' => 'blogposts',
            );
            $this->_posts = array_slice($posts, $limitstart, $blogPostCount);
        } else {
            $this->_posts = $posts;
        }
        $postsCount = count($this->_posts);
        $blogHtml = preg_replace_callback('/<\!--blog_post-->([\s\S]+?)<\!--\/blog_post-->/', array(&$this, '_processBlogPost'), $blogHtml);
        $blogHtml = preg_replace_callback('/<\!--blog_pagination-->([\s\S]+?)<\!--\/blog_pagination-->/', array(&$this, '_processBlogPagination'), $blogHtml);

        $blogGridProps = isset($blogOptions['gridProps']) ? $blogOptions['gridProps'] : array();
        $blogHtml .= GridHelper::buildGridAutoRowsStyles($blogGridProps, $postsCount);

        array_push($this->_blogList, $blogHtml);
        return $blogHtml;
    }

    /**
     * Process pagination
     *
     * @param array $paginationMatch Matches
     *
     * @return false|mixed|string
     */
    private function _processBlogPagination($paginationMatch) {
        if (!$this->_paginationProps) {
            return '';
        }
        $paginationHtml = $paginationMatch[1];
        $paginationStyleOptions = array();
        if (preg_match('/<\!--blog_pagination_options_json--><\!--([\s\S]+?)--><\!--\/blog_pagination_options_json-->/', $paginationHtml, $matches)) {
            $paginationStyleOptions = json_decode($matches[1], true);
        }
        $pagination = new Pagination($this->_paginationProps, $paginationStyleOptions);
        return $pagination->getPagination();
    }

    /**
     * Process post
     *
     * @param array $postMatch Matches
     *
     * @return mixed|string|string[]|null
     */
    private function _processBlogPost($postMatch) {
        $postHtml = $postMatch[1];

        if (count($this->_posts) < 1) {
            return ''; // remove cell, if post is missing
        }

        $result = '';
        while (count($this->_posts) > 0) {
            $this->_post = array_shift($this->_posts);
            $newPostHtml = preg_replace_callback('/<\!--blog_post_header-->([\s\S]+?)<\!--\/blog_post_header-->/', array(&$this, '_setHeaderData'), $postHtml);
            $newPostHtml = preg_replace_callback('/<\!--blog_post_content-->([\s\S]+?)<\!--\/blog_post_content-->/', array(&$this, '_setContentData'), $newPostHtml);
            $newPostHtml = preg_replace_callback('/<\!--blog_post_image-->([\s\S]+?)<\!--\/blog_post_image-->/', array(&$this, '_setImageData'), $newPostHtml);
            $newPostHtml = preg_replace_callback('/<\!--blog_post_readmore-->([\s\S]+?)<\!--\/blog_post_readmore-->/', array(&$this, '_setReadmoreData'), $newPostHtml);
            $newPostHtml = preg_replace_callback('/<\!--blog_post_metadata-->([\s\S]+?)<\!--\/blog_post_metadata-->/', array(&$this, '_setMetadataData'), $newPostHtml);
            $newPostHtml = preg_replace_callback('/<\!--blog_post_tags-->([\s\S]+?)<\!--\/blog_post_tags-->/', array(&$this, '_setTagsData'), $newPostHtml);
            $result .= $newPostHtml;
        }
        return $result;
    }

    /**
     * Get blog post by source
     *
     * @param string $source Source
     * @param int    $count  Post count
     *
     * @return array
     */
    private function _getBlogPosts($source, $count = null) {
        $posts = array();
        $categoryId = '';
        $tags = '';
        $postId = '';
        if ($source) {
            if (preg_match('/^postId:/', $source)) {
                $postId = str_replace('postId:', '', $source);
            } else if (preg_match('/^tags:/', $source)) {
                $tags = str_replace('tags:', '', $source);
            } else {
                $categoryObject = \Nicepage_Data_Mappers::get('category');
                $categoryList = $categoryObject->find(array('title' => $source));
                if (count($categoryList) < 1) {
                    return $posts;
                }
                $categoryId = $categoryList[0]->id;
            }
        }
        // Get recent articles, if $categoryId is empty
        $blog = new ContentModelCustomArticles(array('category_id' => $categoryId, 'tags' => $tags, 'count' => $count, 'postId' => $postId));
        return $blog->getPosts($postId ? 'post' : 'blog');
    }

    /**
     * Set header
     *
     * @param string $headerMatch Header match
     *
     * @return mixed|string|string[]|null
     */
    private function _setHeaderData($headerMatch) {
        $headerHtml = $headerMatch[1];
        $headerHtml = preg_replace_callback(
            '/<\!--blog_post_header_content-->([\s\S]+?)<\!--\/blog_post_header_content-->/',
            function ($headerContentMatch) {
                return isset($this->_post['post-header']) ? $this->_post['post-header'] : $headerContentMatch[1];
            },
            $headerHtml
        );
        $headerLink = isset($this->_post['post-header-link']) ? $this->_post['post-header-link'] : '#';
        $headerHtml = preg_replace('/(href=[\'"])([\s\S]+?)([\'"])/', '$1' . $headerLink . '$3', $headerHtml);
        return $headerHtml;
    }

    /**
     * Set content
     *
     * @param string $contentMatch Content match
     *
     * @return mixed|string|string[]|null
     */
    private function _setContentData($contentMatch) {
        $contentHtml = $contentMatch[1];
        $contentHtml = preg_replace_callback(
            '/<\!--blog_post_content_content-->([\s\S]+?)<\!--\/blog_post_content_content-->/',
            function ($contentMatch) {
                return isset($this->_post['post-content']) ? $this->_post['post-content'] : $contentMatch[1];
            },
            $contentHtml
        );
        return $contentHtml;
    }

    /**
     * Set image
     *
     * @param string $imageMatch Image match
     *
     * @return mixed
     */
    private function _setImageData($imageMatch) {
        $imageHtml = $imageMatch[1];
        $isBackgroundImage = strpos($imageHtml, '<div') !== false ? true : false;

        $src = isset($this->_post['post-image'])? $this->_post['post-image'] : '';
        if (!$src) {
            return '<div class="none-post-image" style="display: none;"></div>';
        }

        if ($isBackgroundImage) {
            if (strpos($imageHtml, 'data-bg') !== false) {
                $imageHtml = preg_replace('/(data-bg=[\'"])([\s\S]+?)([\'"])/', '$1url(' . $this->_post['post-image'] . ')$3', $imageHtml);
            } else {
                $imageHtml = str_replace('<div', '<div' . ' style="background-image:url(' . $this->_post['post-image'] . ')"', $imageHtml);
            }
        } else {
            $imageHtml = preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $this->_post['post-image'] . '$3', $imageHtml);
        }
        return $imageHtml;
    }

    /**
     * Set readmore
     *
     * @param string $readmoreMatch Readmre match
     *
     * @return mixed|string|string[]|null
     */
    private function _setReadmoreData($readmoreMatch) {
        $readmoreHtml = $readmoreMatch[1];
        $readmoreHtml = preg_replace_callback(
            '/<\!--blog_post_readmore_content-->([\s\S]+?)<\!--\/blog_post_readmore_content-->/',
            function ($readmoreContentMatch) {
                return isset($this->_post['post-readmore-text']) ? $this->_post['post-readmore-text'] : $readmoreContentMatch[1];
            },
            $readmoreHtml
        );
        $readmoreLink = isset($this->_post['post-readmore-link']) ? $this->_post['post-readmore-link'] : '#';
        $readmoreHtml = preg_replace('/(href=[\'"])([\s\S]+?)([\'"])/', '$1' . $readmoreLink . '$3', $readmoreHtml);
        return $readmoreHtml;
    }

    /**
     * Set metadata
     *
     * @param string $metadataMatch Metadata match
     *
     * @return mixed|string|string[]|null
     */
    private function _setMetadataData($metadataMatch) {
        $metadataHtml = $metadataMatch[1];
        $metaDataTypes = array('date', 'author', 'category', 'comments', 'edit');
        foreach ($metaDataTypes as $type) {
            $this->_metaDataType = $type;
            $metadataHtml = preg_replace_callback(
                '/<\!--blog_post_metadata_' . $this->_metaDataType . '-->([\s\S]+?)<\!--\/blog_post_metadata_' . $this->_metaDataType . '-->/',
                function ($metadataTypeMatch) {
                    return $metadataTypeMatch[1];
                },
                $metadataHtml
            );
            $metadataHtml = preg_replace_callback(
                '/<\!--blog_post_metadata_' . $this->_metaDataType . '_content-->([\s\S]+?)<\!--\/blog_post_metadata_' . $this->_metaDataType . '_content-->/',
                function ($metadataTypeContentMatch) {
                    return isset($this->_post['post-metadata-' . $this->_metaDataType]) ? $this->_post['post-metadata-' . $this->_metaDataType] : $metadataTypeContentMatch[1];
                },
                $metadataHtml
            );
        }
        return $metadataHtml;
    }

    /**
     * Set tags
     *
     * @param string $tagsMatch tags match
     *
     * @return mixed|string|string[]|null
     */
    private function _setTagsData($tagsMatch) {
        $tagsHtml = $tagsMatch[1];
        $tagsHtml = preg_replace_callback(
            '/<\!--blog_post_tags_content-->([\s\S]+?)<\!--\/blog_post_tags_content-->/',
            function ($contentTagsMatch) {
                return isset($this->_post['post-tags']) ? $this->_post['post-tags'] : $contentTagsMatch[1];
            },
            $tagsHtml
        );
        return $tagsHtml;
    }
}