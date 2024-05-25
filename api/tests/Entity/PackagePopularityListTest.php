<?php

namespace App\Tests\Entity;

use App\Entity\PackagePopularity;
use App\Entity\PackagePopularityList;
use PHPUnit\Framework\TestCase;

class PackagePopularityListTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $packagePopularity = new PackagePopularity('pacman', 22, 13, 201901, 201902);
        $packagePopularityList = new PackagePopularityList([$packagePopularity], 34, 10, 0, 'pacman');

        $this->assertEquals(1, $packagePopularityList->getCount());
        $this->assertEquals(34, $packagePopularityList->getTotal());
        $this->assertCount(1, $packagePopularityList->getPackagePopularities());
        $this->assertEquals('pacman', $packagePopularityList->getQuery());
        $this->assertEquals($packagePopularity, $packagePopularityList->getPackagePopularities()[0]);
    }
}
