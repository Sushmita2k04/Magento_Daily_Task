<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Converts between store-timezone input/display values and UTC storage.
 */
class DateTimeConverter
{
    private const DB_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private readonly TimezoneInterface $timezone)
    {
    }

    /**
     * @param string $value  "Y-m-d H:i:s" (as posted by the form, store timezone) or ISO-8601 with offset
     * @return string|null   UTC "Y-m-d H:i:s", null for empty
     * @throws LocalizedException on malformed value
     */
    public function toUtc(?string $value, ?int $storeId = null): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $tz = new \DateTimeZone($this->timezone->getConfigTimezone('store', $storeId));
        foreach (['!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d\TH:i:s', '!Y-m-d\TH:i', '!Y-m-d\TH:i:sP', '!Y-m-d\TH:i:s.vP'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value, $tz);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && (!$errors || (!$errors['warning_count'] && !$errors['error_count']))) {
                return $date->setTimezone(new \DateTimeZone('UTC'))->format(self::DB_FORMAT);
            }
        }
        throw new LocalizedException(__('The date "%1" is not valid. Please use the date picker.', $value));
    }

    public function utcToStore(?string $utc, ?int $storeId = null): ?\DateTimeImmutable
    {
        if ($utc === null || trim($utc) === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!' . self::DB_FORMAT, $utc, new \DateTimeZone('UTC'));
        if ($date === false) {
            return null;
        }
        return $date->setTimezone(new \DateTimeZone($this->timezone->getConfigTimezone('store', $storeId)));
    }

    public function format(?string $utc, ?int $storeId = null): string
    {
        $date = $this->utcToStore($utc, $storeId);
        return $date ? $date->format('M j, Y g:i A (T)') : '';
    }
}
