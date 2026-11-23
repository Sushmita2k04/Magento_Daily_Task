<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Model\Source;

use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    public function testStatusOptions(): void
    {
        $source = new Status();
        $options = $source->toOptionArray();

        $this->assertSame([0, 1, 2], array_column($options, 'value'));
        $this->assertSame('Pending', (string) $source->getLabel(0));
        $this->assertSame('In Progress', (string) $source->getLabel('1'));
        $this->assertSame('Completed', (string) $source->getLabel(2));
        $this->assertSame('', $source->getLabel(99));
    }

    public function testPriorityOptions(): void
    {
        $source = new Priority();
        $options = $source->toOptionArray();

        $this->assertSame([0, 1, 2], array_column($options, 'value'));
        $this->assertSame('Low', (string) $source->getLabel(0));
        $this->assertSame('Medium', (string) $source->getLabel(1));
        $this->assertSame('High', (string) $source->getLabel(2));
    }

    public function testIsValidIsStrict(): void
    {
        $status = new Status();
        $this->assertTrue($status->isValid(0));
        $this->assertTrue($status->isValid('2'));
        $this->assertFalse($status->isValid(3));
        $this->assertFalse($status->isValid('abc'));
        $this->assertFalse($status->isValid('1.5'));
        $this->assertFalse($status->isValid(true));
        $this->assertFalse($status->isValid(null));
    }
}
