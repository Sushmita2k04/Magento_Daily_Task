<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Model\Product\Type;

use Codilar\BuildYourOwnBox\Model\AdditionalOptionsBuilder;
use Codilar\BuildYourOwnBox\Model\BoxComposition;
use Codilar\BuildYourOwnBox\Model\BoxValidator;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Psr\Log\LoggerInterface;

/**
 * "Build Your Own Box" product type: one purchasable cart line whose coffee/equipment
 * components are validated (including MSI stock) through quote-item qty options.
 */
class Box extends AbstractType
{
    public const TYPE_CODE = 'byob_box';

    public function __construct(
        Option $catalogProductOption,
        EavConfig $eavConfig,
        Type $catalogProductType,
        ManagerInterface $eventManager,
        Database $fileStorageDb,
        Filesystem $filesystem,
        Registry $coreRegistry,
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        private readonly BoxValidator $validator,
        private readonly AdditionalOptionsBuilder $optionsBuilder,
        Json $serializer
    ) {
        parent::__construct(
            $catalogProductOption,
            $eavConfig,
            $catalogProductType,
            $eventManager,
            $fileStorageDb,
            $filesystem,
            $coreRegistry,
            $logger,
            $productRepository,
            $serializer
        );
    }

    /**
     * Stock of the components is validated separately, so the box itself only needs to be enabled.
     */
    public function isSalable($product)
    {
        return (int)$product->getStatus() === Status::STATUS_ENABLED;
    }

    public function getWeight($product)
    {
        $composition = BoxComposition::fromProduct($product);
        return $composition ? $composition->getWeight() : 0.0;
    }

    public function hasWeight()
    {
        return true;
    }

    public function canConfigure($product)
    {
        return false;
    }

    public function isPossibleBuyFromList($product)
    {
        return false;
    }

    public function deleteTypeSpecificData(\Magento\Catalog\Model\Product $product)
    {
    }

    /**
     * Assembles and validates the box on the server; the client only supplies product ids and a message.
     */
    protected function _prepareProduct(DataObject $buyRequest, $product, $processMode)
    {
        $selection = $buyRequest->getData('byob_selection');
        if (!is_array($selection)) {
            return (string)__('Please assemble your box on the Build Your Own Box page.');
        }

        try {
            $assembled = $this->validator->assemble($selection);
        } catch (LocalizedException $e) {
            return $e->getMessage();
        }

        $buyRequest->setQty(1);
        $buyRequest->unsetData('custom_price');
        $buyRequest->unsetData('original_custom_price');
        $buyRequest->setData('byob_selection', [
            'coffee' => $assembled->composition->getCoffeeIds(),
            'equipment' => $assembled->composition->getEquipmentId(),
            'message' => $assembled->composition->toArray()['gift_message'],
        ]);

        $result = parent::_prepareProduct($buyRequest, $product, $processMode);
        if (is_string($result)) {
            return $result;
        }

        $composition = $assembled->composition;
        $product->addCustomOption('byob_composition', json_encode($composition->toArray(), JSON_THROW_ON_ERROR));
        $product->addCustomOption(
            'additional_options',
            json_encode($this->optionsBuilder->build($composition), JSON_THROW_ON_ERROR)
        );
        // Qty options make Magento's own quote-item stock validation (MSI) check every component.
        foreach ($assembled->products as $id => $component) {
            $product->addCustomOption('product_qty_' . $id, 1, $component);
        }
        return $result;
    }

    public function getOrderOptions($product)
    {
        $options = parent::getOrderOptions($product);
        $composition = BoxComposition::fromProduct($product);
        if ($composition) {
            $options['byob_composition'] = $composition->toArray();
            // Order, invoice, credit memo and email renderers display the stored label/value rows.
            $options['additional_options'] = $this->optionsBuilder->build($composition);
        }
        return $options;
    }
}
