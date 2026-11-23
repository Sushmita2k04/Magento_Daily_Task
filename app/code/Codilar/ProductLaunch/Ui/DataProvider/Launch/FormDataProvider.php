<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Ui\DataProvider\Launch;

use Codilar\ProductLaunch\Model\BannerManager;
use Codilar\ProductLaunch\Model\DateTimeConverter;
use Codilar\ProductLaunch\Model\ResourceModel\Launch\CollectionFactory;
use Codilar\ProductLaunch\Model\Status;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\DataProvider\AbstractDataProvider;

class FormDataProvider extends AbstractDataProvider
{
    private ?array $loadedData = null;

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly BannerManager $bannerManager,
        private readonly DateTimeConverter $dateTimeConverter,
        private readonly Status $status,
        private readonly ProductRepositoryInterface $productRepository,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        foreach ($this->collection->getItems() as $launch) {
            $this->loadedData[$launch->getId()] = $this->prepare($launch->getData());
        }
        $persisted = $this->dataPersistor->get('codilar_productlaunch');
        if (!empty($persisted)) {
            $id = (int)($persisted['launch_id'] ?? 0);
            $this->loadedData[$id ?: ''] = $this->prepare(array_merge($this->loadedData[$id] ?? [], $persisted));
            $this->dataPersistor->clear('codilar_productlaunch');
        }
        if (!$this->loadedData) {
            $this->loadedData[''] = ['is_active' => 1, 'store_ids' => [0]];
        }
        return $this->loadedData;
    }

    private function prepare(array $data): array
    {
        $data['is_active'] = (int)($data['is_active'] ?? 1);
        $data['store_ids'] = $data['store_ids'] ?? [0];
        if (!empty($data['banner']) && is_string($data['banner'])) {
            $url = $this->bannerManager->getUrl($data['banner']);
            $data['banner'] = $url ? [['name' => $data['banner'], 'url' => $url, 'type' => 'image']] : [];
        }
        $productId = (int)($data['product_id'] ?? 0);
        if ($productId) {
            try {
                $product = $this->productRepository->getById($productId);
                $data['product_label'] = sprintf('%s (%s)', $product->getName(), $product->getSku());
                $data['product_sku'] = $product->getSku();
            } catch (NoSuchEntityException) {
                $data['product_label'] = (string)__('Product no longer exists');
            }
        }
        if (!empty($data['start_at'])) {
            try {
                $data['status_label'] = (string)($this->status->getLabels()[
                    $this->status->calculate($data['start_at'], $data['end_at'] ?? null)
                ] ?? '');
            } catch (\InvalidArgumentException) {
                $data['status_label'] = '';
            }
        } else {
            $data['status_label'] = (string)__('Calculated from the start and end date once saved');
        }
        foreach (['created_at', 'updated_at'] as $key) {
            if (!empty($data[$key])) {
                $data[$key . '_label'] = $this->dateTimeConverter->format($data[$key]);
            }
        }
        return $data;
    }
}
