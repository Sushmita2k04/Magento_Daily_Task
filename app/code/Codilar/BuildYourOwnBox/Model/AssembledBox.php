<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Model;

use Magento\Catalog\Model\Product;

/**
 * A validated box: the composition snapshot plus the loaded component products (keyed by id).
 */
class AssembledBox
{
    /**
     * @param Product[] $products
     */
    public function __construct(
        public readonly BoxComposition $composition,
        public readonly array $products
    ) {
    }
}
