<?php

namespace App\Tests\Controller;

use SymfonyDatabaseTest\DatabaseTestCase;
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
        $this->assertNotNull($this->getClient()->getContainer());
        $funConfiguration = $this->getClient()->getContainer()->getParameter('app.fun');
        /** @var string $funCategory */
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
