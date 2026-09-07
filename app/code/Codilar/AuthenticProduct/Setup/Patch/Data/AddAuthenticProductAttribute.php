<?php

namespace Codilar\AuthenticProduct\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddAuthenticProductAttribute implements DataPatchInterface
{
    private EavSetupFactory $eavSetupFactory;

    /**
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply()
    {
        $eavSetup = $this->eavSetupFactory->create();

        $eavSetup->addAttribute(
            Product::ENTITY,
            'is_authentic',
            [
                'type' => 'int',
                'label' => 'Enable Authentic Product',
                'input' => 'boolean',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,

                'required' => false,
                'default' => 1,

                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,

                'visible' => true,
                'user_defined' => true,

                'searchable' => false,
                'filterable' => false,
                'comparable' => false,

                'visible_on_front' => false,
                'used_in_product_listing' => true,

                'unique' => false,

                'group' => 'Product Details'
            ]
        );
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
