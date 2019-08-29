<?php

namespace App\Tests\Controller;

use SymfonyDatabaseTest\DatabaseTestCase;

/**
 * @covers \App\Controller\SitemapController
 */
class SitemapControllerTest extends DatabaseTestCase
{
    public function testIndexAction()
    {
        $client = $this->getClient();

        $client->request('GET', '/sitemap.xml');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $response = $client->getResponse()->getContent();
        $this->assertIsString($response);
        $this->assertNotFalse(\simplexml_load_string($response));
        $this->assertEmpty(\libxml_get_errors());
        $this->assertStringContainsString('<url><loc>http://localhost/</loc></url>', $response);
    }
}
