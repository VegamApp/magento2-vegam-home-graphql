<?php
/**
 * @package Vegam_HomepageGraphQl
 */
declare(strict_types=1);

namespace Vegam\HomepageGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\UrlInterface;
use Magento\StoreGraphQl\Model\Resolver\Store\StoreConfigDataProvider;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Sales\Model\ResourceModel\Report\Bestsellers\CollectionFactory as BestSellersCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Serialize\SerializerInterface;
use Vegam\Homepage\Api\BlockRepositoryInterface;
use Vegam\Homepage\Model\BlockFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface as CmsPageRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Cms\Api\BlockRepositoryInterface as CmsBlockRepositoryInterface;

class HomepageBlockResolver implements ResolverInterface
{
    /**
     * @var UrlInterface
     */
    private $urlInterface;
    /**
     * @var Curl
     */
    private $curl;
    /**
     * @var StoreConfigDataProvider
     */
    private $storeConfigDataProvider;
    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;
    /**
     * @var BlockRepositoryInterface
     */
    private $blockRepository;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var BlockFactory
     */
    private $blockFactory;
    /**
     * @var BestSellersCollectionFactory
     */
    private $bestSellersCollectionFactory;
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var Visibility
     */
    private $catalogProductVisibility;
    /**
     * @var CmsPageRepositoryInterface
     */
    private $cmsPageRepository;
    /**
     * @var FilterBuilder
     */
    private $filterBuilder;
    /**
     * @var CategoryCollection
     */
    private $categoryCollection;
    /**
     * @var CmsBlockRepositoryInterface
     */
    private $cmsBlockRepository;

