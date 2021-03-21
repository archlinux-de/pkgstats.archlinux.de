<?php

namespace App\Tests\Controller;

use App\Entity\Package;
use SymfonyDatabaseTest\DatabaseTestCase;

/**
 * @coversNothing
 */
class SmokeTest extends DatabaseTestCase
{
    /**
     * @param string $url
     * @dataProvider provideUrls
     */
    public function testRequestIsSuccessful(string $url): void
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setName('pacman')
            ->setMonth(201812);
        $entityManager->persist($package);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', $url);

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testUnknownUrlFails(): void
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
            ['/api/packages?startMonth=201812'],
            ['/api/packages/pacman?startMonth=201812'],
            ['/api/packages/pacman/series?startMonth=201812'],
            ['/api/doc.json'],
            ['/sitemap.xml']
        ];
    }
}
