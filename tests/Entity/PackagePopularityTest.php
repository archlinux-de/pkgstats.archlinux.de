<?php

namespace App\Tests\Entity;

use App\Entity\PackagePopularity;
use PHPUnit\Framework\TestCase;

class PackagePopularityTest extends TestCase
{
    public function testSettersAndGetters()
    {
        $packagePopularity = new PackagePopularity('pacman', 22, 13);

        $this->assertEquals('pacman', $packagePopularity->getName());
        $this->assertEquals(22, $packagePopularity->getSamples());
        $this->assertEquals(13, $packagePopularity->getCount());
        $this->assertEquals(59.09, $packagePopularity->getPopularity());
    }

    public function testJsonSerialize()
    {
        $packagePopularity = new PackagePopularity('pacman', 22, 13);

        $this->assertEquals(
            [
                'name' => 'pacman',
                'samples' => 22,
                'count' => 13,
                'popularity' => 59.09
            ],
            $packagePopularity->jsonSerialize()
        );
    }
}
