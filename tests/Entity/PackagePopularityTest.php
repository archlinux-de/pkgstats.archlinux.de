<?php

namespace App\Tests\Entity;

use App\Entity\PackagePopularity;
use PHPUnit\Framework\TestCase;

class PackagePopularityTest extends TestCase
{
    public function testSettersAndGetters()
    {
        $packagePopularity = new PackagePopularity('pacman', 22, 13, 201901, 201902);

        $this->assertEquals('pacman', $packagePopularity->getName());
        $this->assertEquals(22, $packagePopularity->getSamples());
        $this->assertEquals(13, $packagePopularity->getCount());
        $this->assertEquals(59.09, $packagePopularity->getPopularity());
        $this->assertEquals(201901, $packagePopularity->getStartMonth());
        $this->assertEquals(201902, $packagePopularity->getEndMonth());
    }

    public function testJsonSerialize()
    {
        $packagePopularity = new PackagePopularity('pacman', 22, 13, 201901, 201902);

        $this->assertEquals(
            [
                'name' => 'pacman',
                'samples' => 22,
                'count' => 13,
                'popularity' => 59.09,
                'startMonth' => 201901,
                'endMonth' => 201902
            ],
            $packagePopularity->jsonSerialize()
        );
    }
}
