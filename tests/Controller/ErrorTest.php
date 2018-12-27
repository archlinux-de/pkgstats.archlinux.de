<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @coversNothing
 */
class ErrorTest extends WebTestCase
{
    /**
     * @param string $code
     * @dataProvider provideErrorCodes
     */
    public function testErrorPages(string $code)
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/_error/' . $code);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertContains($code, $crawler->filter('h2')->text());
    }

    /**
     * @return array
     */
    public function provideErrorCodes(): array
    {
        return [
            [403],
            [404],
            [500]
        ];
    }
}
