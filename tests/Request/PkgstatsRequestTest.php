<?php

namespace App\Tests\Request;

use App\Entity\Module;
use App\Entity\Package;
use App\Entity\User;
use App\Request\PkgstatsRequest;
use PHPUnit\Framework\TestCase;

class PkgstatsRequestTest extends TestCase
{
    public function testGettersAndSetters()
    {
        /** @var User $user */
        $user = $this->createMock(User::class);
        /** @var Package $package */
        $package = $this->createMock(Package::class);
        /** @var Module $module */
        $module = $this->createMock(Module::class);

        $request = new PkgstatsRequest('1.0', $user);
        $request->setQuiet(true);
        $request->addModule($module);
        $request->addPackage($package);

        $this->assertEquals('1.0', $request->getVersion());
        $this->assertSame($user, $request->getUser());
        $this->assertTrue($request->isQuiet());

        $packages = $request->getPackages();
        $this->assertCount(1, $packages);
        $this->assertSame($package, $packages[0]);

        $modules = $request->getModules();
        $this->assertCount(1, $modules);
        $this->assertSame($module, $modules[0]);
    }
}
