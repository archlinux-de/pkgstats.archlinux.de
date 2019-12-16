<?php

namespace App\Tests\Request;

use App\Request\PackageQueryRequest;
use PHPUnit\Framework\TestCase;

class PackageQueryRequestTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $packageQueryRequest = new PackageQueryRequest('foo');

        $this->assertEquals('foo', $packageQueryRequest->getQuery());
    }
}
