<?php
declare(strict_types=1);

namespace Codilar\Task\Model\Dashboard;

/**
 * Immutable, already validated dashboard filter (do not subclass; values are read-only).
 *
 * Built only by DashboardFilterParser. The "range" values are what the SQL uses (UTC datetimes for the
 * created date, plain dates for the due date); the "date" values are what the admin entered.
 */
class DashboardFilter
{
    public const FIELD_CREATED = 'created';
    public const FIELD_DUE = 'due';

    /**
     * @param string $dateField self::FIELD_CREATED or self::FIELD_DUE
     * @param string|null $dateFrom Y-m-d as entered (store timezone), or null
     * @param string|null $dateTo Y-m-d as entered (store timezone), or null
     * @param string|null $rangeFrom lower SQL bound, inclusive, or null
     * @param string|null $rangeTo upper SQL bound, inclusive, or null
     * @param int[] $statuses empty means "all"
     * @param int[] $priorities empty means "all"
     */
    public function __construct(
        private readonly string $dateField = self::FIELD_CREATED,
        private readonly ?string $dateFrom = null,
        private readonly ?string $dateTo = null,
        private readonly ?string $rangeFrom = null,
        private readonly ?string $rangeTo = null,
        private readonly array $statuses = [],
        private readonly array $priorities = []
    ) {
    }

    /**
     * Date field the range applies to.
     *
     * @return string
     */
    public function getDateField(): string
    {
        return $this->dateField;
    }

    /**
     * Start date as entered.
     *
     * @return string|null
     */
    public function getDateFrom(): ?string
    {
        return $this->dateFrom;
    }

    /**
     * End date as entered.
     *
     * @return string|null
     */
    public function getDateTo(): ?string
    {
        return $this->dateTo;
    }

    /**
     * Lower SQL bound (inclusive).
     *
     * @return string|null
     */
    public function getRangeFrom(): ?string
    {
        return $this->rangeFrom;
    }

    /**
     * Upper SQL bound (inclusive).
     *
     * @return string|null
     */
    public function getRangeTo(): ?string
    {
        return $this->rangeTo;
    }

    /**
     * Selected statuses (empty = all).
     *
     * @return int[]
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }

    /**
     * Selected priorities (empty = all).
     *
     * @return int[]
     */
    public function getPriorities(): array
    {
        return $this->priorities;
    }

    /**
     * Client-facing representation (what the filter bar shows and the URL carries).
     *
     * @return array{date_field: string, date_from: string|null, date_to: string|null, status: int[], priority: int[]}
     */
    public function toArray(): array
    {
        return [
            'date_field' => $this->dateField,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'status' => $this->statuses,
            'priority' => $this->priorities,
        ];
    }
}
