<?php
declare(strict_types=1);

namespace DevScripts\GraphQL\Model\Resolver\Products;

use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

class Identity implements IdentityInterface
{

    private $cacheTag = \Magento\Framework\App\Config::CACHE_TAG;

    /**
     * @param array $resolvedData
     * @return string[]
     */
    public function getIdentities(array $resolvedData)
    {
        $ids =  empty($resolvedData['id']) ?
                        [] : [$this->cacheTag, sprintf('%s_%s', $this->cacheTag, $resolvedData['id'])];
        
                    return $ids;
    }
}

