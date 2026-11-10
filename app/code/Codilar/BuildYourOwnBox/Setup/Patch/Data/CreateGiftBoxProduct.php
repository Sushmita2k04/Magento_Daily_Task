<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Setup\Patch\Data;

use Codilar\BuildYourOwnBox\Model\Config;
use Codilar\BuildYourOwnBox\Model\Product\Type\Box;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Makes the product-level attributes the box needs applicable to the new type and creates the single
 * placeholder product that every assembled box is added to the cart through. Additive only.
 */
class CreateGiftBoxProduct implements DataPatchInterface
{
    private const ATTRIBUTES = ['price', 'weight', 'tax_class_id'];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly ProductFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $appState
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        foreach (self::ATTRIBUTES as $code) {
            $current = (string)$eavSetup->getAttribute(Product::ENTITY, $code, 'apply_to');
            $types = $current === '' ? [] : explode(',', $current);
            // An empty apply_to already means "all types".
            if ($types && !in_array(Box::TYPE_CODE, $types, true)) {
                $types[] = Box::TYPE_CODE;
                $eavSetup->updateAttribute(Product::ENTITY, $code, 'apply_to', implode(',', $types));
            }
        }

        $this->appState->emulateAreaCode('adminhtml', function (): void {
            $this->createProduct();
        });

        $this->moduleDataSetup->getConnection()->endSetup();
        return $this;
    }

    private function createProduct(): void
    {
        try {
            $this->productRepository->get(Config::BOX_SKU);
            return;
        } catch (NoSuchEntityException) {
            // Not created yet.
        }

        $websiteIds = array_map(
            static fn($website): int => (int)$website->getId(),
            $this->storeManager->getWebsites()
        );

        $product = $this->productFactory->create();
        $product->setTypeId(Box::TYPE_CODE)
            ->setAttributeSetId((int)$product->getDefaultAttributeSetId())
            ->setSku(Config::BOX_SKU)
            ->setName('Build Your Own Gift Box')
            ->setUrlKey('build-your-own-gift-box-item')
            ->setPrice(0)
            ->setWeight(0)
            ->setTaxClassId(2)
            ->setStatus(Status::STATUS_ENABLED)
            ->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE)
            ->setWebsiteIds($websiteIds)
            ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1]);
        $this->productRepository->save($product);
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
