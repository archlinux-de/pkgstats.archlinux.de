<?php

namespace App\Tests\Service;

use App\Service\ClientIdGenerator;
use geertw\IpAnonymizer\IpAnonymizer;
use PHPUnit\Framework\TestCase;

class ClientIdGeneratorTest extends TestCase
{
    public function testCreateClientId()
    {
        /** @var IpAnonymizer|\PHPUnit_Framework_MockObject_MockObject */
        $ipAnonymizer = $this->createMock(IpAnonymizer::class);
        $ipAnonymizer
            ->expects($this->once())
            ->method('anonymize')
            ->with('foo')
            ->willReturn('bar');
        $clientIdGenerator = new ClientIdGenerator($ipAnonymizer);
        $clientId = $clientIdGenerator->createClientId('foo');
        $this->assertNotEmpty($clientId);
        $this->assertNotEquals('foo', $clientId);
        $this->assertNotEquals('bar', $clientId);
    }
}
