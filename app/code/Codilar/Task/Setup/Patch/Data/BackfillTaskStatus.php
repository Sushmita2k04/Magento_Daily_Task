<?php
declare(strict_types=1);

namespace Codilar\Task\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Brings pre-existing rows in line with the new `status` column.
 *
 * Before `status` existed, only `is_done` was stored. Rows with is_done = 1 are
 * marked Completed (2); all others keep the default Pending (0). Idempotent and
 * a no-op on an empty table. Never touches rows that already have a status.
 */
class BackfillTaskStatus implements DataPatchInterface
{
    private const TABLE = 'codilar_task';
    private const STATUS_PENDING = 0;
    private const STATUS_COMPLETED = 2;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $connection->update(
            $this->moduleDataSetup->getTable(self::TABLE),
            ['status' => self::STATUS_COMPLETED],
            ['is_done = ?' => 1, 'status = ?' => self::STATUS_PENDING]
        );

        $connection->endSetup();
        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
