<?php
/**
 * @package Vegam_HomepageGraphQl
 */
declare(strict_types=1);

namespace Vegam\HomepageGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Query\Resolver\TypeResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

/**
 * {@inheritdoc}
 */
class HomeBlockTypeResolver implements TypeResolverInterface
{
    /**
     * @inheritdoc
     *
     * @throws GraphQlInputException
     */
    public function resolveType(array $data): string
    {
        return $data['typename'];
    }
}
