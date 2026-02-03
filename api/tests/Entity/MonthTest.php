<?php

namespace App\Tests\Entity;

use App\Entity\Month;
use PHPUnit\Framework\TestCase;

class MonthTest extends TestCase
{
    public function testFromYearMonth(): void
    {
        $month = Month::fromYearMonth(202407);

        $this->assertEquals(202407, $month->getYearMonth());
    }

    public function testOffsetPositive(): void
    {
        $month = Month::fromYearMonth(202407);

        $this->assertEquals(202408, $month->offset(1)->getYearMonth());
        $this->assertEquals(202507, $month->offset(12)->getYearMonth());
    }

    public function testOffsetNegative(): void
    {
        $month = Month::fromYearMonth(202407);

        $this->assertEquals(202406, $month->offset(-1)->getYearMonth());
        $this->assertEquals(202401, $month->offset(-6)->getYearMonth());
        $this->assertEquals(202307, $month->offset(-12)->getYearMonth());
    }

    public function testOffsetAcrossYearBoundary(): void
    {
        $month = Month::fromYearMonth(202401);

        $this->assertEquals(202312, $month->offset(-1)->getYearMonth());
    }
}
