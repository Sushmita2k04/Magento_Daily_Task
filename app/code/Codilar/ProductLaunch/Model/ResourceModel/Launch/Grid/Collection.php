<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model\ResourceModel\Launch\Grid;

use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Admin grid collection: adds the CALCULATED status (never stored) as a filterable column.
 */
class Collection extends SearchResult
{
    public const STATUS_SQL = "CASE WHEN main_table.start_at > UTC_TIMESTAMP() THEN 'upcoming' "
        . "WHEN main_table.end_at IS NOT NULL AND main_table.end_at <= UTC_TIMESTAMP() THEN 'completed' "
        . "ELSE 'live' END";

    protected function _initSelect()
    {
        parent::_initSelect();
        $expression = new Expression(self::STATUS_SQL);
        $this->addFilterToMap('calculated_status', $expression);
        $this->getSelect()->columns(['calculated_status' => $expression]);
        return $this;
    }
}
