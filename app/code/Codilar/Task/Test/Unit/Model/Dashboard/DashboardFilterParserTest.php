<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Model\Dashboard;

use Codilar\Task\Model\Dashboard\DashboardFilter;
use Codilar\Task\Model\Dashboard\DashboardFilterParser;
use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DashboardFilterParserTest extends TestCase
{
    /**
     * @return DashboardFilterParser parser whose store timezone is Asia/Kolkata (UTC+5:30)
     */
    private function parser(): DashboardFilterParser
    {
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('convertConfigTimeToUtc')->willReturnCallback(
            fn($date, $format) => (new \DateTimeImmutable($date, new \DateTimeZone('Asia/Kolkata')))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format($format)
        );
        return new DashboardFilterParser($timezone, new Status(), new Priority());
    }

    public function testNoParametersMeansNoFilter(): void
    {
        $filter = $this->parser()->parse([]);
        $this->assertSame(DashboardFilter::FIELD_CREATED, $filter->getDateField());
        $this->assertNull($filter->getRangeFrom());
        $this->assertNull($filter->getRangeTo());
        $this->assertSame([], $filter->getStatuses());
        $this->assertSame([], $filter->getPriorities());
    }

    public function testBlankValuesAreTreatedAsAbsent(): void
    {
        $filter = $this->parser()->parse(
            ['date_field' => '', 'date_from' => '  ', 'status' => '', 'priority' => [' ', '']]
        );
        $this->assertSame(
            ['date_field' => 'created', 'date_from' => null, 'date_to' => null, 'status' => [], 'priority' => []],
            $filter->toArray()
        );
    }

    public function testCreatedRangeCoversWholeStoreDaysInUtc(): void
    {
        $filter = $this->parser()->parse(['date_from' => '2026-11-19', 'date_to' => '2026-11-19']);
        // 19 Nov 00:00 IST = 18 Nov 18:30 UTC; 19 Nov 23:59:59 IST = 19 Nov 18:29:59 UTC
        $this->assertSame('2026-11-18 18:30:00', $filter->getRangeFrom());
        $this->assertSame('2026-11-19 18:29:59', $filter->getRangeTo());
        $this->assertSame('2026-11-19', $filter->getDateFrom());
    }

    public function testDueRangeUsesPlainDates(): void
    {
        $filter = $this->parser()->parse(
            ['date_field' => 'due', 'date_from' => '2026-01-01', 'date_to' => '2026-01-31']
        );
        $this->assertSame('due', $filter->getDateField());
        $this->assertSame('2026-01-01', $filter->getRangeFrom());
        $this->assertSame('2026-01-31', $filter->getRangeTo());
    }

    public function testOpenEndedRanges(): void
    {
        $from = $this->parser()->parse(['date_from' => '2026-05-01']);
        $this->assertNotNull($from->getRangeFrom());
        $this->assertNull($from->getRangeTo());

        $to = $this->parser()->parse(['date_to' => '2026-05-01']);
        $this->assertNull($to->getRangeFrom());
        $this->assertNotNull($to->getRangeTo());
    }

    public function testChoicesAreDeduplicatedSortedAndAcceptLists(): void
    {
        $filter = $this->parser()->parse(['status' => ['2', 0, '2'], 'priority' => '2, 0']);
        $this->assertSame([0, 2], $filter->getStatuses());
        $this->assertSame([0, 2], $filter->getPriorities());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidProvider(): array
    {
        return [
            'unknown date field' => [['date_field' => 'updated']],
            'bad date format' => [['date_from' => '19/11/2026']],
            'impossible date' => [['date_to' => '2026-02-30']],
            'datetime instead of date' => [['date_from' => '2026-11-19 10:00:00']],
            'array as date' => [['date_from' => ['2026-11-19']]],
            'from after to' => [['date_from' => '2026-12-01', 'date_to' => '2026-11-01']],
            'status out of range' => [['status' => [3]]],
            'status text' => [['status' => 'done']],
            'status fractional' => [['status' => '1.5']],
            'status nested array' => [['status' => [[1]]]],
            'priority out of range' => [['priority' => '5']],
            'sql in status' => [['status' => '1; DROP TABLE codilar_task']],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('invalidProvider')]
    public function testInvalidInputIsRejected(array $params): void
    {
        $this->expectException(InputException::class);
        $this->parser()->parse($params);
    }

    public function testAllProblemsAreReportedTogether(): void
    {
        try {
            $this->parser()->parse(['date_field' => 'x', 'date_from' => 'nope', 'status' => [9], 'priority' => [9]]);
            $this->fail('Expected InputException');
        } catch (InputException $e) {
            $this->assertCount(4, $e->getErrors());
        }
    }
}
