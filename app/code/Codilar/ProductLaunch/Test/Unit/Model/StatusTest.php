<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Test\Unit\Model;

use Codilar\ProductLaunch\Model\DateTimeConverter;
use Codilar\ProductLaunch\Model\Status;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StatusTest extends TestCase
{
    private function now(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s, new \DateTimeZone('UTC'));
    }

    public static function cases(): array
    {
        return [
            'future' => ['2026-11-11 00:00:00', null, '2026-11-10 12:00:00', 'upcoming'],
            'later today' => ['2026-11-10 18:00:00', null, '2026-11-10 12:00:00', 'upcoming'],
            'started earlier today, no end' => ['2026-11-10 06:00:00', null, '2026-11-10 12:00:00', 'live'],
            'exact start is live' => ['2026-11-10 12:00:00', null, '2026-11-10 12:00:00', 'live'],
            'live with end' => ['2026-11-09 00:00:00', '2026-11-11 00:00:00', '2026-11-10 12:00:00', 'live'],
            'exact end is completed' => ['2026-11-09 00:00:00', '2026-11-10 12:00:00', '2026-11-10 12:00:00', 'completed'],
            'past with end' => ['2026-11-01 00:00:00', '2026-11-02 00:00:00', '2026-11-10 12:00:00', 'completed'],
            'past without end stays live' => ['2026-01-01 00:00:00', null, '2026-11-10 12:00:00', 'live'],
        ];
    }

    #[DataProvider('cases')]
    public function testCalculate(string $start, ?string $end, string $now, string $expected): void
    {
        $this->assertSame($expected, (new Status())->calculate($start, $end, $this->now($now)));
    }

    public function testMalformedStartThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Status())->calculate('2026-13-45 99:00:00', null);
    }

    public function testToTimestampRejectsGarbageAndOverflow(): void
    {
        $s = new Status();
        $this->assertNull($s->toTimestamp('garbage'));
        $this->assertNull($s->toTimestamp('2026-02-30 10:00:00'));
        $this->assertNull($s->toTimestamp(''));
    }

    private function converter(string $tz): DateTimeConverter
    {
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('getConfigTimezone')->willReturn($tz);
        return new DateTimeConverter($timezone);
    }

    public function testStoreTimezoneToUtc(): void
    {
        // Asia/Kolkata is UTC+5:30, America/New_York UTC-5 in November
        $this->assertSame('2026-11-10 04:30:00', $this->converter('Asia/Kolkata')->toUtc('2026-11-10 10:00:00'));
        $this->assertSame('2026-11-10 15:00:00', $this->converter('America/New_York')->toUtc('2026-11-10 10:00:00'));
    }

    public function testIsoUtcFromFormIsAbsolute(): void
    {
        $this->assertSame('2026-11-10 10:00:00', $this->converter('Asia/Kolkata')->toUtc('2026-11-10T10:00:00.000Z'));
    }

    public function testEmptyAndMalformedDates(): void
    {
        $this->assertNull($this->converter('UTC')->toUtc('  '));
        $this->expectException(LocalizedException::class);
        $this->converter('UTC')->toUtc('not a date');
    }

    public function testUtcToStoreDisplay(): void
    {
        $d = $this->converter('Asia/Kolkata')->utcToStore('2026-11-10 04:30:00');
        $this->assertSame('2026-11-10 10:00:00', $d->format('Y-m-d H:i:s'));
    }
}
