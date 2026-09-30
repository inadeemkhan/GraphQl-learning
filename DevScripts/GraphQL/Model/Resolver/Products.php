<?php
declare(strict_types=1);

namespace DevScripts\GraphQL\Model\Resolver;

use DevScripts\GraphQL\Model\Resolver\DataProvider\Products as ProductsDataProvider;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Psr\Log\LoggerInterface;

/**
 * Resolver for the custom `products` GraphQL query field.
 */
class Products implements ResolverInterface
{
    /**
     * @var ProductsDataProvider
     */
    private $productsDataProvider;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ProductsDataProvider $productsDataProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductsDataProvider $productsDataProvider,
        LoggerInterface $logger
    ) {
        $this->productsDataProvider = $productsDataProvider;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        return $this->productsDataProvider->getProducts($args ?? []);
    }
}