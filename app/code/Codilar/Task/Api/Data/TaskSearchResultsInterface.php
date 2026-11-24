<?php
declare(strict_types=1);

namespace Codilar\Task\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Result of a task search: the matching tasks plus the criteria and total count.
 *
 * @api
 */
interface TaskSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get tasks.
     *
     * @return \Codilar\Task\Api\Data\TaskInterface[]
     */
    public function getItems();

    /**
     * Set tasks.
     *
     * @param \Codilar\Task\Api\Data\TaskInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
