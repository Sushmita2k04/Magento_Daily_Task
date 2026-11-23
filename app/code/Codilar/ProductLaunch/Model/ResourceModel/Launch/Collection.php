<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model\ResourceModel\Launch;

use Codilar\ProductLaunch\Api\Data\LaunchInterface;
use Codilar\ProductLaunch\Model\Launch as Model;
use Codilar\ProductLaunch\Model\ResourceModel\Launch as ResourceModel;
use Codilar\ProductLaunch\Model\Status;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = LaunchInterface::LAUNCH_ID;

    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }

    /**
     * Only launches visible in the given store view (or "all store views" = 0).
     */
    public function addStoreFilter(int $storeId): self
    {
        $this->getSelect()->join(
            ['store_link' => $this->getTable(ResourceModel::STORE_TABLE)],
            'main_table.launch_id = store_link.launch_id AND store_link.store_id IN (0, ' . (int)$storeId . ')',
            []
        )->group('main_table.launch_id');
        return $this;
    }

    public function addActiveFilter(): self
    {
        return $this->addFieldToFilter(LaunchInterface::IS_ACTIVE, 1);
    }

    /**
     * Filter by calculated status relative to $nowUtc (Y-m-d H:i:s, UTC).
     */
    public function addStatusFilter(string $status, string $nowUtc): self
    {
        switch ($status) {
            case Status::UPCOMING:
                $this->addFieldToFilter(LaunchInterface::START_AT, ['gt' => $nowUtc]);
                break;
            case Status::LIVE:
                $this->addFieldToFilter(LaunchInterface::START_AT, ['lteq' => $nowUtc]);
                $this->getSelect()->where('(main_table.end_at IS NULL OR main_table.end_at > ?)', $nowUtc);
                break;
            case Status::COMPLETED:
                $this->getSelect()->where('main_table.end_at IS NOT NULL AND main_table.end_at <= ?', $nowUtc);
                break;
        }
        return $this;
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();
        $ids = array_map('intval', $this->getColumnValues(LaunchInterface::LAUNCH_ID));
        if ($ids) {
            $connection = $this->getConnection();
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($this->getTable(ResourceModel::STORE_TABLE))
                    ->where('launch_id IN (?)', $ids)
            );
            $map = [];
            foreach ($rows as $row) {
                $map[(int)$row['launch_id']][] = (int)$row['store_id'];
            }
            foreach ($this as $item) {
                $item->setData(LaunchInterface::STORE_IDS, $map[(int)$item->getId()] ?? [0]);
            }
        }
        return $this;
    }

    public function getSelectCountSql()
    {
        $select = parent::getSelectCountSql();
        // store filter groups by launch_id; count distinct instead
        if (in_array('store_link', array_keys($this->getSelect()->getPart(\Magento\Framework\DB\Select::FROM)), true)) {
            $select->reset(\Magento\Framework\DB\Select::GROUP);
            $select->reset(\Magento\Framework\DB\Select::COLUMNS);
            $select->columns('COUNT(DISTINCT main_table.launch_id)');
        }
        return $select;
    }
}
