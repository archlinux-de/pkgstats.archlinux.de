<?php

namespace App\Tests\Entity;

use App\Entity\Mirror;
use PHPUnit\Framework\TestCase;

class MirrorTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $mirror = (new Mirror('a'))->setMonth(201812);

        $this->assertEquals('a', $mirror->getUrl());
        $this->assertEquals(201812, $mirror->getMonth());
        $this->assertEquals(1, $mirror->getCount());
    }

    public function testIncrementCount(): void
    {
        $mirror = (new Mirror('a'))->setMonth(201812)
            ->incrementCount();
        $this->assertEquals(2, $mirror->getCount());
    }
}
