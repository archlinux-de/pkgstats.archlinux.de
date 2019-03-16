<?php

namespace App\Tests\Request;

use App\Request\PackageQueryRequest;
use PHPUnit\Framework\TestCase;

class PackageQueryRequestTest extends TestCase
{
    public function testGettersAndSetters()
    {
        $packageQueryRequest = new PackageQueryRequest('foo');

        $this->assertEquals('foo', $packageQueryRequest->getQuery());
    }
}
