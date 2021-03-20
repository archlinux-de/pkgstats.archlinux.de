<?php

namespace App\Tests\Request;

use App\Entity\Package;
use App\Request\PkgstatsRequest;
use PHPUnit\Framework\TestCase;

class PkgstatsRequestTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        /** @var Package $package */
        $package = $this->createMock(Package::class);

        $request = new PkgstatsRequest('1.0');
        $request->addPackage($package);

        $this->assertEquals('1.0', $request->getVersion());

        $packages = $request->getPackages();
        $this->assertCount(1, $packages);
        $this->assertSame($package, $packages[0]);
    }
}
