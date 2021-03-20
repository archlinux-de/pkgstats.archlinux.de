<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $user = (new User())
            ->setMirror('https://mirror.archlinux.de')
            ->setCountrycode('DE')
            ->setCpuarch('x86_64')
            ->setArch('x86_64')
            ->setTime(1234);

        $this->assertEquals('https://mirror.archlinux.de', $user->getMirror());
        $this->assertEquals('DE', $user->getCountrycode());
        $this->assertEquals('x86_64', $user->getCpuarch());
        $this->assertEquals('x86_64', $user->getArch());
        $this->assertEquals(1234, $user->getTime());
    }
}
