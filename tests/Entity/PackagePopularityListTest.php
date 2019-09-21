<?php

namespace App\Tests\Entity;

use App\Entity\PackagePopularity;
use App\Entity\PackagePopularityList;
use PHPUnit\Framework\TestCase;

class PackagePopularityListTest extends TestCase
{
    public function testSettersAndGetters()
    {
        $packagePopularity = new PackagePopularity('pacman', 22, 13, 201901, 201902);
        $packagePopularityList = new PackagePopularityList([$packagePopularity], 34);

        $this->assertEquals(1, $packagePopularityList->getCount());
        $this->assertEquals(34, $packagePopularityList->getTotal());
        $this->assertCount(1, $packagePopularityList->getPackagePopularities());
        $this->assertEquals($packagePopularity, $packagePopularityList->getPackagePopularities()[0]);
    }

    public function testJsonSerialize()
    {
        $packagePopularity = new PackagePopularity('pacman', 22, 13, 201901, 201902);
        $packagePopularityList = new PackagePopularityList([$packagePopularity], 34);

        $this->assertEquals(
            [
                'total' => 34,
                'count' => 1,
                'packagePopularities' => [$packagePopularity]
            ],
            $packagePopularityList->jsonSerialize()
        );
    }
}
