<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @covers \App\Controller\LegalController
 */
class LegalControllerTest extends WebTestCase
{
    public function testImpressumAction()
    {
        $client = static::createClient();

        $client->request('GET', '/impressum');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $response = $client->getResponse()->getContent();
        $this->assertIsString($response);
        $this->assertStringContainsString('Pierre Schmitz', $response);
        $this->assertStringContainsString('+49 228 9716608', $response);
        $this->assertStringContainsString('pierre@archlinux.de', $response);
    }

    public function testPrivacyAction()
    {
        $client = static::createClient();

        $client->request('GET', '/privacy-policy');

        $this->assertTrue($client->getResponse()->isSuccessful());
    }
}
