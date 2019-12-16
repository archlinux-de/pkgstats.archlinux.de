<?php

namespace App\Tests\Service;

use App\Service\ClientIdGenerator;
use PHPUnit\Framework\TestCase;

class ClientIdGeneratorTest extends TestCase
{
    public function testCreateClientId(): void
    {
        $clientIdGenerator = new ClientIdGenerator();
        $clientId = $clientIdGenerator->createClientId('127.0.0.1');
        $this->assertNotEmpty($clientId);
        $this->assertNotEquals('127.0.0.1', $clientId);
    }
}
