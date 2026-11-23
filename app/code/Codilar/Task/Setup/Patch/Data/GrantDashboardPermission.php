<?php
declare(strict_types=1);

namespace Codilar\Task\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Gives the new "View Task Dashboard" permission to roles and integrations that can already view tasks,
 * since the dashboard only shows aggregates of data they could see in the grid anyway.
 *
 * Runs once at upgrade, never revokes anything, and does nothing when no role holds `task_view`.
 */
class GrantDashboardPermission implements DataPatchInterface
{
    private const VIEW = 'Codilar_Task::task_view';
    private const DASHBOARD = 'Codilar_Task::task_dashboard';

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
                ->where('resource_id = ?', self::VIEW)
                ->where('permission = ?', 'allow')
        );

        $rows = [];
        foreach ($roleIds as $roleId) {
            // authorization_rule has no unique key per role+resource, so replace rather than upsert.
            $connection->delete(
                $table,
                ['role_id = ?' => (int) $roleId, 'resource_id = ?' => self::DASHBOARD]
            );
            $rows[] = [
                'role_id' => (int) $roleId,
                'resource_id' => self::DASHBOARD,
                'privileges' => null,
                'permission' => 'allow',
            ];
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
        return [GrantChildTaskPermissions::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
