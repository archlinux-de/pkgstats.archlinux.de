<?php

namespace App\Tests\Request;

use App\Request\PaginationRequest;
use PHPUnit\Framework\TestCase;

class PaginationRequestTest extends TestCase
{
    public function testGettersAndSetters()
    {
        $paginationRequest = new PaginationRequest(2, 100);

        $this->assertEquals(2, $paginationRequest->getOffset());
        $this->assertEquals(100, $paginationRequest->getLimit());
    }
}
