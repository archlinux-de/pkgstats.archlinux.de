<?php

namespace App\Tests\Controller;

use App\Tests\Util\DatabaseTestCase;

/**
 * @coversNothing
 */
class SmokeTest extends DatabaseTestCase
{
    /**
     * @param string $url
     * @dataProvider provideUrls
     */
    public function testRequestIsSuccessful(string $url)
    {
        $client = $this->getClient();

        $client->request('GET', $url);

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testUnknownUrlFails()
    {
        $client = $this->getClient();

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
            ['/impressum'],
            ['/privacy-policy']
        ];
    }
}
