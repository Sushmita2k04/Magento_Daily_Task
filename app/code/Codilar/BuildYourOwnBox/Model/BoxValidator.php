<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Central server-side validation of a box selection. Never trusts prices, discounts or categories
 * supplied by the client: everything is re-read from configuration and the catalog.
 */
class BoxValidator
{
    public function __construct(
        private readonly Config $config,
        private readonly ProductProvider $productProvider,
        private readonly PriceCalculator $priceCalculator,
        private readonly StoreManagerInterface $storeManager,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @param array $input raw request data: coffee (list of ids), equipment (id|empty), message (string)
     * @throws LocalizedException
     */
    public function assemble(array $input): AssembledBox
    {
        if ($error = $this->config->getConfigurationError()) {
            throw new LocalizedException(__($error));
        }
        $count = $this->config->getCoffeeCount();
        $coffeeCategory = $this->config->getCoffeeCategoryId();
        $equipmentCategory = $this->config->getEquipmentCategoryId();

        $coffeeIds = $this->parseIds($input['coffee'] ?? null);
        $equipmentId = $this->parseEquipment($input['equipment'] ?? null);
        $message = $this->normalizeMessage($input['message'] ?? '');

        if (count($coffeeIds) !== $count) {
            throw new LocalizedException(__('Please select exactly %1 coffee products.', $count));
        }
        if (count(array_unique($coffeeIds)) !== count($coffeeIds)) {
            throw new LocalizedException(__('The same coffee cannot be selected more than once.'));
        }

        $coffee = $this->loadComponents($coffeeCategory, $coffeeIds, 'coffee');
        $equipment = $equipmentId === null ? [] : $this->loadComponents($equipmentCategory, [$equipmentId], 'equipment');

        return $this->build($coffeeIds, $equipmentId, $message, $coffee, $equipment, [
            'configured_discount' => $this->config->getDiscountAmount(),
            'coffee_count' => $count,
            'coffee_category_id' => $coffeeCategory,
            'equipment_category_id' => $equipmentCategory,
            'message_max_length' => $this->config->getMessageMaxLength(),
        ]);
    }

    /**
     * Re-checks an existing box against the current catalog (disabled, removed from its category,
     * deleted, unsalable). Uses the categories recorded in the box snapshot.
     *
     * @return \Magento\Catalog\Model\Product[] keyed by id
     * @throws LocalizedException
     */
    public function revalidate(BoxComposition $composition): array
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('Build Your Own Box is currently unavailable.'));
        }
        $coffee = $this->loadComponents($composition->getCoffeeCategoryId(), $composition->getCoffeeIds(), 'coffee');
        $equipmentId = $composition->getEquipmentId();
        $equipment = $equipmentId === null
            ? []
            : $this->loadComponents($composition->getEquipmentCategoryId(), [$equipmentId], 'equipment');

        $products = [];
        foreach ($coffee + $equipment as $id => $row) {
            $products[$id] = $row['product'];
        }
        return $products;
    }

    /**
     * Gift message: string, unicode-aware length, no HTML tags, control characters stripped.
     */
    public function normalizeMessage(mixed $message): string
    {
        if ($message === null) {
            return '';
        }
        if (!is_string($message)) {
            throw new LocalizedException(__('Invalid gift message.'));
        }
        if (!mb_check_encoding($message, 'UTF-8')) {
            throw new LocalizedException(__('Invalid gift message.'));
        }
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = (string)preg_replace('/[^\P{C}\n]/u', '', $message);
        $message = trim($message);

        // Some stock renderers (e.g. the order email) print option values unescaped, so angle brackets are refused.
        if (preg_match('/[<>]/u', $message)) {
            throw new LocalizedException(__('The gift message cannot contain HTML (the < and > characters).'));
        }
        $max = $this->config->getMessageMaxLength();
        if (mb_strlen($message, 'UTF-8') > $max) {
            throw new LocalizedException(__('The gift message cannot be longer than %1 characters.', $max));
        }
        return $message;
    }

    /**
     * @return int[]
     */
    private function parseIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new LocalizedException(__('Invalid coffee selection.'));
        }
        $ids = [];
        foreach ($raw as $value) {
            $ids[] = $this->toId($value);
        }
        return $ids;
    }

    private function parseEquipment(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }
        if (is_array($raw)) {
            throw new LocalizedException(__('Only one equipment item can be selected.'));
        }
        return $this->toId($raw);
    }

    private function toId(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9]\d{0,9}$/', (string)$value)) {
            throw new LocalizedException(__('Invalid product selection.'));
        }
        return (int)$value;
    }

    /**
     * @param int[] $ids
     * @return array<int, array{product: \Magento\Catalog\Model\Product, salable: bool, price: float}>
     */
    private function loadComponents(int $categoryId, array $ids, string $kind): array
    {
        $rows = $this->productProvider->getProducts($categoryId, $ids);
        foreach ($ids as $id) {
            if (!isset($rows[$id])) {
                throw new LocalizedException(
                    __('One of the selected %1 items is not available for this box.', $kind)
                );
            }
            $row = $rows[$id];
            if (!$row['salable']) {
                throw new LocalizedException(__('"%1" is currently out of stock.', $row['product']->getName()));
            }
            if ($row['price'] <= 0) {
                throw new LocalizedException(__('"%1" cannot be added to a box.', $row['product']->getName()));
            }
        }
        return $rows;
    }

    private function build(
        array $coffeeIds,
        ?int $equipmentId,
        string $message,
        array $coffee,
        array $equipment,
        array $snapshot
    ): AssembledBox {
        $describe = static fn(array $row): array => [
            'id' => (int)$row['product']->getId(),
            'sku' => (string)$row['product']->getSku(),
            'name' => (string)$row['product']->getName(),
            'price' => $row['price'],
            'weight' => (float)$row['product']->getWeight(),
        ];

        $coffeeRows = array_map(static fn(int $id): array => $coffee[$id], $coffeeIds);
        $coffeeData = array_map($describe, $coffeeRows);
        $equipmentRow = $equipmentId === null ? null : $equipment[$equipmentId];
        $equipmentData = $equipmentRow === null ? null : $describe($equipmentRow);

        $totals = $this->priceCalculator->calculate(
            array_column($coffeeData, 'price'),
            $equipmentData['price'] ?? null,
            $snapshot['configured_discount']
        );
        if ($totals['final_price'] < 0) {
            throw new LocalizedException(__('The box price is invalid.'));
        }
        $weight = array_sum(array_column($coffeeData, 'weight')) + (float)($equipmentData['weight'] ?? 0);

        $composition = BoxComposition::fromArray([
            'version' => BoxComposition::VERSION,
            'coffee' => $coffeeData,
            'equipment' => $equipmentData,
            'gift_message' => $message,
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'final_price' => $totals['final_price'],
            'currency' => $this->storeManager->getStore()->getBaseCurrencyCode(),
            'weight' => $weight,
            'snapshot' => $snapshot,
            'assembled_at' => $this->dateTime->gmtDate('c'),
        ]);

        $products = [];
        foreach ($coffeeRows as $row) {
            $products[(int)$row['product']->getId()] = $row['product'];
        }
        if ($equipmentRow !== null) {
            $products[(int)$equipmentRow['product']->getId()] = $equipmentRow['product'];
        }
        return new AssembledBox($composition, $products);
    }
}
