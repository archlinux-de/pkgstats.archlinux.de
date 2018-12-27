<?php

namespace App\Tests\Controller;

use App\Tests\Util\DatabaseTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @covers \App\Controller\FunStatisticsController
 */
class FunStatisticsControllerTest extends DatabaseTestCase
{
    public function testFunAction()
    {
        $client = $this->getClient();

        $crawler = $client->request('GET', '/fun');

        $this->assertTrue($client->getResponse()->isSuccessful());

        $tableHeads = $crawler->filter('th');
        $funConfiguration = $this->getClient()->getContainer()->getParameter('app.fun');
        foreach (array_keys($funConfiguration) as $funCategory) {
            $this->assertNodeContainsText($tableHeads, $funCategory);
        }
    }

    /**
     * @param Crawler $crawler
     * @param string $text
     */
    private function assertNodeContainsText(Crawler $crawler, string $text): void
    {
        foreach ($crawler as $node) {
            if ($node->textContent == $text) {
                return;
            }
        }
        $this->fail();
    }
}
