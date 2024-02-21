<?php
/**
 * ViewAll Resolver
 *
 * @package Vegam_HomepageGraphQl
 */
declare(strict_types=1);

namespace Vegam\HomepageGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Vegam\Homepage\Api\BlockRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Report\Bestsellers\CollectionFactory as BestSellersCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\CatalogGraphQl\Model\Resolver\Products\Query\ProductQueryInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class ViewAllProductResolver implements ResolverInterface
{
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var BlockRepositoryInterface
     */
    private $blockRepository;

    /**
     * @var BestSellersCollectionFactory
     */
    private $bestSellersCollectionFactory;

    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ProductQueryInterface
     */
    private $searchQuery;

    /**
     * StoreConfigResolver constructor.
     *
     * @param SearchCriteriaBuilder         $searchCriteriaBuilder
     * @param BlockRepositoryInterface      $blockRepository
     * @param BestSellersCollectionFactory  $bestSellersCollectionFactory
     * @param CollectionFactory             $productCollectionFactory
     * @param SerializerInterface           $serializer
     * @param ProductQueryInterface         $searchQuery
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        BlockRepositoryInterface $blockRepository,
        BestSellersCollectionFactory $bestSellersCollectionFactory,
        CollectionFactory $productCollectionFactory,
        SerializerInterface $serializer,
        ProductQueryInterface $searchQuery
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->blockRepository = $blockRepository;
        $this->bestSellersCollectionFactory = $bestSellersCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->serializer = $serializer;
        $this->searchQuery = $searchQuery;
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
        $skus = [];
        if (empty($args['input']['Id'])) {
            throw new GraphQlInputException(__('Required parameter "Id" is missing'));
        }
        $store = $context->getExtensionAttributes()->getStore();
        $storeId = (int)$store->getId();
        $id = $args['input']['Id'];
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('design_id', $id, 'eq');
        $searchCriteria = $searchCriteria->create();
        $searchResult = $this->blockRepository->getList($searchCriteria);
        $values = $searchResult->getItems();
        foreach ($values as $item) {
            $productType = $item->getProductType();
            $catId = $item->getCategoryId();
            $productIds = $item->getProductIds();
        }
        if ($productType == 1) {
            $productValue = $this->getBestsellerCollection($storeId);
        } elseif ($productType == 2) {
            $productValue = $this->getProductWithCategory($catId, $storeId);
        } elseif ($productType == 3) {
            $productValue = $this->getNewestProduct($storeId);
        } elseif ($productType == 4) {
            $productValue = $this->getFeaturedCollection($storeId);
        } elseif ($productType == 5) {
            $productValue = $this->getCustomCollection($productIds, $storeId);
        } elseif ($productType == 6) {
            $productValue = $this->getMostpopularCollection($storeId);
        }
        $productData = [];
        foreach ($productValue as $product) {
            $productData[ $product->getSku()] = [
                        'name'      => $product->getName(),
                        'type_id'   => $product->getTypeId(),
                        'sku'       => $product->getSku(),
                        'url_key'   => $product->getUrlKey(),
                        'model'     => $product,
                        'entity_id' => $product->getId()
                    ];
            $skus[] = $product->getSku();
        }
        if (empty($skus)) {
            throw new GraphQlInputException(
                __(
                    'No items found'
                )
            );
        }
        $args['filter'] =  ['sku' => ['in' => $skus]];
        $searchResult = $this->searchQuery->getResult($args, $info, $context);

        if ($searchResult->getCurrentPage() > $searchResult->getTotalPages() && $searchResult->getTotalCount() > 0) {
            throw new GraphQlInputException(
                __(
                    'currentPage value %1 specified is greater than the %2 page(s) available.',
                    [$searchResult->getCurrentPage(), $searchResult->getTotalPages()]
                )
            );
        }
        $productItems = $searchResult->getProductsSearchResult();
        $productArray = [];
        foreach ($productItems as $key => $value) {
            $product = $value['model'];
            $productArray[] = $productData[$product->getSku()];
        }
        $data = [
            'total_count' => $searchResult->getTotalCount(),
            'products' => $productArray,
            'items' => $searchResult->getProductsSearchResult(),
            'suggestions' => $searchResult->getSuggestions(),
            'page_info' => [
                'page_size' => $searchResult->getPageSize(),
                'current_page' => $searchResult->getCurrentPage(),
                'total_pages' => $searchResult->getTotalPages()
            ],
            'search_result' => $searchResult,
            'layer_type' => isset($args['search']) ? Resolver::CATALOG_LAYER_SEARCH : Resolver::CATALOG_LAYER_CATEGORY
        ];
        return $data;
    }

    /**
     * Get  Bestseller Product
     *
     * @param int $storeId
     * @return string
     */
    public function getBestsellerCollection($storeId)
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
            ->addStoreFilter($storeId);
            return $collection;
    }

    /**
     * Get Product with Category
     *
     * @param string $catId
     * @param int $storeId
     * @return string
     */
    public function getProductWithCategory($catId, $storeId)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*')
                    ->addCategoriesFilter(['in' => $catId])
                    ->addAttributeToFilter('status', 1)
                    ->addAttributeToFilter('visibility', ['in' => [2, 4]])
                    ->addStoreFilter($storeId);
        return $collection;
    }

    /**
     * Get New Product
     *
     * @param int $storeId
     * @return string
     */
    public function getNewestProduct($storeId)
    {
        $todayDate  = date('Y-m-d', time());
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('visibility', ['in' => [2, 4]])
            ->addAttributeToSort('created_at', 'desc')
            ->addStoreFilter($storeId);
        return $collection;
    }

    /**
     * Get Featured Product
     *
     * @param int $storeId
     * @return string
     */
    public function getFeaturedCollection($storeId)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('is_featured', '1')
            ->addAttributeToFilter('visibility', ['in' => [2, 4]])
            ->addStoreFilter($storeId);
            return $collection;
    }

    /**
     * Get Product Block
     *
     * @param string $productIds
     * @param int $storeId
     * @return array
     */
    public function getCustomCollection($productIds, $storeId)
    {
        $results = [];
        if ($productIds) {
            $unserializedIds = $this->serializer->unserialize($productIds);
            $unserializedids = array_keys($unserializedIds);
            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('status', 1)
                ->addAttributeToFilter('entity_id', ['in' => $unserializedids])
                ->addAttributeToFilter('visibility', ['in' => [2, 4]])
                ->addStoreFilter($storeId);
            return $collection;
        }
    }

    /**
     * Get Most Popular Product
     *
     * @param int $storeId
     * @return string
     */
    public function getMostpopularCollection($storeId)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('most_popular_product', '1')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('visibility', ['in' => [2, 4]])
            ->addStoreFilter($storeId);
        return $collection;
    }
}
