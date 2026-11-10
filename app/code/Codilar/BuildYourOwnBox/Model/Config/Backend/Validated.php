<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Model\Config\Backend;

use Codilar\BuildYourOwnBox\Model\Config;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Validates every Build Your Own Box setting on save and refreshes the page cache
 * (the storefront page embeds the discount and limits).
 */
class Validated extends Value
{

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly CategoryRepositoryInterface $categoryRepository,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        $value = trim((string)$this->getValue());
        $field = substr((string)$this->getPath(), strlen('codilar_byob/general/'));
        $label = (string)__($this->getData('field_config/label') ?: $field);

        switch ($field) {
            case 'coffee_category_id':
            case 'equipment_category_id':
                if ($value !== '' && !$this->isPositiveInt($value)) {
                    throw new LocalizedException(__('%1 must be a positive whole number.', $label));
                }
                if ($value !== '') {
                    $this->assertCategoryExists((int)$value, $label);
                }
                break;
            case 'coffee_count':
                $this->assertRange($value, 1, Config::MAX_COFFEE_COUNT, $label);
                break;
            case 'message_max_length':
                $this->assertRange($value, 1, Config::MAX_MESSAGE_LENGTH, $label);
                break;
            case 'discount_amount':
                if ($value !== '' && (!is_numeric($value) || (float)$value < 0)) {
                    throw new LocalizedException(__('%1 must be a number greater than or equal to 0.', $label));
                }
                break;
            case 'box_product_id':
                if ($value !== '' && !$this->isPositiveInt($value)) {
                    throw new LocalizedException(__('%1 must be a positive whole number.', $label));
                }
                break;
        }
        $this->setValue($value);
        return parent::beforeSave();
    }

    public function afterSave()
    {
        if ($this->isValueChanged()) {
            $this->cacheTypeList->cleanType('full_page');
        }
        return parent::afterSave();
    }

    private function isPositiveInt(string $value): bool
    {
        return (bool)preg_match('/^\d+$/', $value) && (int)$value > 0;
    }

    private function assertRange(string $value, int $min, int $max, string $label): void
    {
        if ($value === '') {
            return;
        }
        if (!preg_match('/^\d+$/', $value) || (int)$value < $min || (int)$value > $max) {
            throw new LocalizedException(__('%1 must be a whole number between %2 and %3.', $label, $min, $max));
        }
    }

    private function assertCategoryExists(int $id, string $label): void
    {
        try {
            $this->categoryRepository->get($id);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('%1: category with ID %2 does not exist.', $label, $id));
        }
    }
}
