<?php

namespace App\Tests\Controller;

use App\Entity\Package;
use SymfonyDatabaseTest\DatabaseTestCase;

/**
 * @covers \App\Controller\PackageController
 */
class PackageControllerTest extends DatabaseTestCase
{
    public function testPackageAction()
    {
        $client = $this->getClient();

        $crawler = $client->request('GET', '/packages');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertStringContainsString(
            'Package statistics',
            (string)$crawler->filter('h1')->text()
        );
    }

    public function testPackageJsonAction()
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setName('foo')
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $entityManager->persist($package);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/package.json');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertJson($client->getResponse()->getContent());
        $jsondData = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $jsondData);
        $this->assertEquals([['pkgname' => 'foo', 'count' => 1]], $jsondData);
    }

    public function testPackagesDetailAction()
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setName('foo')
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $entityManager->persist($package);
        $entityManager->flush();

        $client = $this->getClient();

        $crawler = $client->request('GET', '/packages/foo');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertStringContainsString(
            'foo',
            (string)$crawler->filter('h1')->text()
        );
    }

    public function testPackagesDetailActionReturns404OnUnknownPackage()
    {
        $client = $this->getClient();

        $client->request('GET', '/packages/foo');
        $this->assertTrue($client->getResponse()->isNotFound());
    }

    public function testComparePackagesAction()
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setName('foo')
            ->setMonth(201801);
        $entityManager->persist($package);
        $entityManager->flush();

        $client = $this->getClient();

        $crawler = $client->request('GET', '/compare/packages');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertStringContainsString(
            '201801',
            (string)$crawler->filter('#app')->attr('data-url-template')
        );
    }

    public function testComparePackagesActionReturns404OnUnknownPackage()
    {
        $client = $this->getClient();

        $client->request('GET', '/compare/packages');
        $this->assertTrue($client->getResponse()->isNotFound());
    }
}
