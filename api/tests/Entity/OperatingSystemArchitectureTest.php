<?php

namespace App\Tests\Entity;

use App\Entity\OperatingSystemArchitecture;
use PHPUnit\Framework\TestCase;

class OperatingSystemArchitectureTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $operatingSystemArchitecture = (new OperatingSystemArchitecture('a'))->setMonth(201812);

        $this->assertEquals('a', $operatingSystemArchitecture->getName());
        $this->assertEquals(201812, $operatingSystemArchitecture->getMonth());
        $this->assertEquals(1, $operatingSystemArchitecture->getCount());
    }

    public function testIncrementCount(): void
    {
        $operatingSystemArchitecture = (new OperatingSystemArchitecture('a'))->setMonth(201812)
            ->incrementCount();
        $this->assertEquals(2, $operatingSystemArchitecture->getCount());
    }
}
