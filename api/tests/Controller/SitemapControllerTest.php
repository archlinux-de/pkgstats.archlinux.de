<?php

namespace App\Tests\Controller;

use App\Controller\SitemapController;
use PHPUnit\Framework\Attributes\CoversClass;
use SymfonyDatabaseTest\DatabaseTestCase;

#[CoversClass(SitemapController::class)]
class SitemapControllerTest extends DatabaseTestCase
{
    public function testIndexAction(): void
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
