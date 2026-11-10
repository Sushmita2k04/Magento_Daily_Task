<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Plugin;

use Codilar\BuildYourOwnBox\Model\Product\Type\Box;
use Magento\InventorySales\Model\AppendReservations;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;

/**
 * The box product itself has no stock, so the components are reserved through Magento's MSI reservation
 * service when the order is placed. The reservation service validates salability for the requested quantity
 * (atomically, under the order-placement lock) and the order fails if any component is gone.
 */
class ReserveComponentsPlugin
{
    public function __construct(
        private readonly AppendReservations $appendReservations,
        private readonly ItemToSellInterfaceFactory $itemToSellFactory,
        private readonly SalesEventInterfaceFactory $salesEventFactory,
        private readonly PlaceReservationsForSalesEventInterface $placeReservations
    ) {
    }

    public function aroundPlace(OrderManagementInterface $subject, callable $proceed, OrderInterface $order)
    {
        $itemsBySku = [];
        foreach ($order->getItems() as $item) {
            if ($item->getProductType() !== Box::TYPE_CODE) {
                continue;
            }
            $composition = $item->getProductOptions()['byob_composition'] ?? null;
            if (!is_array($composition)) {
                continue;
            }
            $components = $composition['coffee'] ?? [];
            if (!empty($composition['equipment'])) {
                $components[] = $composition['equipment'];
            }
            foreach ($components as $component) {
                $sku = (string)$component['sku'];
                $itemsBySku[$sku] = ($itemsBySku[$sku] ?? 0.0) + (float)$item->getQtyOrdered();
            }
        }
        if (!$itemsBySku) {
            return $proceed($order);
        }

        $itemsToSell = [];
        foreach ($itemsBySku as $sku => $qty) {
            $itemsToSell[] = $this->itemToSellFactory->create(['sku' => (string)$sku, 'qty' => -$qty]);
        }
        [$salesChannel, $salesEventExtension] = $this->appendReservations->reserve(
            (int)$order->getStore()->getWebsiteId(),
            $itemsBySku,
            $order,
            $itemsToSell
        );

        try {
            return $proceed($order);
        } catch (\Throwable $e) {
            foreach ($itemsToSell as $itemToSell) {
                $itemToSell->setQuantity(-(float)$itemToSell->getQuantity());
            }
            $salesEvent = $this->salesEventFactory->create([
                'type' => SalesEventInterface::EVENT_ORDER_PLACE_FAILED,
                'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
                'objectId' => (string)$order->getEntityId(),
            ]);
            $salesEvent->setExtensionAttributes($salesEventExtension);
            $this->placeReservations->execute($itemsToSell, $salesChannel, $salesEvent);
            throw $e;
        }
    }
}
