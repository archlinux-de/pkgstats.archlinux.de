<?php

namespace App\Tests\Entity;

use App\Entity\Package;
use PHPUnit\Framework\TestCase;

class PackageTest extends TestCase
{
    public function testSettersAndGetters()
    {
        $package = (new Package())->setName('a')->setMonth(201812);

        $this->assertEquals('a', $package->getName());
        $this->assertEquals(201812, $package->getMonth());
        $this->assertNull($package->getCount());
    }
}
