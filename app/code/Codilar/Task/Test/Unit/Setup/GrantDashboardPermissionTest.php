<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Setup;

use Codilar\Task\Setup\Patch\Data\GrantChildTaskPermissions;
use Codilar\Task\Setup\Patch\Data\GrantDashboardPermission;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class GrantDashboardPermissionTest extends TestCase
{
    /**
     * @param int[] $viewRoleIds roles that hold task_view
     * @param array<int, array<string, mixed>> $deleted captured delete() calls
     * @param array<int, array<string, mixed>> $inserted captured rows passed to insertMultiple()
     * @return GrantDashboardPermission
     */
    private function patch(array $viewRoleIds, array &$deleted, array &$inserted): GrantDashboardPermission
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn($viewRoleIds);
        $connection->method('delete')->willReturnCallback(function ($table, $where) use (&$deleted) {
            $deleted[] = $where;
            return 1;
        });
        $connection->method('insertMultiple')->willReturnCallback(function ($table, $rows) use (&$inserted) {
            $inserted = $rows;
            return count($rows);
        });

        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);
        return new GrantDashboardPermission($setup);
    }

    public function testGrantsDashboardToEveryRoleThatCanViewTasks(): void
    {
        $deleted = [];
        $inserted = [];
        $this->patch([4, 9], $deleted, $inserted)->apply();

        $this->assertSame([4, 9], array_column($inserted, 'role_id'));
        $this->assertSame(['Codilar_Task::task_dashboard'], array_unique(array_column($inserted, 'resource_id')));
        $this->assertSame(['allow'], array_unique(array_column($inserted, 'permission')));
        $this->assertCount(2, $deleted, 'any existing dashboard row is replaced, never duplicated');
    }

    public function testDoesNothingWhenNoRoleCanViewTasks(): void
    {
        $deleted = [];
        $inserted = [];
        $this->patch([], $deleted, $inserted)->apply();
        $this->assertSame([], $inserted);
        $this->assertSame([], $deleted);
    }

    public function testRunsAfterTheChildPermissionPatch(): void
    {
        $this->assertSame([GrantChildTaskPermissions::class], GrantDashboardPermission::getDependencies());
    }
}
