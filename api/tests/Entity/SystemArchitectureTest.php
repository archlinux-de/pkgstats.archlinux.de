<?php

namespace App\Tests\Entity;

use App\Entity\SystemArchitecture;
use PHPUnit\Framework\TestCase;

class SystemArchitectureTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $systemArchitecture = (new SystemArchitecture('a'))->setMonth(201812);

        $this->assertEquals('a', $systemArchitecture->getName());
        $this->assertEquals(201812, $systemArchitecture->getMonth());
        $this->assertEquals(1, $systemArchitecture->getCount());
    }

    public function testIncrementCount(): void
    {
        $systemArchitecture = (new SystemArchitecture('a'))->setMonth(201812)
            ->incrementCount();
        $this->assertEquals(2, $systemArchitecture->getCount());
    }
}
