<?php

namespace App\Tests\Entity;

use App\Entity\Country;
use PHPUnit\Framework\TestCase;

class CountryTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $country = new Country('a')->setMonth(201812);

        $this->assertEquals('a', $country->getCode());
        $this->assertEquals(201812, $country->getMonth());
        $this->assertEquals(1, $country->getCount());
    }

    public function testIncrementCount(): void
    {
        $country = new Country('a')->setMonth(201812)
            ->incrementCount();
        $this->assertEquals(2, $country->getCount());
    }
}
