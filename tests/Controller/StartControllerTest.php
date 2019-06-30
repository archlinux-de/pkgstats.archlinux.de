<?php

namespace App\Tests\Controller;

use SymfonyDatabaseTest\DatabaseTestCase;

/**
 * @covers \App\Controller\StartController
 */
class StartControllerTest extends DatabaseTestCase
{
    public function testIndexAction()
    {
        $client = $this->getClient();

        $crawler = $client->request('GET', '/');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertStringContainsString('Arch Linux package statistics', $crawler->filter('h1')->text());
    }
}
