<?php
/**
 * Package Vegam_HomepageGraphQl
 */
declare(strict_types=1);

namespace Vegam\HomepageGraphQl\Plugin\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\UrlRewriteGraphQl\Model\Resolver\EntityUrl;

class UrlResolverForSku
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $productFactory;
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;
    /**
     * @var PageRepositoryInterface
     */
    private $pageRepository;
    /**
     *
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param PageRepositoryInterface $pageRepository
     */
    
    public function __construct(
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        PageRepositoryInterface $pageRepository
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->pageRepository = $pageRepository;
    }

    /**
     * Afterresolve
     *
     * @param array $subject
     * @param array $result
     * @return array
     */
    public function afterResolve(EntityUrl $subject, $result)
    {
        if (isset($result['id'])) {
            if ($result['type'] == 'PRODUCT') {
                $product = $this->productRepository->getById($result['id']);
                $result['sku'] = $product->getSku();
                $result['meta_title'] = $product->getMetaTitle() ? $product->getMetaTitle() : $product->getName();
                $result['meta_description'] = $product->getMetaDescription();
                $result['meta_keyword'] = $product->getMetaKeyword();
                $result['meta_image'] = $product->getMediaConfig()->getMediaUrl($product->getImage());
            } elseif ($result['type'] == 'CATEGORY') {
                $category = $this->categoryRepository->get($result['id']);
                $result['meta_title'] = $category->getMetaTitle() ? $category->getMetaTitle() : $category->getName();
                $result['meta_description'] = $category->getMetaDescription();
                $result['meta_keyword'] = $category->getMetaKeywords();
            } elseif ($result['type'] == 'CMS_PAGE') {
                $page = $this->pageRepository->getById($result['id']);
                $result['meta_title'] = $page->getMetaTitle();
                $result['meta_description'] = $page->getMetaDescription();
                $result['meta_keyword'] = $page->getMetaKeywords();
            }
        }
        return $result;
    }
}
