<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model\ResourceModel;

use Codilar\ProductLaunch\Api\Data\LaunchInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Launch extends AbstractDb
{
    public const MAIN_TABLE = 'codilar_product_launch';
    public const STORE_TABLE = 'codilar_product_launch_store';

    protected function _construct()
    {
        $this->_init(self::MAIN_TABLE, LaunchInterface::LAUNCH_ID);
    }

    /**
     * @return int[]
     */
    public function lookupStoreIds(int $launchId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::STORE_TABLE), 'store_id')
            ->where('launch_id = ?', $launchId);
        return array_map('intval', $connection->fetchCol($select));
    }

    public function isUrlKeyTaken(string $urlKey, ?int $excludeLaunchId = null): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'launch_id')
            ->where('url_key = ?', $urlKey);
        if ($excludeLaunchId) {
            $select->where('launch_id <> ?', $excludeLaunchId);
        }
        return (bool)$connection->fetchOne($select);
    }

    protected function _afterLoad(AbstractModel $object)
    {
        if ($object->getId()) {
            $object->setData(LaunchInterface::STORE_IDS, $this->lookupStoreIds((int)$object->getId()));
        }
        return parent::_afterLoad($object);
    }

    protected function _afterSave(AbstractModel $object)
    {
        $this->saveStoreLinks($object);
        return parent::_afterSave($object);
    }

    private function saveStoreLinks(AbstractModel $object): void
    {
        /** @var \Codilar\ProductLaunch\Model\Launch $object */
        $new = $object->getStoreIds();
        if (in_array(0, $new, true)) {
            $new = [0];
        }
        $old = $this->lookupStoreIds((int)$object->getId());
        $toInsert = array_diff($new, $old);
        $toDelete = array_diff($old, $new);
        $connection = $this->getConnection();
        $table = $this->getTable(self::STORE_TABLE);
        if ($toDelete) {
            $connection->delete($table, ['launch_id = ?' => (int)$object->getId(), 'store_id IN (?)' => $toDelete]);
        }
        if ($toInsert) {
            $rows = [];
            foreach ($toInsert as $storeId) {
                $rows[] = ['launch_id' => (int)$object->getId(), 'store_id' => (int)$storeId];
            }
            $connection->insertMultiple($table, $rows);
        }
    }
}
