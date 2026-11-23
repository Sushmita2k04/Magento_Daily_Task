<?php
declare(strict_types=1);

namespace Codilar\Task\Block\Task;

use Codilar\Task\Model\Task;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;

/**
 * Storefront task list block. All data comes from the TaskList view model; this class only
 * declares the cache tag so the full-page cache is cleared whenever a task changes.
 */
class ListTask extends Template implements IdentityInterface
{
    /**
     * @inheritDoc
     */
    public function getIdentities()
    {
        return [Task::CACHE_TAG];
    }
}
