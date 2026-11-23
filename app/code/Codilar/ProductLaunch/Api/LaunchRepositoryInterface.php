<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Api;

use Codilar\ProductLaunch\Api\Data\LaunchInterface;
use Codilar\ProductLaunch\Api\Data\LaunchSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface LaunchRepositoryInterface
{
    /**
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\ValidatorException
     */
    public function save(LaunchInterface $launch): LaunchInterface;

    /** @throws \Magento\Framework\Exception\NoSuchEntityException */
    public function getById(int $launchId): LaunchInterface;

    /** @throws \Magento\Framework\Exception\NoSuchEntityException */
    public function getByUrlKey(string $urlKey): LaunchInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): LaunchSearchResultsInterface;

    /** @throws \Magento\Framework\Exception\CouldNotDeleteException */
    public function delete(LaunchInterface $launch): bool;

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $launchId): bool;
}
