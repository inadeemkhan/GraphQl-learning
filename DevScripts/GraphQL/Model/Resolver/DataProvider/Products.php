<?php
declare(strict_types=1);

namespace DevScripts\GraphQL\Model\Resolver\DataProvider;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\CatalogInventory\Api\Data\StockStatusInterface;
use Magento\CatalogInventory\Api\StockStatusRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Provides the product data used by the custom `products` GraphQL query.
 */
class Products
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var StockStatusRepositoryInterface
     */
    private $stockStatusRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StockStatusRepositoryInterface $stockStatusRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StockStatusRepositoryInterface $stockStatusRepository,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->stockStatusRepository = $stockStatusRepository;
        $this->logger = $logger;
    }

    /**
     * Return the product data matching the passed sku/id arguments.
     *
     * @param array $args
     * @return array
     */
    public function getProducts(array $args = []): array
    {
        $sku = $this->extractSku($args);
        if ($sku !== null && $sku !== '') {
            return $this->getBySku($sku);
        }

        if (!empty($args['id'])) {
            return $this->getById((int) $args['id']);
        }
        return $this->getFirstProduct();
    }

    /**
     * Extract the requested SKU from the supported argument shapes.
     *
     * The `products` field is stitched together with the core catalog field, so the SKU can
     * arrive either as a direct argument or inside the `filter` input:
     *
     *  - { products(sku: "24-MB01") { ... } }
     *  - { products(filter: { sku: { eq: "24-MB01" } }) { ... } }
     *
     * @param array $args
     * @return string|null
     */
    private function extractSku(array $args): ?string
    {
        if (!empty($args['sku']) && is_scalar($args['sku'])) {
            return (string) $args['sku'];
        }

        $filterSku = $args['filter']['sku'] ?? null;

        if (is_string($filterSku)) {
            return $filterSku;
        }

        if (is_array($filterSku) && !empty($filterSku['eq']) && is_scalar($filterSku['eq'])) {
            return (string) $filterSku['eq'];
        }

        return null;
    }

    /**
     * @param string $sku
     * @return array
     */
    private function getBySku(string $sku): array
    {
        try {
            return $this->formatProduct($this->productRepository->get($sku));
        } catch (NoSuchEntityException $e) {
            return [];
        }
    }

    /**
     * @param int $id
     * @return array
     */
    private function getById(int $id): array
    {
        try {
            return $this->formatProduct($this->productRepository->getById($id));
        } catch (NoSuchEntityException $e) {
            return [];
        }
    }

    /**
     * Fallback used when neither sku nor id is provided.
     *
     * @return array
     */
    private function getFirstProduct(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->setPageSize(1)
            ->create();
        $items = $this->productRepository->getList($searchCriteria)->getItems();

        $product = reset($items);

        return $product instanceof ProductInterface ? $this->formatProduct($product) : [];
    }

    /**
     * @param ProductInterface $product
     * @return array
     */
    private function formatProduct(ProductInterface $product): array
    {
        $stockStatus = $this->loadStockStatus((int) $product->getId());

        return [
            'id' => (string) $product->getId(),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'qty' => $stockStatus !== null ? (float) $stockStatus->getQty() : 0.0,
            'status' => (int) $product->getStatus() === Status::STATUS_ENABLED ? 'ENABLED' : 'DISABLED',
            'stock_status' => $this->resolveStockStatus($stockStatus),
        ];
    }

    /**
     * Load the stock status of the product.
     *
     * Uses the same stock API as the core GraphQL `stock_status` resolver
     * (Magento\CatalogInventoryGraphQl\Model\Resolver\StockStatusProvider).
     *
     * @param int $productId
     * @return StockStatusInterface|null
     */
    private function loadStockStatus(int $productId): ?StockStatusInterface
    {
        try {
            return $this->stockStatusRepository->get($productId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Map the stock status of the stock item to the GraphQL ProductStockStatus enum
     * (`IN_STOCK` / `OUT_OF_STOCK`) declared by Magento_CatalogInventoryGraphQl.
     *
     * @param StockStatusInterface|null $stockStatus
     * @return string
     */
    private function resolveStockStatus(?StockStatusInterface $stockStatus): string
    {
        return $stockStatus !== null
            && (int) $stockStatus->getStockStatus() === StockStatusInterface::STATUS_IN_STOCK
                ? 'IN_STOCK'
                : 'OUT_OF_STOCK';
    }
}

