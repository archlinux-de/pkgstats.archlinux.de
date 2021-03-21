<?php

namespace App\Tests\Entity;

use App\Entity\PackagePopularity;
use PHPUnit\Framework\TestCase;

class PackagePopularityTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $packagePopularity = new PackagePopularity('pacman', 22, 13, 201901, 201902);

        $this->assertEquals('pacman', $packagePopularity->getName());
        $this->assertEquals(22, $packagePopularity->getSamples());
        $this->assertEquals(13, $packagePopularity->getCount());
        $this->assertEquals(59.09, $packagePopularity->getPopularity());
        $this->assertEquals(201901, $packagePopularity->getStartMonth());
        $this->assertEquals(201902, $packagePopularity->getEndMonth());
    }

    public function testJsonSerialize(): void
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

    public function testEmptyPopularity(): void
    {
        $packagePopularity = new PackagePopularity('pacman', 0, 1, 201901, 201902);
        $this->assertEquals(0, $packagePopularity->getPopularity());
    }

    public function testInvalidPopularity(): void
    {
        $packagePopularity = new PackagePopularity('pacman', 1, 2, 201901, 201902);
        $this->assertEquals(100, $packagePopularity->getPopularity());
    }
}
