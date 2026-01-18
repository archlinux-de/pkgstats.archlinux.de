<?php

namespace App\Tests\Entity;

use App\Entity\OperatingSystemId;
use PHPUnit\Framework\TestCase;

class OperatingSystemIdTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $operatingSystemId = new OperatingSystemId('arch')->setMonth(201812);

        $this->assertEquals('arch', $operatingSystemId->getId());
        $this->assertEquals(201812, $operatingSystemId->getMonth());
        $this->assertEquals(1, $operatingSystemId->getCount());
    }

    public function testIncrementCount(): void
    {
        $operatingSystemId = new OperatingSystemId('arch')->setMonth(201812)
            ->incrementCount();
        $this->assertEquals(2, $operatingSystemId->getCount());
    }

    public function testToString(): void
    {
        $operatingSystemId = new OperatingSystemId('arch')->setMonth(201812);
        $this->assertEquals('arch', (string)$operatingSystemId);
    }
}
