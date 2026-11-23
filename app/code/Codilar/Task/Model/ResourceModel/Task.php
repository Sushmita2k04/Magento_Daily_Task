<?php
declare(strict_types=1);

namespace Codilar\Task\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model: talks to the `codilar_task` table (SQL lives here).
 */
class Task extends AbstractDb
{
    public const TABLE_NAME = 'codilar_task';
    public const ID_FIELD = 'task_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        // _init(<table name>, <primary key column>)
        $this->_init(self::TABLE_NAME, self::ID_FIELD);
    }
}
