<?php

namespace Tests\App\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SmokeTest extends WebTestCase
{
    /**
     * @param string $url
     * @dataProvider provideUrls
     */
    public function testRequestIsSuccessful(string $url)
    {
        $client = static::createClient();

        $client->request('GET', $url);

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testUnknownUrlFails()
    {
        $client = static::createClient();

        $client->request('GET', '/unknown');

        $this->assertTrue($client->getResponse()->isNotFound());
    }

    /**
     * @return array
     */
    public function provideUrls(): array
    {
        return [
            ['/'],
            ['/fun'],
            ['/module'],
            ['/package'],
            ['/module/datatables?draw=1&length=1'],
            ['/package/datatables?draw=1&length=1'],
            ['/module.json'],
            ['/package.json'],
            ['/sitemap.xml'],
            ['/impressum']
        ];
    }
}