    /**
     * StoreConfigResolver constructor.
     *
     * @param StoreConfigDataProvider               $storeConfigsDataProvider
     * @param UrlInterface                          $urlInterface
     * @param BlockFactory                          $blockFactory
     * @param CollectionFactory                     $productCollectionFactory
     * @param CategoryRepository                    $categoryRepository
     * @param ProductRepositoryInterface            $productRepository
     * @param StoreManagerInterface                 $storeManager
     * @param Visibility                            $catalogProductVisibility
     * @param BestSellersCollectionFactory          $bestSellersCollectionFactory
     * @param SearchCriteriaBuilder                 $searchCriteriaBuilder
     * @param SortOrderBuilder                      $sortOrderBuilder
     * @param BlockRepositoryInterface              $blockRepository
     * @param SerializerInterface                   $serializer
     * @param Curl                                  $curl
     * @param CmsPageRepositoryInterface            $cmsPageRepository
     * @param FilterBuilder                         $filterBuilder
     * @param CategoryCollection                    $categoryCollection
     * @param CmsBlockRepositoryInterface           $cmsBlockRepository
     * @param array                                 $data
     */
    public function __construct(
        StoreConfigDataProvider $storeConfigsDataProvider,
        UrlInterface $urlInterface,
        BlockFactory $blockFactory,
        CollectionFactory $productCollectionFactory,
        CategoryRepository $categoryRepository,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        Visibility $catalogProductVisibility,
        BestSellersCollectionFactory $bestSellersCollectionFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        BlockRepositoryInterface $blockRepository,
        SerializerInterface $serializer,
        Curl $curl,
        CmsPageRepositoryInterface $cmsPageRepository,
        FilterBuilder $filterBuilder,
        CategoryCollection $categoryCollection,
        CmsBlockRepositoryInterface $cmsBlockRepository,
        array $data = []
    ) {

        $this->storeConfigDataProvider = $storeConfigsDataProvider;
        $this->urlInterface = $urlInterface;
        $this->curl = $curl;
        $this->blockFactory = $blockFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->catalogProductVisibility = $catalogProductVisibility;
        $this->bestSellersCollectionFactory = $bestSellersCollectionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->blockRepository = $blockRepository;
        $this->serializer = $serializer;
        $this->cmsPageRepository = $cmsPageRepository;
        $this->filterBuilder = $filterBuilder;
        $this->categoryCollection = $categoryCollection;
        $this->cmsBlockRepository = $cmsBlockRepository;
    }
    /**
     * Resolve
     *
     * @param Field $field
     * @param Context $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $store = $context->getExtensionAttributes()->getStore();
        $storeId = (int)$store->getId();
        $this->validateInput($args);
        $sortOrder = $this->sortOrderBuilder->setField('position')->setDirection('ASC')->create();
        $searchCriteria = $this->searchCriteriaBuilder->setCurrentPage($args['currentPage'])
                        ->setPageSize($args['pageSize'])->setSortOrders([$sortOrder]);
        if ($storeId) {
            $filter[] = $this->filterBuilder->setField('store')
            ->setValue($storeId)
            ->setConditionType("finset")
            ->create();
        }
        $filter[] = $this->filterBuilder->setField('store')
            ->setValue(0)
            ->setConditionType("finset")
            ->create();
        if (isset($args['filter']['mobile_status'])) {
            $data['block_filter'] = $args['filter']['mobile_status']['eq'];
            $this->searchCriteriaBuilder->addFilter('mobile_status', $data['block_filter']);
        }
        if (isset($args['filter']['desktop_status'])) {
            $data['block_filter1'] = $args['filter']['desktop_status']['eq'];
            $this->searchCriteriaBuilder->addFilter('status', $data['block_filter1']);
        }
        $this->searchCriteriaBuilder->addFilter('block_status', 1);
        $this->searchCriteriaBuilder->addFilters($filter);
        $searchCriteria = $searchCriteria->create();
        $searchResult = $this->blockRepository->getList($searchCriteria);
        if ($searchResult->getTotalCount() > 0) {
            $values = $searchResult->getItems();
            $response = [];
            $maxPages = ceil($searchResult->getTotalCount() / $args['pageSize']);
            if ($args['currentPage'] > $maxPages) {
                throw new GraphQlInputException(
                    __(
                        'currentPage value %1 specified is greater than the number of pages available.',
                        $maxPages
                    )
                );
            }
            foreach ($values as $item) {
                $type = $item->getType();
                if ($type == '') {
                    continue;
                }
                $productValue = [];
                $productCount = $item->getProductCount();
                if ($productType = $item->getProductType()) {
                    if ($productType == 1) {
                        $productValue = $this->getBestsellerCollection($productCount);
                    } elseif ($productType == 2) {
                        if ($catId = $item->getCategoryId()) {
                            $productValue = $this->getProductWithCategory($catId, $productCount);
                        }
                    } elseif ($productType == 3) {
                        $productValue = $this->getNewestProduct($productCount);
                    } elseif ($productType == 4) {
                        $productValue = $this->getFeaturedCollection($productCount);
                    } elseif ($productType == 5) {
                        $productValue = $this->getCustomCollection($item);
                    } elseif ($productType == 6) {
                        $productValue = $this->getMostpopularCollection($productCount);
                    }
                }

                $productData = [];
                foreach ($productValue as $product) {
                    $productData[] = [
                        'name'      => $product->getName(),
                        'type_id'   => $product->getTypeId(),
                        'sku'       => $product->getSku(),
                        'url_key'   => $product->getUrlKey(),
                        'model'     => $product,
                        'entity_id' => $product->getId()
                    ];
                }
                $bannerItems = [];
                $banner = $item->getBannerSerialized();
                $bannerTemplate = $item->getBannerTemplate();
                if (!empty($banner)) {
                    $bannerWidth = 12;
                    if ($item->getSliderWidth()) {
                        $bannerWidth -= $item->getSliderWidth();
                    }
                    $bannerItems = $this->formatBannerInfo($banner, $bannerWidth, $bannerTemplate);
                }
                $response[] = [
                    'id' => $item->getId(),
                    'typename' => $type,
                    'name' => $item->getName(),
                    'status' => $item->getStatus(),
                    'title' => $item->getName(),
                    'desktop_status' => $item->getStatus(),
                    'mobile_status' => $item->getMobileStatus(),
                    'store' => $item->getStore(),
                    'show_title' => $item->getShowTitle(),
                    'store' => $item->getStore(),
                    'banner_template' => $item->getBannerTemplate(),
                    'banners' => $bannerItems,
                    'description' => $item->getDescription(),
                    'layout' => $item->getLayout(),
                    'banneritems' => $bannerItems,
                    'product_type' => $item->getProductType(),
                    'display_style' => $item->getDisplayStyle(),
                    'viewall_status' => $item->getViewallStatus(),
                    'products' => $productData,
                    'category_info' => $this->getCategoryCollection($item)
                ];
            }
            return [
                'data' => $response,
                'total_count' => $searchResult->getTotalCount(),
                'page_info' => [
                    'page_size' => $args['pageSize'],
                    'current_page' => $args['currentPage'],
                    'total_pages' => $maxPages
                ],
            ];
        }
    }

    /**
     * FormatBannerInfo
     *
     * @param string $banner
     * @param int $bannerWidth
     * @param string $bannerTemplate
     *
     * @return array|null
     */
    public function formatBannerInfo($banner, $bannerWidth, $bannerTemplate)
    {
        $bannerItems = null;
        $bannerUnserialize = $this->serializer->unserialize($banner);
        if (is_array($bannerUnserialize)) {
            $sortArray = [];
            foreach ($bannerUnserialize as $key => $row) {
                $sortArray[$key] = $row['position'];
            }
            array_multisort($sortArray, SORT_ASC, $bannerUnserialize);
            foreach ($bannerUnserialize as $bannerItem) {
                $Image = $bannerItem['image'][0]['url'];
                $imageUrl = $this->getMediaUrl($Image);
                $url = '';
                $linkinfoArray = [];
                if (isset($bannerItem['link']['type'])) {
                    $linkinfoArray['link_type'] = $bannerItem['link']['type'];
                    $linkinfoArray['open_tab'] = $bannerItem['link']['setting'];
                    if ($bannerItem['link']['type'] == 'default') {
                        $linkinfoArray['external_url'] = $bannerItem['link']['default'];
                        $url = $bannerItem['link']['default'];
                    } elseif ($bannerItem['link']['type'] == 'product') {
                        $productId = isset($bannerItem['link']['product']) ?
                            $bannerItem['link']['product'] : null;
                        $product = $this->getProduct($productId);
                        if ($product) {
                            $linkinfoArray['product_id'] = $productId;
                            $linkinfoArray['product_sku'] = $product->getSku();
                            $linkinfoArray['link_url']  = $product->getUrlKey();
                        }
                    } elseif ($bannerItem['link']['type'] == 'category') {
                        $categoryId = isset($bannerItem['link']['category']) ?
                            $bannerItem['link']['category'] : null;
                        $url = $this->getCategoryUrl($categoryId);
                        if ($url) {
                            $linkinfoArray['category_id'] = $categoryId;
                            $linkinfoArray['link_url'] = $url;
                        }
                    } elseif ($bannerItem['link']['type'] == 'page') {
                        $pageId = isset($bannerItem['link']['page']) ?
                                $bannerItem['link']['page'] : null;
                        $url = $this->getPageUrl($pageId);
                        if ($url) {
                            $linkinfoArray['page_id'] = $pageId;
                            $linkinfoArray['link_url'] = $url;
                        }
                    }
                }
                if (isset($bannerItem['title'])) {
                    $title = $bannerItem['title'];
                }
                if ($bannerTemplate == 'without_title') {
                    $title = null;
                }
                $bannerItems[] = [
                    'image' => $imageUrl,
                    'title' => isset($title) ? $title : null,
                    'link' => $url,
                    'link_info' => $linkinfoArray,
                    'layout' => isset($bannerItem['layout']) ?
                        $bannerItem['layout'] * 2 : $bannerWidth,
                    'position' => $bannerItem['position']
                ];
            }
        }
        return $bannerItems;
    }

