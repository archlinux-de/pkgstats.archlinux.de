<?php

namespace App\Tests\Request;

use App\Request\StatisticsRangeRequest;
use PHPUnit\Framework\TestCase;

class StatisticsRangeRequestTest extends TestCase
{
    public function testGettersAndSetters()
    {
        $statisticsRangeRequest = new StatisticsRangeRequest(201801, 201812);

        $this->assertEquals(201801, $statisticsRangeRequest->getStartMonth());
        $this->assertEquals(201812, $statisticsRangeRequest->getEndMonth());
    }
}
