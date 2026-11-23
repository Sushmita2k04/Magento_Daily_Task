<?php
declare(strict_types=1);

namespace Codilar\Task\Model\Dashboard;

use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Turns raw request parameters into a validated DashboardFilter. Anything unexpected is rejected
 * with an InputException (one message per problem); nothing unvalidated reaches the database layer.
 */
class DashboardFilterParser
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @param TimezoneInterface $timezone
     * @param Status $statusSource
     * @param Priority $prioritySource
     */
    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly Status $statusSource,
        private readonly Priority $prioritySource
    ) {
    }

    /**
     * Parse request parameters: date_field, date_from, date_to, status, priority.
     *
     * The status and priority values may be arrays or comma separated lists.
     *
     * @param array $params
     * @return DashboardFilter
     * @throws InputException
     */
    public function parse(array $params): DashboardFilter
    {
        $exception = new InputException();

        $field = $this->scalar($params['date_field'] ?? null);
        if ($field === null || $field === '') {
            $field = DashboardFilter::FIELD_CREATED;
        } elseif (!in_array($field, [DashboardFilter::FIELD_CREATED, DashboardFilter::FIELD_DUE], true)) {
            $exception->addError(__('"date_field" must be "created" or "due".'));
            $field = DashboardFilter::FIELD_CREATED;
        }

        $from = $this->parseDate($params['date_from'] ?? null, 'date_from', $exception);
        $to = $this->parseDate($params['date_to'] ?? null, 'date_to', $exception);
        if ($from !== null && $to !== null && $from > $to) {
            $exception->addError(__('"date_from" must not be later than "date_to".'));
        }

        $statuses = $this->parseChoices($params['status'] ?? null, 'status', $this->statusSource, $exception);
        $priorities = $this->parseChoices(
            $params['priority'] ?? null,
            'priority',
            $this->prioritySource,
            $exception
        );

        if ($exception->wasErrorAdded()) {
            throw $exception;
        }

        [$rangeFrom, $rangeTo] = $this->toRange($field, $from, $to);
        return new DashboardFilter($field, $from, $to, $rangeFrom, $rangeTo, $statuses, $priorities);
    }

    /**
     * SQL bounds: UTC datetimes covering whole store-timezone days for "created", plain dates for "due".
     *
     * @param string $field
     * @param string|null $from
     * @param string|null $to
     * @return array{0: string|null, 1: string|null}
     */
    private function toRange(string $field, ?string $from, ?string $to): array
    {
        if ($field === DashboardFilter::FIELD_DUE) {
            return [$from, $to];
        }
        return [
            $from === null ? null : $this->timezone->convertConfigTimeToUtc(
                $from . ' 00:00:00',
                self::DATETIME_FORMAT
            ),
            $to === null ? null : $this->timezone->convertConfigTimeToUtc(
                $to . ' 23:59:59',
                self::DATETIME_FORMAT
            ),
        ];
    }

    /**
     * Strict Y-m-d date, or null when empty.
     *
     * @param mixed $value
     * @param string $name
     * @param InputException $exception
     * @return string|null
     */
    private function parseDate(mixed $value, string $name, InputException $exception): ?string
    {
        $value = $this->scalar($value);
        if ($value === null || $value === '') {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            $exception->addError(__('"%1" must be a valid date in Y-m-d format.', $name));
            return null;
        }
        return $value;
    }

    /**
     * Validate a multi-value choice parameter against an option source.
     *
     * @param mixed $value
     * @param string $name
     * @param Status|Priority $source
     * @param InputException $exception
     * @return int[]
     */
    private function parseChoices(mixed $value, string $name, Status|Priority $source, InputException $exception): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $items = is_array($value) ? $value : explode(',', (string) $value);

        $result = [];
        foreach ($items as $item) {
            if ($item !== null && !is_scalar($item)) {
                $exception->addError(__('"%1" contains an invalid value.', $name));
                return [];
            }
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            if (!$source->isValid($item)) {
                $exception->addError(__('"%1" contains an invalid value.', $name));
                return [];
            }
            $result[(int) $item] = (int) $item;
        }
        ksort($result);
        return array_values($result);
    }

    /**
     * Single scalar parameter as a trimmed string; arrays and objects are treated as absent/invalid.
     *
     * @param mixed $value
     * @return string|null
     */
    private function scalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return is_scalar($value) ? trim((string) $value) : '__invalid__';
    }
}
