<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model;

use Codilar\ProductLaunch\Api\Data\LaunchInterface;
use Codilar\ProductLaunch\Api\Data\LaunchSearchResultsInterface;
use Codilar\ProductLaunch\Api\Data\LaunchSearchResultsInterfaceFactory;
use Codilar\ProductLaunch\Api\LaunchRepositoryInterface;
use Codilar\ProductLaunch\Model\ResourceModel\Launch as ResourceModel;
use Codilar\ProductLaunch\Model\ResourceModel\Launch\CollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filter\FilterManager;

class LaunchRepository implements LaunchRepositoryInterface
{
    public function __construct(
        private readonly ResourceModel $resource,
        private readonly LaunchFactory $launchFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly LaunchSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly LaunchValidator $validator,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly FilterManager $filterManager
    ) {
    }

    public function save(LaunchInterface $launch): LaunchInterface
    {
        $this->validator->assertValid($launch);
        try {
            // SKU is a snapshot of the catalog product, never trusted from input
            if ($launch->getProductId()) {
                $launch->setSku($this->productRepository->getById((int)$launch->getProductId())->getSku());
            }
            if (!$launch->getEndAt()) {
                $launch->setEndAt(null);
            }
            $launch->setTitle(trim((string)$launch->getTitle()));
            $launch->setUrlKey($this->resolveUrlKey($launch));
            /** @var Launch $launch */
            $this->resource->save($launch);
        } catch (ValidatorException | CouldNotSaveException $e) {
            throw $e;
        } catch (AlreadyExistsException) {
            throw new CouldNotSaveException(__('A launch with this URL key already exists. Please change the URL key.'));
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('The launch could not be saved: %1', $e->getMessage()), $e);
        }
        return $launch;
    }

    public function getById(int $launchId): LaunchInterface
    {
        $launch = $this->launchFactory->create();
        $this->resource->load($launch, $launchId);
        if (!$launch->getId()) {
            throw new NoSuchEntityException(__('The launch with ID "%1" does not exist.', $launchId));
        }
        return $launch;
    }

    public function getByUrlKey(string $urlKey): LaunchInterface
    {
        $launch = $this->launchFactory->create();
        $this->resource->load($launch, $urlKey, LaunchInterface::URL_KEY);
        if (!$launch->getId()) {
            throw new NoSuchEntityException(__('The launch "%1" does not exist.', $urlKey));
        }
        return $launch;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): LaunchSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems($collection->getItems());
        $results->setTotalCount($collection->getSize());
        return $results;
    }

    public function delete(LaunchInterface $launch): bool
    {
        try {
            /** @var Launch $launch */
            $this->resource->delete($launch);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the launch: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $launchId): bool
    {
        return $this->delete($this->getById($launchId));
    }

    private function resolveUrlKey(LaunchInterface $launch): string
    {
        $explicit = trim((string)$launch->getUrlKey());
        $excludeId = $launch->getLaunchId() ? (int)$launch->getLaunchId() : null;
        if ($explicit !== '') {
            if ($this->resource->isUrlKeyTaken($explicit, $excludeId)) {
                throw new CouldNotSaveException(__('The URL key "%1" is already used by another launch.', $explicit));
            }
            return $explicit;
        }
        $base = trim((string)$this->filterManager->translitUrl((string)$launch->getTitle()), '-');
        $base = $base !== '' ? substr($base, 0, 200) : 'launch';
        if ($base === 'index') {
            $base = 'launch-index';
        }
        $key = $base;
        $i = 1;
        while ($this->resource->isUrlKeyTaken($key, $excludeId)) {
            $key = $base . '-' . (++$i);
        }
        return $key;
    }
}
