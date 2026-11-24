<?php
declare(strict_types=1);

namespace Codilar\Task\Model;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Turns the date typed/picked in the admin (in the admin's locale format) into Y-m-d.
 *
 * Deliberately strict: Magento's own Filter\Date is lenient and silently rolls impossible dates
 * forward (2/30/26 becomes 2 March), which would store a wrong due date without any warning.
 */
class DateInputConverter
{
    /**
     * @param TimezoneInterface $timezone
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly ResolverInterface $localeResolver
    ) {
    }

    /**
     * Convert to Y-m-d, or return null when the value is not a real date in the admin's format.
     *
     * @param string $value
     * @return string|null
     */
    public function toIsoDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $parsed && $parsed->format('Y-m-d') === $value ? $value : null;
        }

        $pattern = (string) $this->timezone->getDateFormat();
        $candidates = array_unique([
            $pattern,
            (string) preg_replace('/y+/', 'yyyy', $pattern),
            (string) preg_replace('/y+/', 'yy', $pattern),
        ]);

        foreach ($candidates as $candidate) {
            $date = $this->parse($value, $candidate);
            if ($date !== null) {
                return $date;
            }
        }
        return null;
    }

    /**
     * Parse with one ICU pattern, rejecting overflowing dates and trailing garbage.
     *
     * @param string $value
     * @param string $pattern
     * @return string|null
     */
    private function parse(string $value, string $pattern): ?string
    {
        $formatter = new \IntlDateFormatter(
            $this->localeResolver->getLocale(),
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            'UTC',
            \IntlDateFormatter::GREGORIAN,
            $pattern
        );
        $formatter->setLenient(false);

        $position = 0;
        $timestamp = $formatter->parse($value, $position);
        if ($timestamp === false || $position !== strlen($value)) {
            return null;
        }
        return gmdate('Y-m-d', (int) $timestamp);
    }
}