    /**
     * Validate input arguments
     *
     * @param array $args
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     */
    private function validateInput(array $args)
    {

        if (isset($args['`currentPage`']) && $args['currentPage'] < 1) {
            throw new GraphQlInputException(__('currentPage value must be greater than 0.'));
        }
        if (isset($args['pageSize']) && $args['pageSize'] < 1) {
            throw new GraphQlInputException(__('pageSize value must be greater than 0.'));
        }
    }

    /**
     * Get media url
     *
     * @param string $imagePath
     * @return string
     */
    public function getMediaUrl($imagePath)
    {
        $imageUrl = null;
        if ($imagePath) {
            $imageUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $imagePath;
        }
        return $imageUrl;
    }

    /**
     * Get product with category
     *
     * @param string $catId
     * @param string $productCount
     * @return string
     */
    public function getProductWithCategory($catId, $productCount)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('status', 1);
        $collection->addCategoriesFilter(['in' => $catId]);
        $collection->setPageSize($productCount);
        $collection->addAttributeToFilter('visibility', ['in' => [2, 4]]);
        return $collection;
    }

    /**
     * Get product with category url
     *
     * @param strin $catId
     * @return string
     */
    public function getCategoryUrl($catId)
    {
        try {
            $category = $this->categoryRepository->get($catId, $this->storeManager->getStore()->getId());
            $url = explode($this->storeManager->getStore()->getBaseUrl(), $category->getUrl());
            return $url[1];
        } catch (NoSuchEntityException $e) {
            $url[1] = null;
        }
    }

    /**
     * Get product
     *
     * @param string $productId
     * @return string
     */
    public function getProduct($productId)
    {
        try {
            $product = $this->productRepository->getById($productId);
               
        } catch (NoSuchEntityException $e) {
            $product = null;
        }
        return $product;
    }

    /**
     * Get page with page url
     *
     * @param strin $pageId
     * @return string
     */
    public function getPageUrl($pageId)
    {
        try {
            $pageUrl = $this->cmsPageRepository->getById($pageId);
            return $pageUrl->getIdentifier();
        } catch (NoSuchEntityException $e) {
            $pageUrl = null;
        }
        return $pageUrl;
    }

    /**
     * Get Cms Block
     *
     * @param string $blockId
     * @return string
     */
    public function getCmsBlock($blockId)
    {
        try {
            $blockIdentifier = $this->cmsBlockRepository->getById($blockId);
            return $blockIdentifier->getIdentifier();
        } catch (NoSuchEntityException $e) {
            $blockIdentifier = null;
        }
        return $blockIdentifier;
    }

    /**
     * Get new product
     *
     * @return string
     * @param string $productCount
     */
    public function getNewestProduct($productCount)
    {
        $todayDate  = date('Y-m-d', time());
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', '1')
            ->addAttributeToFilter('visibility', ['in' => [2, 4]])
            ->addAttributeToSort('created_at', 'desc')
            ->setPageSize($productCount);
        return $collection;
    }

    /**
     * Get  bestseller product
     *
     * @return string
     * @param string $productCount
     */
    public function getBestsellerCollection($productCount)
    {
        $productIds = [];
        $bestSellers = $this->bestSellersCollectionFactory->create()
            ->setPeriod('month');
        foreach ($bestSellers as $product) {
            $productIds[] = $product->getProductId();
        }
        $collection = $this->productCollectionFactory->create()->addIdFilter($productIds);
        $collection->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('visibility', ['in' => [2, 3, 4]])
            ->setPageSize($productCount);
        return $collection;
    }

    /**
     * Get featured product
     *
     * @return string
     * @param string $productCount
     */
    public function getFeaturedCollection($productCount)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('is_featured', '1')
            ->addAttributeToFilter('visibility', ['in' => [2, 4]])
            ->setPageSize($productCount);
        return $collection;
    }

    /**
     * Get product block
     *
     * @param array $items
     * @return array
     */
    public function getCustomCollection($items)
    {
        $results = [];
        $pIds = $items->getProductIds();
        if ($pIds) {
            $unserializedIds = $this->serializer->unserialize($pIds);
            $unserializedids = array_keys($unserializedIds);
            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('status', 1)
                ->addAttributeToFilter('entity_id', ['in' => $unserializedids])
                ->addAttributeToFilter('visibility', ['in' => [2, 4]])
                ->setPageSize(10);
            return $collection;
        }
    }

    /**
     * Get most popular product
     *
     * @param string $productCount
     * @return string
     */
    public function getMostpopularCollection($productCount)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('most_popular_product', '1')
            ->addAttributeToFilter('visibility', ['in' => [2, 4]])
            ->setPageSize($productCount);
        return $collection;
    }

    /**
     * Get category block
     *
     * @param array $item
     * @return array
     */
    public function getCategoryCollection($item)
    {
        $categoryData = [];
        $catIds = $item->getCategoryId();
        if ($catIds) {
            $catId = explode(",", $catIds);
            $collection = $this->categoryCollection->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('entity_id', ['in' => $catId]);
            foreach ($collection as $catData) {
                $imagePath = null;
                if ($catData->getData('vegam_category_thumbnail')) {
                    $baseUrl = $this->storeManager->getStore()->getBaseUrl();
                    $baseUrl = substr_replace($baseUrl, "", -1);
                    $imagePath = $baseUrl.$catData->getData('vegam_category_thumbnail');
                }
                $categoryData[] = [
                    'category_id' => $catData->getId(),
                    'name' => $catData->getName(),
                    'image'  => $imagePath,
                    'url_path' => $catData->getUrlPath(),
                    'url_key'  => $catData->getUrlKey()
                ];
            }
            return $categoryData;
        }
    }
}
