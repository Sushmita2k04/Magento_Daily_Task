<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface LaunchSearchResultsInterface extends SearchResultsInterface
{
    /** @return \Codilar\ProductLaunch\Api\Data\LaunchInterface[] */
    public function getItems();

    /** @param \Codilar\ProductLaunch\Api\Data\LaunchInterface[] $items */
    public function setItems(array $items);
}
