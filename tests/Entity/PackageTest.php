<?php

namespace App\Tests\Entity;

use App\Entity\Package;
use PHPUnit\Framework\TestCase;

class PackageTest extends TestCase
{
    public function testSettersAndGetters()
    {
        $package = (new Package())->setPkgname('a')->setMonth(201812);

        $this->assertEquals('a', $package->getPkgname());
        $this->assertEquals(201812, $package->getMonth());
        $this->assertNull($package->getCount());
    }
}
