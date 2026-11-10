<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads and sanitises the Build Your Own Box configuration.
 */
class Config
{
    public const BOX_SKU = 'BYOB-GIFT-BOX';
    public const MAX_COFFEE_COUNT = 10;
    public const MAX_MESSAGE_LENGTH = 1000;

    private const PATH = 'codilar_byob/general/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return (bool)$this->value('enabled');
    }

    public function getCoffeeCategoryId(): int
    {
        return $this->positiveInt('coffee_category_id', 0);
    }

    public function getEquipmentCategoryId(): int
    {
        return $this->positiveInt('equipment_category_id', 0);
    }

    public function getCoffeeCount(): int
    {
        return min(self::MAX_COFFEE_COUNT, $this->positiveInt('coffee_count', 3));
    }

    /**
     * Fixed discount; invalid, negative or non-numeric values are treated as zero.
     */
    public function getDiscountAmount(): float
    {
        $value = $this->value('discount_amount');
        if (!is_numeric($value) || (float)$value < 0) {
            return 0.0;
        }
        return round((float)$value, 4);
    }

    public function getMessageMaxLength(): int
    {
        return min(self::MAX_MESSAGE_LENGTH, $this->positiveInt('message_max_length', 200));
    }

    public function getBoxProductId(): int
    {
        return $this->positiveInt('box_product_id', 0);
    }

    /**
     * Returns an error message when the feature cannot work, null when the setup is usable.
     */
    public function getConfigurationError(): ?string
    {
        if (!$this->isEnabled()) {
            return 'Build Your Own Box is currently unavailable.';
        }
        $coffee = $this->getCoffeeCategoryId();
        $equipment = $this->getEquipmentCategoryId();
        if ($coffee === 0 || $equipment === 0 || $coffee === $equipment) {
            return 'Build Your Own Box is not configured correctly.';
        }
        return null;
    }

    private function value(string $field): mixed
    {
        return $this->scopeConfig->getValue(self::PATH . $field, ScopeInterface::SCOPE_STORE);
    }

    private function positiveInt(string $field, int $default): int
    {
        $value = $this->value($field);
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === null || $value === '' || !preg_match('/^\d+$/', (string)$value) || (int)$value < 1) {
            return $default;
        }
        return (int)$value;
    }
}
