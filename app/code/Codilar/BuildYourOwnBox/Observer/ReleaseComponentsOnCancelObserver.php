<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Observer;

use Codilar\BuildYourOwnBox\Model\Product\Type\Box;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventExtensionFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;

/**
 * Releases the component reservations of a box when its order item is cancelled.
 */
class ReleaseComponentsOnCancelObserver implements ObserverInterface
{
    public function __construct(
        private readonly ItemToSellInterfaceFactory $itemToSellFactory,
        private readonly SalesEventInterfaceFactory $salesEventFactory,
        private readonly SalesEventExtensionFactory $salesEventExtensionFactory,
        private readonly SalesChannelInterfaceFactory $salesChannelFactory,
        private readonly WebsiteRepositoryInterface $websiteRepository,
        private readonly PlaceReservationsForSalesEventInterface $placeReservations
    ) {
    }

    public function execute(Observer $observer): void
    {
        $orderItem = $observer->getEvent()->getItem();
        if ($orderItem->getProductType() !== Box::TYPE_CODE) {
            return;
        }
        $composition = $orderItem->getProductOptions()['byob_composition'] ?? null;
        $qty = (float)$orderItem->getQtyToCancel();
        if (!is_array($composition) || $qty <= 0) {
            return;
        }
        $components = $composition['coffee'] ?? [];
        if (!empty($composition['equipment'])) {
            $components[] = $composition['equipment'];
        }
        $itemsToSell = [];
        foreach ($components as $component) {
            $itemsToSell[] = $this->itemToSellFactory->create(['sku' => (string)$component['sku'], 'qty' => $qty]);
        }
        if (!$itemsToSell) {
            return;
        }

        $order = $orderItem->getOrder();
        $websiteCode = $this->websiteRepository->getById((int)$orderItem->getStore()->getWebsiteId())->getCode();
        $salesChannel = $this->salesChannelFactory->create([
            'data' => ['type' => SalesChannelInterface::TYPE_WEBSITE, 'code' => $websiteCode],
        ]);
        $salesEvent = $this->salesEventFactory->create([
            'type' => SalesEventInterface::EVENT_ORDER_CANCELED,
            'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
            'objectId' => (string)$order->getEntityId(),
        ]);
        $salesEvent->setExtensionAttributes($this->salesEventExtensionFactory->create([
            'data' => ['objectIncrementId' => (string)$order->getIncrementId()],
        ]));
        $this->placeReservations->execute($itemsToSell, $salesChannel, $salesEvent);
    }
}
