<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model;

use InvalidArgumentException;

/**
 * Exact decimal helper for all monetary values. Money is carried as decimal strings with scale 4 and is only
 * ever combined with bcmath; PHP floats are never used for arithmetic.
 */
class Decimal
{
    public const SCALE = 4;

    private const PATTERN = '/^-?\d{1,16}(\.\d{1,4})?$/';

    /**
     * True when $value is an int or a string holding at most 16 integer and 4 fractional digits.
     * Floats are rejected on purpose: a float cannot be trusted to carry an exact amount.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isValid(mixed $value): bool
    {
        return (is_string($value) || is_int($value)) && preg_match(self::PATTERN, (string)$value) === 1;
    }

    /**
     * @param string|int $value
     * @return string Scale-4 decimal string, e.g. "12.5000"
     * @throws InvalidArgumentException
     */
    public static function normalize(string|int $value): string
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid monetary amount.', $value));
        }
        $result = bcadd((string)$value, '0', self::SCALE);
        return $result === '-0.0000' ? '0.0000' : $result;
    }

    /**
     * @param string $value Normalised decimal string
     * @return int -1, 0 or 1
     */
    public static function sign(string $value): int
    {
        return bccomp($value, '0', self::SCALE);
    }

    /**
     * Divides and rounds half-up to the working scale.
     *
     * @param string $dividend
     * @param string $divisor Must be non-zero
     * @return string
     */
    public static function divideRounded(string $dividend, string $divisor): string
    {
        $quotient = bcdiv($dividend, $divisor, self::SCALE + 1);
        $half = Decimal::sign($quotient) < 0 ? '-0.00005' : '0.00005';
        return bcadd($quotient, $half, self::SCALE);
    }

    /**
     * Whole part of a non-negative decimal, i.e. rounded down.
     *
     * @param string $value
     * @return int
     */
    public static function floorToInt(string $value): int
    {
        return Decimal::sign($value) <= 0 ? 0 : (int)explode('.', $value)[0];
    }

    /**
     * Boundary helper for Magento's own totals, which are floats: turns one into a scale-4 decimal string.
     * Magento rounds its totals to 4 places already, so nothing is lost.
     *
     * @param float|int|string|null $value
     * @return string
     */
    public static function fromFloat(float|int|string|null $value): string
    {
        return number_format((float)$value, self::SCALE, '.', '');
    }

    /**
     * Smaller of two decimal strings.
     *
     * @param string $a
     * @param string $b
     * @return string
     */
    public static function min(string $a, string $b): string
    {
        return bccomp($a, $b, self::SCALE) <= 0 ? $a : $b;
    }
}
