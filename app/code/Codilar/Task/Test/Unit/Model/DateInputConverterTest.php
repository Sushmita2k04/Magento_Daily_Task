<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Model;

use Codilar\Task\Model\DateInputConverter;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DateInputConverterTest extends TestCase
{
    /**
     * @param string $locale
     * @param string $pattern
     * @return DateInputConverter
     */
    private function converter(string $locale = 'en_US', string $pattern = 'M/d/yy'): DateInputConverter
    {
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('getDateFormat')->willReturn($pattern);
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->method('getLocale')->willReturn($locale);
        return new DateInputConverter($timezone, $resolver);
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function enUsProvider(): array
    {
        return [
            'short year' => ['12/31/26', '2026-12-31'],
            'long year' => ['12/31/2026', '2026-12-31'],
            'single digits' => ['1/5/26', '2026-01-05'],
            'leap day' => ['2/29/28', '2028-02-29'],
            'iso passthrough' => ['2026-12-31', '2026-12-31'],
            'surrounding spaces' => [' 12/31/26 ', '2026-12-31'],
            'day-first is not a month' => ['31/12/2026', null],
            'month 13' => ['13/1/26', null],
            'feb 30 does not roll over' => ['2/30/26', null],
            'feb 29 non-leap' => ['2/29/27', null],
            'zero month' => ['00/10/26', null],
            'trailing junk' => ['12/31/26 junk', null],
            'words' => ['tomorrow-ish', null],
            'impossible iso' => ['2026-02-30', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('enUsProvider')]
    public function testEnUsParsing(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->converter()->toIsoDate($input));
    }

    public function testDayFirstLocale(): void
    {
        $converter = $this->converter('en_GB', 'dd/MM/y');
        $this->assertSame('2026-12-31', $converter->toIsoDate('31/12/2026'));
        $this->assertNull($converter->toIsoDate('12/31/2026'));
        $this->assertNull($converter->toIsoDate('30/02/2026'));
    }
}
