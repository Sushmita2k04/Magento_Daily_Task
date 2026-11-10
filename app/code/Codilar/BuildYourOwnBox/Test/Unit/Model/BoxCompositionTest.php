<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Test\Unit\Model;

use Codilar\BuildYourOwnBox\Model\BoxComposition;
use PHPUnit\Framework\TestCase;

class BoxCompositionTest extends TestCase
{
    private function data(array $override = []): array
    {
        return $override + [
            'version' => 1,
            'coffee' => [['id' => 2, 'sku' => 'b'], ['id' => 1, 'sku' => 'a']],
            'equipment' => ['id' => 9, 'sku' => 'e'],
            'gift_message' => 'hi', 'subtotal' => 10, 'discount' => 1, 'final_price' => 9, 'weight' => 1.5,
            'snapshot' => ['coffee_category_id' => 10, 'equipment_category_id' => 20],
        ];
    }

    public function testRoundTrip(): void
    {
        $c = BoxComposition::fromArray($this->data());
        $this->assertSame([2, 1], $c->getCoffeeIds());
        $this->assertSame(9, $c->getEquipmentId());
        $this->assertSame(9.0, $c->getFinalPrice());
        $this->assertSame(10, $c->getCoffeeCategoryId());
    }

    public function testHashDiffersByContentsAndMessageButNotOrder(): void
    {
        $a = BoxComposition::fromArray($this->data());
        $reordered = BoxComposition::fromArray($this->data(['coffee' => [['id' => 1], ['id' => 2]]]));
        $other = BoxComposition::fromArray($this->data(['gift_message' => 'bye']));
        $this->assertSame($a->getHash(), $reordered->getHash());
        $this->assertNotSame($a->getHash(), $other->getHash());
    }

    public function testRejectsUnsupportedVersionAndMalformedData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BoxComposition::fromArray($this->data(['version' => 2]));
    }

    public function testRejectsMissingCoffee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BoxComposition::fromArray($this->data(['coffee' => []]));
    }
}
