<?php
declare(strict_types=1);

namespace Codilar\Task\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Keeps existing API/admin grants working after the ACL was split.
 *
 * Before the split only `Codilar_Task::task` existed and it guarded every REST
 * route. Magento does not make new child resources inherit a parent grant, so
 * any role or integration that already had the parent would suddenly lose access.
 * This patch gives those roles the three new child permissions once.
 * It does nothing when no role holds the parent grant, and never revokes anything.
 */
class GrantChildTaskPermissions implements DataPatchInterface
{
    private const PARENT = 'Codilar_Task::task';
    private const CHILDREN = [
        'Codilar_Task::task_view',
        'Codilar_Task::task_save',
        'Codilar_Task::task_delete',
    ];

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
        $table = $this->moduleDataSetup->getTable('authorization_rule');
        $connection->startSetup();

        $roleIds = $connection->fetchCol(
            $connection->select()
                ->from($table, ['role_id'])
                ->where('resource_id = ?', self::PARENT)
                ->where('permission = ?', 'allow')
        );

        $rows = [];
        foreach ($roleIds as $roleId) {
            // authorization_rule has no unique key per role+resource, so replace rather than upsert.
            $connection->delete(
                $table,
                ['role_id = ?' => (int) $roleId, 'resource_id IN (?)' => self::CHILDREN]
            );
            foreach (self::CHILDREN as $resource) {
                $rows[] = [
                    'role_id' => (int) $roleId,
                    'resource_id' => $resource,
                    'privileges' => null,
                    'permission' => 'allow',
                ];
            }
        }
        if ($rows) {
            $connection->insertMultiple($table, $rows);
        }

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
