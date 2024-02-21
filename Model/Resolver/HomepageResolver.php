<?php
/**
 * Homepage Resolver
 *
 * @package Vegam_HomepageGraphQl
 */
declare(strict_types=1);

namespace Vegam\HomepageGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class HomepageResolver implements ResolverInterface
{
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
        
        return ['blocks'];
    }
}
