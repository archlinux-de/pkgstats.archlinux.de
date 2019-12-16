<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @coversNothing
 */
class ErrorTest extends WebTestCase
{
    /**
     * @param int $code
     * @dataProvider provideErrorCodes
     */
    public function testErrorPages(int $code): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/_error/' . $code);

        $this->assertEquals($code, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString((string)$code, $crawler->filter('h2')->text());
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
