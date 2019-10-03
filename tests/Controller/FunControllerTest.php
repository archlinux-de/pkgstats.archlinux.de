<?php

namespace App\Tests\Controller;

use SymfonyDatabaseTest\DatabaseTestCase;

/**
 * @covers \App\Controller\FunController
 */
class FunControllerTest extends DatabaseTestCase
{
    public function testFunAction()
    {
        $client = $this->getClient();

        $client->request('GET', '/fun');

        $this->assertTrue($client->getResponse()->isSuccessful());
    }
}
