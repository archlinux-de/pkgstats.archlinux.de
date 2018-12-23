<?php

namespace App\Tests\Entity;

use App\Entity\Module;
use PHPUnit\Framework\TestCase;

class ModuleTest extends TestCase
{
    public function testSettersAndGetters()
    {
        $module = (new Module())->setName('a')->setMonth(201812);

        $this->assertEquals('a', $module->getName());
        $this->assertEquals(201812, $module->getMonth());
        $this->assertNull($module->getCount());
    }
}
