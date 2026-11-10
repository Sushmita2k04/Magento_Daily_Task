<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Model;

use InvalidArgumentException;

/**
 * Immutable, versioned snapshot of an assembled gift box.
 *
 * Everything needed to display the box later (names, SKUs, prices, discount, config) is stored,
 * so historical orders never depend on the current catalog or configuration.
 */
class BoxComposition
{
    public const VERSION = 1;

    private function __construct(private readonly array $data)
    {
    }

    public static function fromArray(array $data): self
    {
        if ((int)($data['version'] ?? 0) !== self::VERSION) {
            throw new InvalidArgumentException('Unsupported box composition version.');
        }
        if (!isset($data['coffee']) || !is_array($data['coffee']) || !$data['coffee']
            || !isset($data['final_price'], $data['subtotal'], $data['discount'])
        ) {
            throw new InvalidArgumentException('Malformed box composition.');
        }
        return new self($data);
    }

    /**
     * Reads the composition stored as a custom option on a (quote-prepared) box product.
     */
    public static function fromProduct(\Magento\Catalog\Model\Product $product): ?self
    {
        $option = $product->getCustomOption('byob_composition');
        if (!$option || !is_string($option->getValue())) {
            return null;
        }
        try {
            $data = json_decode($option->getValue(), true, 16, JSON_THROW_ON_ERROR);
            return is_array($data) ? self::fromArray($data) : null;
        } catch (\JsonException | InvalidArgumentException) {
            return null;
        }
    }

    public function toArray(): array
    {
        return $this->data;
    }

    /** @return int[] */
    public function getCoffeeIds(): array
    {
        return array_map(static fn(array $c): int => (int)$c['id'], $this->data['coffee']);
    }

    public function getEquipmentId(): ?int
    {
        return isset($this->data['equipment']['id']) ? (int)$this->data['equipment']['id'] : null;
    }

    public function getFinalPrice(): float
    {
        return (float)$this->data['final_price'];
    }

    public function getWeight(): float
    {
        return (float)($this->data['weight'] ?? 0);
    }

    public function getCoffeeCategoryId(): int
    {
        return (int)($this->data['snapshot']['coffee_category_id'] ?? 0);
    }

    public function getEquipmentCategoryId(): int
    {
        return (int)($this->data['snapshot']['equipment_category_id'] ?? 0);
    }

    /** Stable fingerprint, used to tell different boxes apart. */
    public function getHash(): string
    {
        $ids = $this->getCoffeeIds();
        sort($ids);
        return hash('sha256', json_encode([$ids, $this->getEquipmentId(), $this->data['gift_message'] ?? '']));
    }
}
